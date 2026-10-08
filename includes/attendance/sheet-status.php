<?php
// Status corrections are separate, audited records: device punches are never rewritten.
function att_sheet_record(PDO $db,int $employee,string $day):?array {
 $q=$db->prepare("SELECT * FROM hr_records WHERE module='attendance_sheet_status' AND employee_id=? AND title=? ORDER BY id DESC LIMIT 1");$q->execute([$employee,'Attendance '.$day]);$r=$q->fetch(PDO::FETCH_ASSOC);if($r)$r['values']=json_decode($r['data'],true)?:[];return $r?:null;
}
function att_sheet_leaves(PDO $db,int $employee,string $day):array {
 $q=$db->prepare("SELECT * FROM hr_requests WHERE kind='leave' AND user_id=? AND status IN ('Pending','Approved') AND start_date<=? AND end_date>=? ORDER BY id");$q->execute([$employee,$day,$day]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function att_sheet_fingerprint(PDO $db,int $employee,string $day):string {
 $q=$db->prepare('SELECT id,version FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$employee,$day]);
 $leaves=att_sheet_leaves($db,$employee,$day);$state=[];foreach($leaves as $leave)$state[]=[$leave['id'],$leave['status'],$leave['category'],ess_workflow($db,(int)$leave['id'])];
 return hash('sha256',json_encode([$q->fetch(PDO::FETCH_ASSOC),att_sheet_record($db,$employee,$day),$state]));
}
function att_sheet_save(PDO $db,array $actor,array $in):string {
 if(!can($db,$actor,'attendance.manage'))throw new InvalidArgumentException('Attendance editing is not permitted.');
 $employee=(int)($in['employee_id']??0);$day=chr_date((string)($in['attendance_date']??''));$employeeRow=att_active_employee($db,$employee);
 if($day>date('Y-m-d'))throw new InvalidArgumentException('Future attendance cannot be marked here. Use Apply Leave for planned leave.');
 if(!empty($employeeRow['joining_date'])&&$day<$employeeRow['joining_date'])throw new InvalidArgumentException('Attendance cannot be marked before the joining date.');
 $choice=(string)($in['sheet_status']??'');$normal=['Automatic','Present','Absent','Half Day','Holiday','Week Off'];
 $isLeave=str_starts_with($choice,'leave:');$type=$isLeave?substr($choice,6):'';
 if(!$isLeave&&!in_array($choice,$normal,true))throw new InvalidArgumentException('Choose a valid attendance status.');
 $reason=ftext($in,'notes',3000,true);
 $db->beginTransaction();try{
  chr_lock($db);
  if(!hash_equals(att_sheet_fingerprint($db,$employee,$day),(string)($in['fingerprint']??'')))throw new InvalidArgumentException('Attendance or leave changed. Close this form and reopen the cell before saving.');
  $leaves=att_sheet_leaves($db,$employee,$day);$old=att_sheet_record($db,$employee,$day);
  // Existing leave must be explicitly cancelled through its own workflow, never hidden by a sheet label.
  if($leaves){
   if($isLeave&&count($leaves)===1&&$leaves[0]['category']===$type){$db->commit();return 'This leave type is already recorded: '.$leaves[0]['status'].'.';}
   if(empty($in['replace_leave']))throw new InvalidArgumentException('Confirm replacement of the existing leave. Its balance will be adjusted through the leave ledger.');
   foreach($leaves as $leave){if($leave['start_date']!==$day||$leave['end_date']!==$day)throw new InvalidArgumentException('This day belongs to a multi-day leave request. Edit or cancel that request from Leave before changing this cell.');chr_review_leave($db,$actor,(int)$leave['id'],'Cancelled',$reason);}
  }
  $message='Attendance status updated.';
  if($isLeave){
   $request=chr_create_leave($db,$actor,['employee_id'=>$employee,'start_date'=>$day,'end_date'=>$day,'day_part'=>'Full Day','category'=>$type,'subject'=>'Attendance sheet correction','details'=>$reason]);
   $q=$db->prepare('SELECT * FROM hr_requests WHERE id=?');$q->execute([$request]);$requestRow=$q->fetch(PDO::FETCH_ASSOC);
   if(ess_can_review($db,$actor,$requestRow))chr_review_leave($db,$actor,$request,'Approved',$reason);
   $q->execute([$request]);$status=$q->fetch(PDO::FETCH_ASSOC)['status'];$message=$status==='Approved'?'Leave recorded and balance updated.':'Leave submitted to the approval route. The sheet shows it as pending until approved.';
  }
  att_record_write($db,$actor,'attendance_sheet_status',['id'=>$old['id']??0,'version'=>$old['version']??0,'title'=>'Attendance '.$day],['date'=>$day,'status'=>$isLeave?'Automatic':$choice,'notes'=>$reason,'source'=>'Admin Manual','updated_at'=>date('c')],$employee,($isLeave||$choice==='Automatic')?'Inactive':'Active');
  att_record_write($db,$actor,'attendance_changes',['title'=>'Sheet correction '.$day],['date'=>$day,'before'=>$old,'status'=>$choice,'reason'=>$reason],$employee);
  $db->commit();return $message;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
