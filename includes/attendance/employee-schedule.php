<?php
/** Employee schedule changes retain every old record and its historical dates. */
function att_employee_roster(PDO $db,int $employee):array {
 return array_values(array_filter(chr_rows($db,'roster'),fn($r)=>(int)$r['employee_id']===$employee));
}
function att_schedule_token(array $rows):string {
 return hash('sha256',json_encode(array_map(fn($r)=>[(int)$r['id'],(int)$r['version']],$rows)));
}
/** Return untouched parts of a date interval after applying a replacement. */
function att_schedule_remainders(array $values,string $from,?string $to):array {
 $end=$values['to']?:'9999-12-31';$until=$to?:'9999-12-31';
 if($values['from']>$until||$end<$from)return [$values];
 $parts=[];
 if($values['from']<$from){$left=$values;$left['to']=(new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');$parts[]=$left;}
 if($to&&$end>$to){$right=$values;$right['from']=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');$parts[]=$right;}
 return $parts;
}
function att_save_employee_schedule(PDO $db,array $actor,array $in):int {
 if(!can($db,$actor,'shifts.manage'))throw new InvalidArgumentException('Shift management is not permitted.');
 $employee=(int)($in['employee_id']??0);chr_employee($db,$employee);
 $mode=$in['schedule_mode']??'';if(!in_array($mode,['default','temporary'],true))throw new InvalidArgumentException('Choose default or temporary shift.');
 $from=chr_date((string)($in['from']??''));$to=$mode==='temporary'?chr_date((string)($in['to']??'')):null;
 if($from<date('Y-m-d')||($to&&$to<$from))throw new InvalidArgumentException('Use today or a future date. The end date must not precede the start date.');
 $notes=ftext($in,'notes',2000,true);
 $owns=!$db->inTransaction();if($owns)$db->beginTransaction();
 try {
  chr_lock($db);$rows=att_employee_roster($db,$employee);
  if(!hash_equals(att_schedule_token($rows),(string)($in['schedule_token']??'')))throw new InvalidArgumentException('This employee’s schedule changed. Refresh before saving.');
  $shift=chr_record($db,'shifts',(int)($in['shift_id']??0));if(!$shift||$shift['status']!=='Active')throw new InvalidArgumentException('Choose an active shift.');
  $q=$db->prepare('SELECT id FROM hr_attendance WHERE employee_id=? AND attendance_date>=? AND attendance_date<=? LIMIT 1');$q->execute([$employee,$from,$to?:'9999-12-31']);
  if($q->fetchColumn())throw new InvalidArgumentException('Attendance is already recorded in this period. Choose dates without recorded attendance; existing attendance must be corrected separately.');
  if($mode==='default')foreach($rows as $r)if($r['status']==='Active'&&$r['values']['from']>$from)throw new InvalidArgumentException('A future shift is already scheduled. Review its dates in assignment history before replacing the ongoing default.');
  foreach($rows as $r){
   $v=$r['values'];if($r['status']!=='Active'||$v['from']>($to?:'9999-12-31')||($v['to']?:'9999-12-31')<$from)continue;
   $parts=att_schedule_remainders($v,$from,$to);
   // Retain the original ID for the first unaffected period, or keep it as a superseded record.
   $first=array_shift($parts);
   att_record_write($db,$actor,'roster',['id'=>$r['id'],'version'=>$r['version'],'title'=>$r['title']],$first?:$v,$employee,$first?'Active':'Cancelled');
   foreach($parts as $part)att_record_write($db,$actor,'roster',['title'=>$r['title']],$part,$employee);
  }
  $id=att_record_write($db,$actor,'roster',['title'=>$mode==='temporary'?'Temporary employee shift':'Default employee shift'],['from'=>$from,'to'=>$to,'shift_id'=>(int)$shift['id'],'shift_snapshot'=>$shift['values'],'notes'=>$notes,'schedule_kind'=>$mode],$employee);
  faudit($db,$actor,'attendance.employee_schedule_changed',$employee);
  if($owns)$db->commit();return $id;
 }catch(Throwable $e){if($owns&&$db->inTransaction())$db->rollBack();throw $e;}
}
