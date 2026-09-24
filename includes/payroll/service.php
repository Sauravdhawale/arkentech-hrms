<?php
require_once __DIR__.'/config.php';
function pay_month(string $month):array {
 if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))throw new InvalidArgumentException('Choose a valid payroll month.');
 $from=chr_date($month.'-01');return [$from,(new DateTimeImmutable($from))->format('Y-m-t')];
}
function pay_event(PDO $db,array $actor,string $action,?int $run=null,?int $entry=null,string $reason=''):void {
 $db->prepare('INSERT INTO hr_payroll_events(run_id,entry_id,actor_id,action,reason) VALUES(?,?,?,?,?)')->execute([$run,$entry,$actor['id'],$action,substr($reason,0,1000)]);faudit($db,$actor,'payroll.'.$action,$entry??$run??0);
}
function pay_run(PDO $db,int $id,bool $lock=false):array {
 $q=$db->prepare('SELECT * FROM hr_payroll_runs WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Payroll run not found.');return $r;
}
function pay_mutable(array $run):void {if(in_array($run['status'],['Finalized','Paid'],true))throw new InvalidArgumentException('Finalized payroll is locked. Record a documented adjustment in a later payroll.');}
function pay_version(array $run,array $in):void {if((int)($in['version']??0)!==(int)$run['version'])throw new InvalidArgumentException('Payroll changed. Refresh this page.');}
function pay_company_snapshot(PDO $db):array {
 $company=$db->query('SELECT * FROM company_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
 $logo=$db->query("SELECT mime,content FROM hr_media WHERE purpose='company_logo' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
 return ['name'=>$company['name'],'address'=>trim(implode(', ',array_filter([$company['address'],$company['city'],$company['state'],$company['country'],$company['pin_code']]))),'logo'=>$logo?'data:'.$logo['mime'].';base64,'.base64_encode($logo['content']):''];
}
function pay_create_run(PDO $db,array $actor,string $month,array $employeeIds=[]):int {
 pay_allow($db,$actor,'payroll.process.manage');[$from,$to]=pay_month($month);if($from>date('Y-m-d'))throw new InvalidArgumentException('Future payroll cannot be started.');
 $db->beginTransaction();try{chr_lock($db);$settings=pay_settings($db,$from);$slips=array_values(pay_current($db,'pay_payslip',$from));$snapshot=['payroll'=>$settings,'payslip'=>$slips?$slips[0]['values']:pay_slip_defaults(),'company'=>pay_company_snapshot($db)];
  $q=$db->prepare('SELECT id FROM hr_payroll_runs WHERE month=?');$q->execute([$month]);if($q->fetchColumn())throw new InvalidArgumentException('A run already exists for this month. Open that run.');
  $db->prepare('INSERT INTO hr_payroll_runs(month,settings_snapshot,created_by) VALUES(?,?,?)')->execute([$month,pay_json($snapshot),$actor['id']]);$id=(int)$db->lastInsertId();
  $q=$db->prepare("SELECT e.user_id FROM employees e JOIN users u ON u.id=e.user_id WHERE e.deleted_at IS NULL AND u.active=1 AND e.employment_status='Active' AND (e.joining_date IS NULL OR e.joining_date<=?)");$q->execute([$to]);$eligible=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$employeeIds=array_values(array_unique(array_map('intval',$employeeIds)));if(array_diff($employeeIds,$eligible))throw new InvalidArgumentException('Choose eligible active employees.');$selected=$employeeIds?:$eligible;if(!$selected)throw new InvalidArgumentException('No eligible employees.');
  foreach($selected as $selectedId)pay_legacy_check($db,$selectedId,$month);
  foreach(array_map(fn($id)=>['user_id'=>$id],$selected) as $e)$db->prepare("INSERT INTO hr_payroll_entries(run_id,employee_id,snapshot,exceptions) VALUES(?,?,'{}','Not calculated')")->execute([$id,$e['user_id']]);
  pay_event($db,$actor,'run.created',$id);$db->commit();return $id;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/** Reuses chr_day results. Never writes to attendance, employee or leave records. */
function pay_calculate_employee(array $employee,array $days,array $salaries,array $settings,array $rulesByDay,array $adjustments,int $scheduledDays):array {
 $exceptions=[];$rows=[];$basisByDay=[];$segments=[];$variableLimit=0.0;$otRateWeight=0.0;$salaryByDay=[];
 $totalDays=count($days);$activeDays=count(array_filter($days,fn($d)=>!in_array($d['status'],['Not started','Not active'],true)));
 $denominator=$settings['proration']==='Working Days'?$scheduledDays:($settings['proration']==='Fixed 30 Days'?30:$totalDays);
 if(!$denominator)throw new InvalidArgumentException('No scheduled working days. Assign a shift before calculating payroll.');
 $fixedWeight=$settings['proration']==='Fixed 30 Days'&&$activeDays===$totalDays?30/$totalDays:1;
 foreach($days as $day){
  if(in_array($day['status'],['Not started','Not active'],true))continue;
  if(in_array($day['status'],['Unscheduled','Review needed','Open','Pending'],true)||$day['note']==='Check-out pending'||str_starts_with($day['note'],'Punch recorded during approved leave'))$exceptions[]=$day['date'].': '.$day['status'].($day['note']?' — '.$day['note']:'');
  $assignment=null;foreach($salaries as $s)if($s['effective_from']<=$day['date']&&(!$s['effective_to']||$s['effective_to']>=$day['date'])){$assignment=$s;break;}
  if(!$assignment){$exceptions[]=$day['date'].': Missing Salary Configuration';continue;}
  $salary=json_decode($assignment['snapshot'],true);$structure=$salary['structure'];$structure['statutory']=$rulesByDay[$day['date']]??[];
  $parts=pay_breakup($structure,(int)$assignment['monthly_gross'],$salary['manual']);
  $segments[$assignment['id']]=['assignment_id'=>(int)$assignment['id'],'effective_from'=>$assignment['effective_from'],'effective_to'=>$assignment['effective_to'],'salary'=>$salary];
  $salaryByDay[$day['date']]=$salary;
  $weight=$settings['proration']==='Working Days'?(float)$day['working_days']:$fixedWeight;
  $values=['GROSS'=>(int)$assignment['monthly_gross']];foreach($parts['components'] as $part)$values[$part['code']]=$part['amount'];
  if(!array_key_exists($settings['lop_basis'],$values))throw new InvalidArgumentException('LOP basis component is missing: '.$settings['lop_basis']);
  $basisByDay[$day['date']]=$values[$settings['lop_basis']];
  $lossUnits=(float)$day['unpaid_leave']+($settings['lop_days']==='Absent + unpaid leave'?(float)$day['absent']:0);
  foreach($parts['components'] as $part){
   if(($part['section']??'Fixed')==='Variable')continue;
   $key=$part['code'];if(!isset($rows[$key]))$rows[$key]=$part+['total'=>0.0];
   // Statutory rules apply to prorated wages, reduced proportionally for configured LOP.
   $partWeight=$weight;
   if(!empty($part['statutory'])&&(int)$assignment['monthly_gross']>0)$partWeight=max(0,$weight-min(1,$lossUnits)*$values[$settings['lop_basis']]/(int)$assignment['monthly_gross']);
   $rows[$key]['total']+=$part['amount']*$partWeight/$denominator;
  }
  if(!empty($settings['variable_enabled'])&&!empty($structure['variable_enabled']))$variableLimit+=(int)$assignment['monthly_gross']*(float)$structure['variable_cap']/100*$weight/$denominator;
  $rate=0;
  if(!empty($settings['ot_enabled'])){
   $method=$settings['ot_method'];
   if($method==='Fixed Hourly Rate')$rate=pay_money($settings['ot_rate']);
   elseif($method==='Custom Employee Rate')$rate=(int)$salary['ot_rate'];
   else{$basis=$method==='Gross Salary Based'?'GROSS':$settings['ot_basis'];if(!isset($values[$basis]))throw new InvalidArgumentException('OT basis component is missing.');$rate=$values[$basis]/$denominator/(float)$settings['hours_per_day'];}
  }$otRateWeight+=$rate/max(1,$activeDays);
 }
 $lines=[];foreach($rows as $r){$r['amount']=(int)round($r['total']);unset($r['total']);$lines[]=$r;}
 $lop=pay_lop($days,$settings,$basisByDay,$denominator);
 if($lop)$lines[]=['code'=>'LOP','name'=>'Loss of pay','category'=>'Deduction','amount'=>$lop,'visible'=>true,'include_ctc'=>false];
 $fixedEarnings=array_sum(array_map(fn($r)=>$r['category']==='Earning'?$r['amount']:0,$lines));
 $availableOT=array_sum(array_column($days,'overtime_minutes'))/60;$approvedOT=0.0;$variable=0;$pendingOT=false;
 foreach($adjustments as $a){
  if($a['type']==='Overtime'){
   if(!$settings['ot_enabled'])throw new InvalidArgumentException('Overtime is disabled in this run’s settings.');
   if(!$a['approved_by']){$pendingOT=true;continue;}$approvedOT+=(float)$a['units'];$amount=(int)round((float)$a['units']*$otRateWeight);$category='Earning';
  }elseif($a['type']==='Variable Percentage'||$a['type']==='Variable'){
   $amount=$a['type']==='Variable Percentage'?(int)round($fixedEarnings*(float)$a['units']/100):(int)$a['amount'];$variable+=$amount;$category='Earning';
  }else{$amount=(int)$a['amount'];$category=in_array($a['type'],['Deduction','Loan Recovery','Salary Advance Recovery'],true)?'Deduction':'Earning';}
  $lines[]=['code'=>'ADJ_'.$a['id'],'name'=>$a['component']?:$a['type'],'category'=>$category,'amount'=>$amount,'visible'=>true,'include_ctc'=>false,'adjustment_id'=>(int)$a['id'],'type'=>$a['type']];
 }
 if($pendingOT)$exceptions[]='Overtime approval pending';
 if($approvedOT>$availableOT+0.0001)$exceptions[]='Approved OT exceeds the existing attendance total';
 if($variable>(int)round($variableLimit))$exceptions[]='Variable pay exceeds the configured salary-structure limit';
 $gross=0;$deductions=0;$employer=0;
 foreach($lines as $l){if($l['category']==='Earning')$gross+=$l['amount'];elseif($l['category']==='Deduction')$deductions+=$l['amount'];else $employer+=$l['amount'];}
 $net=$gross-$deductions;
 if($settings['rounding']==='Nearest Rupee'&&$net>=0){$rounded=(int)round($net/100)*100;$delta=$rounded-$net;if($delta){$lines[]=['code'=>'ROUNDING','name'=>'Round-off','category'=>$delta>0?'Earning':'Deduction','amount'=>abs($delta),'visible'=>true];if($delta>0)$gross+=$delta;else $deductions-=$delta;$net=$rounded;}}
 if($net<0)$exceptions[]='Deductions exceed earnings';
 if(!$activeDays)$exceptions[]='No eligible days in this month';
 $attendance=chr_monthly($days)[0]??[];$attendance['approved_overtime_hours']=$approvedOT;
 return ['employee'=>array_intersect_key($employee,array_flip(['user_id','name','employee_code','department_id','department_name','designation_name','joining_date'])),'components'=>$lines,'salary_segments'=>array_values($segments),'attendance'=>$attendance,'daily_attendance'=>$days,'adjustments'=>$adjustments,'statutory_rules'=>$rulesByDay,'gross'=>$gross,'deductions'=>$deductions,'net'=>$net,'employer'=>$employer,'lop'=>$lop,'variable'=>$variable,'ot_hours'=>$approvedOT,'ot_rate'=>(int)round($otRateWeight),'exceptions'=>array_values(array_unique($exceptions)),'bank_last4'=>$salary['bank_last4']??'','pan_last4'=>$salary['pan_last4']??''];
}
function pay_calculate_run(PDO $db,array $actor,array $in):void {
 pay_allow($db,$actor,'payroll.process.manage');$db->beginTransaction();try{
  chr_lock($db);$run=pay_run($db,(int)$in['run_id'],true);pay_version($run,$in);pay_mutable($run);
  if(!in_array($run['status'],['Draft','Calculated'],true))throw new InvalidArgumentException('Return the run to Draft before recalculating.');
  [$from,$to]=pay_month($run['month']);$settings=json_decode($run['settings_snapshot'],true)['payroll'];$ctx=chr_report_context($db,$from,$to);$dates=chr_dates($from,$to);$rules=[];
  $allRules=chr_rows($db,'pay_statutory');foreach($dates as $day){$seen=[];$rules[$day]=[];foreach($allRules as $rule){$v=$rule['values'];if(isset($seen[$v['code']])||$v['effective_from']>$day||(!empty($v['effective_to'])&&$v['effective_to']<$day))continue;$seen[$v['code']]=true;if($rule['status']==='Active')$rules[$day][]=$v+['record_id'=>$rule['id'],'record_version'=>$rule['version']];}}
  $q=$db->prepare('SELECT * FROM hr_payroll_entries WHERE run_id=? ORDER BY id');$q->execute([$run['id']]);
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $entry){
   if($entry['status']==='On Hold')continue;
   try{
    $e=foundation_employee($db,(int)$entry['employee_id']);if(!$e||!$e['active']||$e['employment_status']!=='Active')throw new InvalidArgumentException('Employee is inactive or archived; review eligibility.');
    $days=[];$scheduled=0;$scheduleEmployee=$e;$scheduleEmployee['joining_date']=null;
    foreach($dates as $day){$days[]=chr_day($ctx,$e,$day);$scheduled+=(int)chr_day($ctx,$scheduleEmployee,$day)['working_days'];}
    $s=$db->prepare('SELECT * FROM hr_salary_assignments WHERE employee_id=? AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?) ORDER BY effective_from DESC');$s->execute([$e['user_id'],$to,$from]);
    $a=$db->prepare('SELECT * FROM hr_payroll_adjustments WHERE entry_id=? ORDER BY id');$a->execute([$entry['id']]);
    $snapshot=pay_calculate_employee($e,$days,$s->fetchAll(PDO::FETCH_ASSOC),$settings,$rules,$a->fetchAll(PDO::FETCH_ASSOC),$scheduled);
   }catch(InvalidArgumentException $error){$snapshot=['gross'=>0,'deductions'=>0,'net'=>0,'employer'=>0,'exceptions'=>[$error->getMessage()]];}
   $db->prepare("UPDATE hr_payroll_entries SET status='Calculated',gross=?,deductions=?,net=?,employer_cost=?,snapshot=?,exceptions=? WHERE id=?")->execute([$snapshot['gross'],$snapshot['deductions'],$snapshot['net'],$snapshot['employer'],pay_json($snapshot),implode("\n",$snapshot['exceptions']),$entry['id']]);
  }
  $db->prepare("UPDATE hr_payroll_runs SET status='Calculated',version=version+1 WHERE id=?")->execute([$run['id']]);pay_event($db,$actor,'run.calculated',(int)$run['id']);$db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function pay_adjust(PDO $db,array $actor,array $in):void {
 pay_allow($db,$actor,'payroll.process.manage');$reason=ftext($in,'reason',1000,true);$type=pay_enum($in,'type',['Earning','Deduction','Bonus','Incentive','Reimbursement','Loan Recovery','Salary Advance Recovery','Variable','Variable Percentage','Overtime']);
 $amount=pay_money($in['amount']??'0');$units=pay_number($in['units']??0,1000);if(in_array($type,['Variable Percentage','Overtime'],true)?$units<=0:$amount<=0)throw new InvalidArgumentException('Enter a positive amount or units.');
 $db->beginTransaction();try{chr_lock($db);$run=pay_run($db,(int)$in['run_id'],true);pay_mutable($run);pay_version($run,$in);
  if(!in_array($run['status'],['Draft','Calculated'],true))throw new InvalidArgumentException('Return the payroll to Draft before adding adjustments.');
  $q=$db->prepare('SELECT id FROM hr_payroll_entries WHERE id=? AND run_id=?');$q->execute([(int)$in['entry_id'],$run['id']]);if(!$q->fetchColumn())throw new InvalidArgumentException('Employee payroll not found.');
  $db->prepare('INSERT INTO hr_payroll_adjustments(entry_id,type,component,amount,units,reason,created_by) VALUES(?,?,?,?,?,?,?)')->execute([(int)$in['entry_id'],$type,ftext($in,'component',80),$amount,$units,$reason,$actor['id']]);
  $db->prepare("UPDATE hr_payroll_runs SET status='Draft',version=version+1 WHERE id=?")->execute([$run['id']]);pay_event($db,$actor,'adjustment.added',(int)$run['id'],(int)$in['entry_id'],$reason);$db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function pay_transition(PDO $db,array $actor,array $in):void {
 $action=(string)($in['transition']??'');$permission=match($action){'approve','approve_ot'=>'payroll.process.approve','finalize'=>'payroll.process.finalize','paid'=>'payroll.process.pay','publish'=>'payslip.generate',default=>'payroll.process.manage'};
 pay_allow($db,$actor,$permission);$reason=ftext($in,'reason',1000,true);if(empty($in['confirm']))throw new InvalidArgumentException('Confirm this payroll action.');
 $db->beginTransaction();try{chr_lock($db);$run=pay_run($db,(int)$in['run_id'],true);pay_version($run,$in);$id=(int)$run['id'];
  if($action==='publish'){if(!in_array($run['status'],['Finalized','Paid'],true))throw new InvalidArgumentException('Finalize payroll before generating payslips.');pay_publish($db,$run);}
  elseif($action==='paid'){if($run['status']!=='Finalized')throw new InvalidArgumentException('Only finalized payroll can be marked paid.');$db->prepare("UPDATE hr_payroll_runs SET status='Paid',paid_at=NOW() WHERE id=?")->execute([$id]);$db->prepare("UPDATE hr_payroll_entries SET status='Paid' WHERE run_id=?")->execute([$id]);}
  else{
   pay_mutable($run);
   if($action==='approve_ot'){
    if(!in_array($run['status'],['Draft','Calculated'],true))throw new InvalidArgumentException('Return to Draft to approve overtime.');
    $db->prepare("UPDATE hr_payroll_adjustments a JOIN hr_payroll_entries e ON e.id=a.entry_id SET a.approved_by=?,a.approved_at=NOW() WHERE a.id=? AND e.run_id=? AND a.type='Overtime' AND a.approved_by IS NULL")->execute([$actor['id'],(int)($in['adjustment_id']??0),$id]);
    $db->prepare("UPDATE hr_payroll_runs SET status='Draft' WHERE id=?")->execute([$id]);
   }elseif($action==='hold'||$action==='release'){
    if(!in_array($run['status'],['Draft','Calculated'],true))throw new InvalidArgumentException('Return to Draft before changing holds.');
    $q=$db->prepare("UPDATE hr_payroll_entries SET status=?,exceptions=? WHERE id=? AND run_id=?");$q->execute([$action==='hold'?'On Hold':'Draft',$action==='hold'?$reason:'Recalculate after releasing hold',(int)$in['entry_id'],$id]);if(!$q->rowCount())throw new InvalidArgumentException('Payroll entry not found.');
    $db->prepare("UPDATE hr_payroll_runs SET status='Draft' WHERE id=?")->execute([$id]);
   }elseif($action==='draft'){$db->prepare("UPDATE hr_payroll_runs SET status='Draft' WHERE id=?")->execute([$id]);}
   else{
    $next=['review'=>['Calculated','Reviewed'],'approve'=>['Reviewed','Approved'],'finalize'=>['Approved','Finalized']][$action]??null;
    $settings=json_decode($run['settings_snapshot'],true);if($action==='finalize'&&empty($settings['payroll']['approval_required'])&&$run['status']==='Reviewed')$next=['Reviewed','Finalized'];
    if(!$next||$run['status']!==$next[0])throw new InvalidArgumentException('Invalid payroll workflow transition.');
    $q=$db->prepare("SELECT COUNT(*) FROM hr_payroll_entries WHERE run_id=? AND (exceptions<>'' OR status IN ('Draft','On Hold'))");$q->execute([$id]);if($q->fetchColumn())throw new InvalidArgumentException('Resolve all exceptions and holds, then recalculate.');
    $q=$db->prepare('SELECT COUNT(*) FROM hr_payroll_entries WHERE run_id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Cannot process an empty run.');
    if($action==='finalize'){$existing=$db->prepare('SELECT employee_id FROM hr_payroll_entries WHERE run_id=?');$existing->execute([$id]);foreach($existing as $entry)pay_legacy_check($db,(int)$entry['employee_id'],$run['month']);[, $to]=pay_month($run['month']);$closing=(new DateTimeImmutable($to))->modify('+'.(int)$settings['payroll']['closing_day'].' days')->format('Y-m-d');if(date('Y-m-d')<$closing)throw new InvalidArgumentException('Payroll can be finalized from '.$closing.' after the month closes.');}
    $db->prepare('UPDATE hr_payroll_runs SET status=? WHERE id=?')->execute([$next[1],$id]);$db->prepare('UPDATE hr_payroll_entries SET status=? WHERE run_id=?')->execute([$next[1],$id]);
    if($action==='finalize'){$db->prepare('UPDATE hr_payroll_runs SET finalized_at=NOW() WHERE id=?')->execute([$id]);if(!empty($settings['payroll']['auto_payslip']))pay_publish($db,$run);}
   }
  }
  $db->prepare('UPDATE hr_payroll_runs SET version=version+1 WHERE id=?')->execute([$id]);pay_event($db,$actor,'run.'.$action,$id,isset($in['entry_id'])?(int)$in['entry_id']:null,$reason);$db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function pay_publish(PDO $db,array $run):void {
 $settings=json_decode($run['settings_snapshot'],true);$format=$settings['payslip']['number_format'];
 $q=$db->prepare('SELECT id FROM hr_payroll_entries WHERE run_id=? AND published_at IS NULL');$q->execute([$run['id']]);
 foreach($q as $entry){$number=strtr($format,['{YYYY}'=>substr($run['month'],0,4),'{MM}'=>substr($run['month'],5,2),'{ID}'=>(string)$entry['id']]);$db->prepare('UPDATE hr_payroll_entries SET payslip_number=?,published_at=NOW() WHERE id=?')->execute([$number,$entry['id']]);}
}

function pay_add_employees(PDO $db,array $actor,array $in):void {
 pay_allow($db,$actor,'payroll.process.manage');$db->beginTransaction();try{chr_lock($db);$run=pay_run($db,(int)$in['run_id'],true);pay_mutable($run);pay_version($run,$in);if(!in_array($run['status'],['Draft','Calculated'],true))throw new InvalidArgumentException('Return to Draft before adding employees.');
 [, $to]=pay_month($run['month']);$sql="SELECT e.user_id FROM employees e JOIN users u ON u.id=e.user_id WHERE e.deleted_at IS NULL AND u.active=1 AND e.employment_status='Active' AND (e.joining_date IS NULL OR e.joining_date<=?)";$args=[$to];if(!empty($in['employee_id'])){$sql.=' AND e.user_id=?';$args[]=(int)$in['employee_id'];}$q=$db->prepare($sql);$q->execute($args);$ids=$q->fetchAll(PDO::FETCH_COLUMN);$added=0;
 foreach($ids as $id){$exists=$db->prepare('SELECT id FROM hr_payroll_entries WHERE run_id=? AND employee_id=?');$exists->execute([$run['id'],$id]);if($exists->fetchColumn())continue;pay_legacy_check($db,(int)$id,$run['month']);$db->prepare("INSERT INTO hr_payroll_entries(run_id,employee_id,snapshot,exceptions) VALUES(?,?,'{}','Not calculated')")->execute([$run['id'],$id]);$added++;}
 if(!$added)throw new InvalidArgumentException('No new eligible employees to add.');$db->prepare("UPDATE hr_payroll_runs SET status='Draft',version=version+1 WHERE id=?")->execute([$run['id']]);pay_event($db,$actor,'employees.added',(int)$run['id'],null,(string)$added.' employees added');$db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function pay_legacy_check(PDO $db,int $employee,string $month):void {
 $q=$db->prepare("SELECT data FROM hr_records WHERE module='payroll' AND employee_id=? AND status='Published'");$q->execute([$employee]);foreach($q as $row)if((json_decode($row['data'],true)['month']??'')===$month)throw new InvalidArgumentException('An existing published payroll already covers employee #'.$employee.' for '.$month.'. Keep its original payslip; do not pay this month twice.');
}
