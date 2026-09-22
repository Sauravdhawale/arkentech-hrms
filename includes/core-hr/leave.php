<?php
// Leave dates and rules are snapshotted at submission. Older requests keep calendar-day semantics.
function chr_dates(string $from,string $to):array {
 chr_date($from);chr_date($to);$a=new DateTimeImmutable($from);$b=new DateTimeImmutable($to);
 if($b<$a||$a->diff($b)->days>365)throw new InvalidArgumentException('Select a date range of at most 366 days.');
 $days=[];for($d=$a;$d<=$b;$d=$d->modify('+1 day'))$days[]=$d->format('Y-m-d');return $days;
}
function chr_policy(PDO $db,string $type):?array {foreach(chr_rows($db,'leave_policy') as $r)if(($r['values']['type']??'')===$type)return $r;return null;}
function chr_request_days(PDO $db,array $r):array {
 $q=$db->prepare('SELECT leave_date,units,day_part FROM hr_leave_days WHERE request_id=? ORDER BY leave_date');$q->execute([$r['id']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if($rows)return $rows;
 // Legacy requests may span more than the new UI limit; do not modify them.
 $rows=[];if(empty($r['start_date'])||empty($r['end_date']))return $rows;
 for($d=new DateTimeImmutable($r['start_date']),$end=new DateTimeImmutable($r['end_date']);$d<=$end;$d=$d->modify('+1 day'))$rows[]=['leave_date'=>$d->format('Y-m-d'),'units'=>1,'day_part'=>'Full Day'];return $rows;
}
function chr_used(PDO $db,int $employee,string $type,int $year,int $exclude=0):float {
 $q=$db->prepare("SELECT * FROM hr_requests WHERE user_id=? AND kind='leave' AND category=? AND status='Approved' AND id<>? AND end_date>=? AND start_date<=?");$q->execute([$employee,$type,$exclude,"$year-01-01","$year-12-31"]);$days=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)foreach(chr_request_days($db,$r) as $d)if((int)substr($d['leave_date'],0,4)===$year)$days[$d['leave_date']]=min(1,($days[$d['leave_date']]??0)+(float)$d['units']);return (float)array_sum($days);
}
function chr_entitlement(PDO $db,int $employee,string $type,int $year):?float {
 foreach(chr_rows($db,'balances') as $r)if((int)$r['employee_id']===$employee&&$r['status']==='Active'&&(int)($r['values']['year']??0)===$year&&array_key_exists($type,$r['values']))return (float)$r['values'][$type];
 $p=chr_policy($db,$type);return $p?(float)($p['values']['annual_days']??0):null;
}
function chr_validate_leave(PDO $db,array $r):void {
 $q=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$r['user_id']]);
 $q=$db->prepare('SELECT policy_snapshot FROM hr_leave_details WHERE request_id=?');$q->execute([$r['id']]);$raw=$q->fetchColumn();$policy=$raw?json_decode($raw,true):['allow_negative'=>$r['category']==='LWP'];
 $days=chr_request_days($db,$r);$years=[];$requested=[];foreach($days as $d){if((float)$d['units']<=0)continue;$requested[$d['leave_date']]=$d;$year=(int)substr($d['leave_date'],0,4);$years[$year]=($years[$year]??0)+(float)$d['units'];}
 if(!$requested)throw new InvalidArgumentException('The request has no chargeable leave days.');
 $q=$db->prepare("SELECT * FROM hr_requests WHERE user_id=? AND kind='leave' AND status='Approved' AND id<>? AND start_date<=? AND end_date>=?");$q->execute([$r['user_id'],$r['id'],$r['end_date'],$r['start_date']]);
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $other)foreach(chr_request_days($db,$other) as $d){$new=$requested[$d['leave_date']]??null;if($new&&(float)$d['units']>0&&($new['day_part']==='Full Day'||$d['day_part']==='Full Day'||$new['day_part']===$d['day_part']))throw new InvalidArgumentException('Approved leave already covers this day or half-day period.');}
 if(!empty($policy['allow_negative']))return;
 foreach($years as $year=>$amount){$entitlement=chr_entitlement($db,(int)$r['user_id'],$r['category'],$year);if($entitlement===null||chr_used($db,(int)$r['user_id'],$r['category'],$year,(int)$r['id'])+$amount>$entitlement+0.0001)throw new InvalidArgumentException('Insufficient '.$r['category'].' balance for '.$year.'. Set an allocation before approval.');}
}
function chr_create_leave(PDO $db,array $actor,array $in,array $file=[] ,bool $self=false):int {
 if(!$self&&!can($db,$actor,'leave.create'))throw new InvalidArgumentException('Leave creation is not permitted.');
 $employee=$self?(int)$actor['id']:(int)($in['employee_id']??0);chr_employee($db,$employee);
 $from=chr_date((string)($in['start_date']??''));$to=chr_date((string)($in['end_date']??''));$range=chr_dates($from,$to);$part=$in['day_part']??'Full Day';
 if(!in_array($part,['Full Day','First Half','Second Half'],true)||($part!=='Full Day'&&$from!==$to))throw new InvalidArgumentException('Half-day leave must cover exactly one date.');
 $type=ftext($in,'category',40,true);$reason=ftext($in,'details',5000,true);$subject=ftext($in,'subject',180)?:'Leave request';$upload=null;
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){if($file['error']!==UPLOAD_ERR_OK||$file['size']>4000000||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Upload a PDF, JPEG or PNG up to 4 MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true))throw new InvalidArgumentException('Unsupported attachment type.');$upload=['name'=>substr(preg_replace('/[^a-zA-Z0-9._ -]/','_',basename($file['name'])),0,190),'mime'=>$mime,'content'=>file_get_contents($file['tmp_name'])];}
 $db->beginTransaction();try{chr_lock($db);$policy=chr_policy($db,$type);if(!$policy||$policy['status']!=='Published')throw new InvalidArgumentException('Choose an active leave type.');$days=[];$total=0;foreach($range as $day){$shift=chr_assignment($db,$employee,$day);$units=(chr_holiday($db,$day)||chr_weekoff($shift,$day))?0:($part==='Full Day'?1:0.5);$days[]=[$day,$units,$part];$total+=$units;}if($total<=0)throw new InvalidArgumentException('This range contains only holidays or weekly offs.');
 $db->prepare("INSERT INTO hr_requests(user_id,kind,category,subject,details,start_date,end_date) VALUES(?,'leave',?,?,?,?,?)")->execute([$employee,$type,$subject,$reason,$from,$to]);$id=(int)$db->lastInsertId();$attachment=null;
 if($upload){$db->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('leave_attachment',?,?,'Received',?,?)")->execute([$employee,$upload['name'],json_encode(['request_id'=>$id]),$actor['id']]);$attachment=(int)$db->lastInsertId();$db->prepare('INSERT INTO hr_files(record_id,uploaded_by,filename,mime,content) VALUES(?,?,?,?,?)')->execute([$attachment,$actor['id'],$upload['name'],$upload['mime'],$upload['content']]);}
 $db->prepare('INSERT INTO hr_leave_details(request_id,type_record_id,day_part,days,policy_snapshot,attachment_record_id) VALUES(?,?,?,?,?,?)')->execute([$id,$policy['id'],$part,$total,json_encode($policy['values']),$attachment]);$q=$db->prepare('INSERT INTO hr_leave_days(request_id,leave_date,units,day_part) VALUES(?,?,?,?)');foreach($days as $d)$q->execute([$id,...$d]);faudit($db,$actor,'core.leave.submitted',$id);$db->commit();return $id;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function chr_review_leave(PDO $db,array $actor,int $id,string $status):void {
 if(!in_array($status,['Approved','Rejected','Cancelled'],true)||!can($db,$actor,$status==='Cancelled'?'leave.manage':'leave.approve'))throw new InvalidArgumentException('This decision is not permitted.');
 $db->beginTransaction();try{chr_lock($db);$q=$db->prepare("SELECT * FROM hr_requests WHERE id=? AND kind='leave' FOR UPDATE");$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||!in_array($r['status'],$status==='Cancelled'?['Pending','Approved']:['Pending'],true))throw new InvalidArgumentException('This request has already been processed. Refresh its status.');if($status==='Approved')chr_validate_leave($db,$r);$db->prepare('UPDATE hr_requests SET status=?,reviewer_id=? WHERE id=?')->execute([$status,$actor['id'],$id]);faudit($db,$actor,'core.leave.'.strtolower($status),$id);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
