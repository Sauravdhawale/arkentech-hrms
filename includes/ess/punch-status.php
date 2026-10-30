<?php
/** Read-only presence estimate. Never changes payroll, raw punches or attendance. */
function ess_punch_sequence(array $punches):array {
 usort($punches,fn($a,$b)=>strcmp($a['punched_at'],$b['punched_at']) ?: (($a['id']??0)<=>($b['id']??0)));
 $state='none';$last=null;$inferred=false;$count=0;$events=[];$seen=[];
 foreach($punches as $p){
  $at=strtotime($p['punched_at']);
  // Each distinct scan counts, even seconds apart. Only an identical event is a retry.
  if(empty($p['manual'])){
   $key=isset($p['id'])?'id:'.$p['id']:'event:'.json_encode([$p['device_code']??'', $p['biometric_id']??'', $p['punched_at'], $p['direction']??'unknown']);
   if(isset($seen[$key]))continue;
   $seen[$key]=true;
  }
  $direction=$p['direction']??'unknown';
  if(in_array($direction,['in','out'],true)){$state=$direction;$inferred=false;}
  else{$state=$state==='in'?'out':'in';$inferred=true;}
  $last=$p['punched_at'];$count++;$events[]=['at'=>$at,'state'=>$state,'inferred'=>$inferred,'punch_id'=>$p['id']??null];
 }
 return ['state'=>$state,'last'=>$last,'inferred'=>$inferred,'count'=>$count,'events'=>$events];
}
/** Manual attendance is a presence baseline, not a reason to ignore later device events. */
function ess_presence_sequence(?array $attendance,array $punches,array $manualEvents=[]):array {
 // Explicit admin punches remain authoritative at their own time; subsequent device punches still apply.
 if($manualEvents){
  usort($manualEvents,fn($a,$b)=>strcmp($a['punched_at'],$b['punched_at']) ?: (($a['id']??0)<=>($b['id']??0)));
  $lastManual=$manualEvents[count($manualEvents)-1]['punched_at'];
  $baseline=$attendance?($attendance['check_out']?:$attendance['check_in']):null;
  if(!$baseline||$lastManual>=$baseline){
   $events=[];
   // Do not synthesize a second IN beside the actual first raw/admin event.
   $hasFirst=false;
   foreach(array_merge($punches,$manualEvents) as $p){
    if($p['punched_at']===($attendance['check_in']??null)&&(!isset($p['status'])||in_array($p['status'],['Processed','Manual protected'],true)))$hasFirst=true;
   }
   if(!empty($attendance['check_in'])&&!$hasFirst)$events[]=['punched_at'=>$attendance['check_in'],'direction'=>'in'];
   foreach($punches as $p){
    if(!in_array($p['status'],['Processed','Manual protected'],true)){if($p['punched_at']>$lastManual)return ['state'=>'review'];continue;}
    $events[]=$p;
   }
   foreach($manualEvents as $p)$events[]=['id'=>PHP_INT_MAX,'punched_at'=>$p['punched_at'],'direction'=>$p['action']==='Check In'?'in':'out','manual'=>true];
   return ess_punch_sequence($events);
  }
 }
 $events=[];$anchor=null;
 if($attendance&&($attendance['source']??'')!=='Biometric'){
  $anchor=$attendance['check_out']?:$attendance['check_in'];
  if(!empty($attendance['check_in'])&&!empty($attendance['check_out']))$events[]=['punched_at'=>$attendance['check_in'],'direction'=>'in'];
  if($anchor)$events[]=['punched_at'=>$anchor,'direction'=>$attendance['check_out']?'out':'in'];
 }
 foreach($punches as $p){
  if($anchor&&$p['punched_at']<=$anchor)continue;
  // Manual protected means stored and mapped, but intentionally excluded from payroll updates.
  if(!in_array($p['status'],['Processed','Manual protected'],true))return ['state'=>'review'];
  $events[]=$p;
 }
 return ess_punch_sequence($events);
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
 $punches=[];
 if(att_biometric_enabled($db)){
  $q=$db->prepare('SELECT p.id,p.punched_at,p.direction,x.status FROM hr_punches p JOIN hr_punch_processing x ON x.punch_id=p.id WHERE x.employee_id=? AND x.attendance_date=? AND p.punched_at<=? ORDER BY p.punched_at,p.id');
  $q->execute([$owner,$day,date('Y-m-d H:i:s')]);$punches=$q->fetchAll(PDO::FETCH_ASSOC);
 }
 $base+=['attendance_date'=>$day,'attendance_id'=>(int)($attendance['id']??0),'attendance_version'=>(int)($attendance['version']??0)];
 $mq=$db->prepare('SELECT id,action,punched_at FROM hr_manual_punch_events WHERE employee_id=? AND attendance_date=? AND punched_at<=? ORDER BY punched_at,id');$mq->execute([$owner,$day,date('Y-m-d H:i:s')]);
 $sequence=ess_presence_sequence($attendance?:null,$punches,$mq->fetchAll(PDO::FETCH_ASSOC));
 $base['last_punch_at']=$sequence['last']??null;
 if($sequence['state']==='review')return array_replace($base,['state'=>'review','label'=>'Punch needs review','detail'=>'Attendance processing is incomplete']);
 [$start,$end]=chr_shift_bounds($day,$resolved['shift']['values']);
 $timing=ess_shift_timer($sequence['events'],$start->getTimestamp(),$end->getTimestamp(),$resolved['shift']['values'],time(),(int)att_config($db)['punch_after_minutes']*60);
 $timing['shift_label']=($resolved['shift']['title']??'Assigned shift').' · '.date('d M, h:i A',$start->getTimestamp()).' – '.date('d M, h:i A',$end->getTimestamp());
 $timing['manual_baseline']=$attendance&&$attendance['source']!=='Biometric';
 $timing['limit_label']=date('d M, h:i A',$timing['limit']);
 if($sequence['state']==='none')return $base+['timing'=>$timing];
 $missing=$sequence['state']==='in'&&time()>$end->getTimestamp()+(int)att_config($db)['punch_after_minutes']*60;
 return ['state'=>$missing?'review':$sequence['state'],'checkout_missing'=>$missing,'label'=>$missing?'Last punch: Check In':($sequence['state']==='in'?'Checked In':'Checked Out'),
 'detail'=>$missing?'No checkout recorded. Shift window ended; review required.':date('h:i A',strtotime($sequence['last'])).' · '.($sequence['inferred']?'Inferred':'Recorded'), 'inferred'=>$sequence['inferred'],'timing'=>$timing]+$base;
}


/** Read-only timer based on accepted IN/OUT intervals; no fixed break is deducted twice. */
function ess_shift_timer(array $events,int $start,int $end,array $shift,int $now,int $afterBuffer=0):array {
 $limit=$end+max(0,$afterBuffer);$until=min($now,$limit);
 $worked=0;$before=0;$regular=0;$after=0;$break=0;$first=null;$lastOut=null;$inferred=false;$state='none';
 foreach($events as $i=>$event){
  $at=(int)$event['at'];if($at>$until)break;
  $state=$event['state'];$inferred=$inferred||$event['inferred'];
  $next=min($until,(int)($events[$i+1]['at']??$until));
  if($state==='in'){
   $first=$first??$at;$worked+=max(0,$next-$at);
   $before+=max(0,min($next,$start)-$at);
   $regular+=max(0,min($next,$end)-max($at,$start));
   $after+=max(0,$next-max($at,$end));
  }elseif($first!==null){
   $lastOut=$at;$break+=max(0,min($next,$end)-max($at,$start));
  }
 }
 $target=max(0,(int)round((float)($shift['required_hours']??$shift['overtime_after']??0)*3600));
 if(!$target)$target=max(0,$end-$start-(int)($shift['break_minutes']??0)*60);
 $allowed=max(0,(int)($shift['break_minutes']??0)*60);
 $rule=$shift['overtime_rule']??'After required hours';$extra=max(0,$worked-$target);
 $overtime=$rule==='After shift end'?$after:($rule==='After both'?min($after,$extra):$extra);
 // Employee cards share the published net-overtime rule. Payroll policy stays separate.
 $late=$first===null?0:max(0,$first-$start);
 return ['as_of'=>$now,'shift_start'=>$start,'shift_end'=>$end,'limit'=>$limit,'state'=>$state,
  'worked'=>$worked,'shift_worked'=>$regular,'before_start'=>$before,'after_end'=>$after,'break'=>$break,'allowed_break'=>$allowed,'excess_break'=>max(0,$break-$allowed),
  'late'=>$late,'net_overtime'=>$before+max(0,$after-$late),
  'target'=>$target,'overtime'=>$overtime,'overtime_rule'=>$rule,'first_in'=>$first,'last_out'=>$lastOut,
  'shift_end_label'=>date('d M, h:i A',$end),
  'first_in_label'=>$first?date('d M, h:i A',$first):'—','last_out_label'=>$lastOut?date('d M, h:i A',$lastOut):'—',
  'inferred'=>$inferred,'expired'=>$now>$limit];
}





