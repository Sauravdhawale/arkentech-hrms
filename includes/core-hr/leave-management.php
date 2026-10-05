<?php
function chr_leave_management_ready(PDO $db): bool {
 try{return (bool)$db->query("SELECT name FROM hr_migrations WHERE name='009-leave-management'")->fetchColumn();}catch(Throwable $e){return false;}
}
function chr_leave_management_install(PDO $db): void {
 if(!chr_ready($db))throw new InvalidArgumentException('Install Core HR first.');
 if(chr_leave_management_ready($db))return;
 if(!$db->query("SELECT GET_LOCK('peopleflow_leave_management',10)")->fetchColumn())throw new RuntimeException('Leave Management upgrade is already running.');
 try{
  if(chr_leave_management_ready($db))return;
  foreach(explode(';',file_get_contents(dirname(__DIR__,2).'/database/009-leave-management.sql')) as $sql)if(trim($sql)!=='')$db->exec($sql);
  $db->beginTransaction();
  foreach(['leave.adjust'=>'Adjust leave balances','leave.ledger'=>'View leave ledger'] as $code=>$label)$db->prepare('INSERT IGNORE INTO permissions(code,label) VALUES(?,?)')->execute([$code,$label]);
  $db->exec("INSERT IGNORE INTO hr_migrations(name) VALUES('009-leave-management')");
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 finally{$db->query("SELECT RELEASE_LOCK('peopleflow_leave_management')");}
}
function chr_leave_policy_defaults(array $policy): array {
 $v=$policy['values']??[];
 return $v+[
  'paid'=>true,'annual_days'=>0,'accrual_type'=>'Annual','accrual_amount'=>0,
  'carry_forward_enabled'=>((float)($v['carry_forward']??0)>0),'carry_forward'=>0,
  'encashment_enabled'=>false,'encashment_limit'=>0,'allow_negative'=>false,
  'half_day_allowed'=>true,'hourly_leave_allowed'=>false,'backdated_leave_allowed'=>true,
  'backdated_limit'=>365,'requires_approval'=>true,'applicable_employee_types'=>[],
  'applicable_departments'=>[],'applicable_locations'=>[],'minimum_service_days'=>0,
  'proration_rule'=>'None','minimum_duration'=>0.5,'maximum_duration'=>366
 ];
}
function chr_leave_eligible(PDO $db,array $employee,array $policy,string $day): bool {
 if(($policy['status']??'')!=='Published')return false;
 $v=chr_leave_policy_defaults($policy);
 $types=array_values(array_filter((array)$v['applicable_employee_types']));
 if($types&&!in_array((string)($employee['employment_type']??''),$types,true))return false;
 $departments=array_map('intval',(array)$v['applicable_departments']);
 if($departments&&!in_array((int)($employee['department_id']??0),$departments,true))return false;
 $locations=array_map(fn($x)=>strtolower(trim((string)$x)),(array)$v['applicable_locations']);
 if($locations){
  $employeeLocations=array_filter(array_map(fn($x)=>strtolower(trim((string)$x)),[$employee['city']??'',$employee['state']??'',$employee['country']??'']));
  if(!array_intersect($locations,$employeeLocations))return false;
 }
 $minimum=(int)($v['minimum_service_days']??0);
 if($minimum>0){
  $joining=$employee['joining_date']??null;if(!$joining)return false;
  $service=(new DateTimeImmutable($joining))->diff(new DateTimeImmutable($day))->days;
  if($day<$joining||$service<$minimum)return false;
 }
 return true;
}
function chr_eligible_leave_policies(PDO $db,int $employeeId,string $day): array {
 $employee=chr_employee($db,$employeeId);$out=[];
 foreach(chr_rows($db,'leave_policy') as $policy)if(chr_leave_eligible($db,$employee,$policy,$day))$out[]=$policy;
 return $out;
}
function chr_leave_pending(PDO $db,int $employee,string $type,int $year,int $exclude=0): float {
 $q=$db->prepare("SELECT r.* FROM hr_requests r WHERE r.user_id=? AND r.kind='leave' AND r.category=? AND r.status='Pending' AND r.id<>? AND r.end_date>=? AND r.start_date<=?");
 $q->execute([$employee,$type,$exclude,"$year-01-01","$year-12-31"]);$pending=0;
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)foreach(chr_request_days($db,$r) as $d)if((int)substr($d['leave_date'],0,4)===$year)$pending+=(float)$d['units'];
 return $pending;
}
function chr_leave_balance_bootstrap(PDO $db,int $employee,string $type,int $year,int $actor=0): ?array {
 if(!chr_leave_management_ready($db))return null;
 $q=$db->prepare('SELECT * FROM hr_leave_balances WHERE employee_id=? AND leave_code=? AND leave_year=?');$q->execute([$employee,$type,$year]);$existing=$q->fetch(PDO::FETCH_ASSOC);if($existing)return $existing;
 $policy=chr_policy($db,$type);$entitled=0;$typeId=null;
 if($policy){$v=chr_leave_policy_defaults($policy);$entitled=(float)($v['annual_days']??0);$typeId=(int)$policy['id'];}
 foreach(chr_rows($db,'balances') as $r)if((int)$r['employee_id']===$employee&&(int)($r['values']['year']??0)===$year&&array_key_exists($type,$r['values'])){$entitled=(float)$r['values'][$type];break;}
 $used=chr_used($db,$employee,$type,$year);$available=$entitled-$used;$createdBy=$actor?:((int)$db->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1")->fetchColumn());
 $db->prepare('INSERT INTO hr_leave_balances(employee_id,leave_type_record_id,leave_code,leave_year,entitled,available) VALUES(?,?,?,?,?,?)')->execute([$employee,$typeId,$type,$year,$entitled,$available]);$id=(int)$db->lastInsertId();
 $ins=$db->prepare('INSERT IGNORE INTO hr_leave_balance_transactions(balance_id,employee_id,leave_type_record_id,leave_code,leave_year,transaction_type,amount,reference_kind,reference_id,unique_key,balance_before,balance_after,transaction_date,remarks,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 if($entitled!=0)$ins->execute([$id,$employee,$typeId,$type,$year,'OPENING_BALANCE',$entitled,'migration',null,"legacy-opening:$employee:$type:$year",0,$entitled,date('Y-m-d'),'Legacy entitlement/bootstrap',$createdBy]);
 if($used!=0)$ins->execute([$id,$employee,$typeId,$type,$year,'LEAVE_DEBIT',-$used,'migration',null,"legacy-used:$employee:$type:$year",$entitled,$available,date('Y-m-d'),'Approved leave before ledger activation',$createdBy]);
 $q=$db->prepare('SELECT * FROM hr_leave_balances WHERE id=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function chr_leave_balance_summary(PDO $db,int $employee,string $type,int $year): array {
 $policy=chr_policy($db,$type);$v=$policy?chr_leave_policy_defaults($policy):['allow_negative'=>false];
 if(chr_leave_management_ready($db)){$row=chr_leave_balance_bootstrap($db,$employee,$type,$year);$entitled=(float)($row['entitled']??0);$available=(float)($row['available']??0);}
 else{$entitled=chr_entitlement($db,$employee,$type,$year)??0;$available=$entitled-chr_used($db,$employee,$type,$year);}
 $used=chr_used($db,$employee,$type,$year);$pending=chr_leave_pending($db,$employee,$type,$year);$requestable=!empty($v['allow_negative'])?$available:max(0,$available-$pending);
 return ['entitled'=>$entitled,'used'=>$used,'pending'=>$pending,'available'=>$available,'requestable'=>$requestable];
}
function chr_leave_transaction(PDO $db,array $actor,int $employee,string $type,int $year,string $transaction,float $amount,string $referenceKind,?int $referenceId,string $uniqueKey,string $remarks): void {
 if(!chr_leave_management_ready($db)||abs($amount)<0.0001)return;
 $row=chr_leave_balance_bootstrap($db,$employee,$type,$year,(int)$actor['id']);if(!$row)throw new RuntimeException('Leave balance unavailable.');
 $q=$db->prepare('SELECT * FROM hr_leave_balances WHERE id=? FOR UPDATE');$q->execute([$row['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);$before=(float)$row['available'];$after=$before+$amount;
 $q=$db->prepare('INSERT IGNORE INTO hr_leave_balance_transactions(balance_id,employee_id,leave_type_record_id,leave_code,leave_year,transaction_type,amount,reference_kind,reference_id,unique_key,balance_before,balance_after,transaction_date,remarks,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $q->execute([$row['id'],$employee,$row['leave_type_record_id'],$type,$year,$transaction,$amount,$referenceKind,$referenceId,$uniqueKey,$before,$after,date('Y-m-d'),$remarks,$actor['id']]);
 if($q->rowCount())$db->prepare('UPDATE hr_leave_balances SET available=?,version=version+1 WHERE id=?')->execute([$after,$row['id']]);
}
function chr_leave_prompt_for_request(PDO $db,int $requestId,string $state): void {
 if(!chr_leave_management_ready($db))return;
 $q=$db->prepare("SELECT r.user_id,d.leave_date FROM hr_requests r JOIN hr_leave_days d ON d.request_id=r.id WHERE r.id=? AND d.units>0");$q->execute([$requestId]);
 $status=$state==='Approved'?'Resolved':($state==='Pending'?'Pending':($state==='Rejected'?'Open':($state==='Cancelled'?'Open':'Open')));
 foreach($q as $d){$sql="UPDATE hr_leave_absence_prompts SET status=?,leave_request_id=?,resolved_at=? WHERE employee_id=? AND absence_date=? AND status<>'Dismissed'";$db->prepare($sql)->execute([$status,$requestId,$status==='Resolved'?date('Y-m-d H:i:s'):null,$d['user_id'],$d['leave_date']]);}
}

function chr_leave_absence_prompt_refresh(PDO $db,int $employeeId): array {
 if(!chr_leave_management_ready($db))return [];
 $settings=$db->query('SELECT * FROM hr_leave_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC)?:['absence_grace_minutes'=>30,'absence_prompt_lookback_days'=>30];
 $lookback=max(1,min(90,(int)$settings['absence_prompt_lookback_days']));$grace=max(0,min(1440,(int)$settings['absence_grace_minutes']));
 $from=(new DateTimeImmutable('today'))->modify('-'.$lookback.' days')->format('Y-m-d');$to=date('Y-m-d');
 $employee=chr_employee($db,$employeeId);$rows=chr_report($db,$from,$to,['employee_id'=>$employeeId]);
 foreach($rows as $row){
  if($row['status']!=='Absent')continue;$day=$row['date'];$shift=chr_assignment($db,$employeeId,$day);if(!$shift)continue;
  [$start,$end]=chr_shift_bounds($day,$shift['values']);$eligibleAt=$end->modify('+'.$grace.' minutes');if(new DateTimeImmutable('now')<$eligibleAt)continue;
  $q=$db->prepare("SELECT id,status FROM hr_requests WHERE user_id=? AND kind='leave' AND status IN ('Pending','Approved') AND start_date<=? AND end_date>=? LIMIT 1");$q->execute([$employeeId,$day,$day]);$leave=$q->fetch(PDO::FETCH_ASSOC);if($leave)continue;
  $db->prepare("INSERT INTO hr_leave_absence_prompts(employee_id,absence_date,shift_id,status,detected_at) VALUES(?,?,?,'Open',NOW()) ON DUPLICATE KEY UPDATE status=IF(status='Resolved','Open',status),updated_at=CURRENT_TIMESTAMP")->execute([$employeeId,$day,$shift['id']??null]);
 }
 $q=$db->prepare("SELECT * FROM hr_leave_absence_prompts WHERE employee_id=? AND status IN ('Open','Pending') ORDER BY absence_date DESC,id DESC");$q->execute([$employeeId]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function chr_leave_absence_prompt_dismiss(PDO $db,array $actor,int $promptId): void {
 if(!chr_leave_management_ready($db))throw new InvalidArgumentException('Leave notification service is unavailable.');
 $q=$db->prepare("UPDATE hr_leave_absence_prompts SET status='Dismissed',dismissed_at=NOW() WHERE id=? AND employee_id=? AND status IN ('Open','Pending')");$q->execute([$promptId,$actor['id']]);if(!$q->rowCount())throw new InvalidArgumentException('This attendance reminder is no longer available.');
 faudit($db,$actor,'leave.absence_prompt_dismissed',$promptId);
}
function chr_leave_request_overlap(PDO $db,int $employee,string $from,string $to,int $exclude=0): bool {
 $q=$db->prepare("SELECT id FROM hr_requests WHERE user_id=? AND kind='leave' AND id<>? AND status IN ('Draft','Pending','Approved') AND start_date<=? AND end_date>=? LIMIT 1");$q->execute([$employee,$exclude,$to,$from]);return (bool)$q->fetchColumn();
}

function chr_leave_sync_allocation(PDO $db,array $actor,int $recordId): void {
 if(!chr_leave_management_ready($db))return;
 $record=chr_record($db,'balances',$recordId);if(!$record||$record['status']!=='Active')return;
 $employee=(int)$record['employee_id'];$year=(int)($record['values']['year']??0);if(!$employee||!$year)return;
 foreach($record['values'] as $type=>$target){
  if(in_array($type,['year','allocation_details','notes'],true)||!is_numeric($target))continue;
  $row=chr_leave_balance_bootstrap($db,$employee,$type,$year,(int)$actor['id']);if(!$row)continue;
  $target=(float)$target;$currentEntitled=(float)$row['entitled'];$delta=$target-$currentEntitled;
  if(abs($delta)<0.0001)continue;
  chr_leave_transaction($db,$actor,$employee,$type,$year,$delta>0?'MANUAL_CREDIT':'MANUAL_DEBIT',$delta,'balance_record',$recordId,"allocation-sync:$recordId:$type:".$record['version'],'Allocation updated by HR');
  $db->prepare('UPDATE hr_leave_balances SET entitled=?,version=version+1 WHERE id=?')->execute([$target,$row['id']]);
 }
}
