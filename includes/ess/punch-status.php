<?php
/** Read-only presence estimate. Never changes payroll, raw punches or attendance. */
function ess_punch_sequence(array $punches,int $duplicateSeconds=10):array {
 usort($punches,fn($a,$b)=>strcmp($a['punched_at'],$b['punched_at']));
 $state='none';$last=null;$inferred=false;$count=0;
 foreach($punches as $p){
  $at=strtotime($p['punched_at']);
  if($last!==null&&$at-strtotime($last)<$duplicateSeconds)continue;
  $direction=$p['direction']??'unknown';
  if(in_array($direction,['in','out'],true)){$state=$direction;$inferred=false;}
  else{$state=$state==='in'?'out':'in';$inferred=true;}
  $last=$p['punched_at'];$count++;
 }
 return ['state'=>$state,'last'=>$last,'inferred'=>$inferred,'count'=>$count];
}
function ess_punch_status(PDO $db,int $owner):array {
 $base=['state'=>'none','label'=>'Not checked in','detail'=>'No punch in the current shift','inferred'=>false];
 if(!foundation_employee($db,$owner))return array_replace($base,['label'=>'No employee profile','detail'=>'Contact HR']);
 if(!att_ready($db))return array_replace($base,['label'=>'Status unavailable','detail'=>'Attendance setup is incomplete']);
 try{$resolved=att_punch_shift($db,$owner,date('Y-m-d H:i:s'));}
 catch(InvalidArgumentException $e){
  // Inspect a recently ended shift only to flag a missing OUT, never carry IN into a new shift.
  $q=$db->prepare('SELECT attendance_date,shift_snapshot FROM hr_attendance WHERE employee_id=? AND check_in>=? ORDER BY attendance_date DESC LIMIT 1');
  $q->execute([$owner,date('Y-m-d H:i:s',time()-36*3600)]);$recent=$q->fetch(PDO::FETCH_ASSOC);
  $snapshot=$recent?json_decode($recent['shift_snapshot'],true):null;
  if(!$snapshot||!isset($snapshot['start'],$snapshot['end']))return array_replace($base,['state'=>'review','label'=>'No current shift','detail'=>'Check your shift assignment or contact HR']);
  $resolved=['day'=>$recent['attendance_date'],'shift'=>['values'=>$snapshot]];
  [$a,$b]=chr_shift_bounds($resolved['day'],$snapshot);
  if(time()<=$b->getTimestamp()+(int)att_config($db)['punch_after_minutes']*60)return array_replace($base,['state'=>'review','label'=>'Shift needs review','detail'=>'Contact HR to check shift windows']);
 }

 $day=$resolved['day'];
 $q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$owner,$day]);$attendance=$q->fetch(PDO::FETCH_ASSOC);
 if($attendance&&$attendance['source']!=='Biometric'){
  $sequence=['state'=>$attendance['check_out']?'out':'in','last'=>$attendance['check_out']?:$attendance['check_in'],'inferred'=>false];
 }else{
  if(!att_biometric_enabled($db))return $base;
  $q=$db->prepare('SELECT p.punched_at,p.direction,x.status FROM hr_punches p JOIN hr_punch_processing x ON x.punch_id=p.id WHERE x.employee_id=? AND x.attendance_date=? AND p.punched_at<=? ORDER BY p.punched_at,p.id');
  $q->execute([$owner,$day,date('Y-m-d H:i:s')]);$punches=$q->fetchAll(PDO::FETCH_ASSOC);
  foreach($punches as $p)if($p['status']!=='Processed')return array_replace($base,['state'=>'review','label'=>'Punch needs review','detail'=>'Attendance processing is incomplete']);
  $sequence=ess_punch_sequence($punches);
 }
 if($sequence['state']==='none')return $base;
 [$start,$end]=chr_shift_bounds($day,$resolved['shift']['values']);
 $missing=$sequence['state']==='in'&&time()>$end->getTimestamp()+(int)att_config($db)['punch_after_minutes']*60;
 return ['state'=>$missing?'review':$sequence['state'],'label'=>$missing?'Check-Out missing':($sequence['state']==='in'?'Checked In':'Checked Out'),
 'detail'=>date('h:i A',strtotime($sequence['last'])).' · '.($sequence['inferred']?'Inferred':'Recorded'), 'inferred'=>$sequence['inferred']];
}
