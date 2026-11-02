<?php
/** Audited interval overrides. Raw device and manual punch events are never edited. */
function att_timeline_saved(PDO $db,int $employee,string $day):?array {
 $q=$db->prepare("SELECT * FROM hr_records WHERE module='attendance_timeline' AND employee_id=? AND status='Active' AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.date'))=? ORDER BY id DESC LIMIT 1");
 $q->execute([$employee,$day]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return null;$r['values']=json_decode($r['data'],true);return $r;
}
function att_timeline_sequence(array $saved,array $punches,array $manual):array {
 $events=[];$v=$saved['values'];
 foreach($v['events'] as $e)$events[]=['punched_at'=>date('Y-m-d H:i:s',$e['at']),'direction'=>$e['state'],'manual'=>true];
 foreach($punches as $p){if(strtotime($p['punched_at'])<=$v['cutoff']||strtotime($p['punched_at'])>time())continue;if(!in_array($p['status'],['Processed','Manual protected'],true))return ['state'=>'review'];$events[]=$p;}
 foreach($manual as $p)if(strtotime($p['punched_at'])>$v['cutoff']&&strtotime($p['punched_at'])<=time())$events[]=['id'=>PHP_INT_MAX,'punched_at'=>$p['punched_at'],'direction'=>$p['action']==='Check In'?'in':'out','manual'=>true];
 return ess_punch_sequence($events);
}
function att_timeline_context(PDO $db,int $employee,string $day):array {
 $person=chr_employee($db,$employee);$day=chr_date($day);
 $q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$employee,$day]);$attendance=$q->fetch(PDO::FETCH_ASSOC)?:null;
 $assigned=chr_assignment($db,$employee,$day);$shift=$attendance?json_decode($attendance['shift_snapshot'],true):($assigned['values']??null);
 if(!$shift||!isset($shift['start'],$shift['end']))throw new InvalidArgumentException('Assign a shift for this date before editing the timeline.');
 $q=$db->prepare('SELECT p.id,p.punched_at,p.direction,x.status FROM hr_punches p JOIN hr_punch_processing x ON x.punch_id=p.id WHERE x.employee_id=? AND x.attendance_date=? ORDER BY p.punched_at,p.id');$q->execute([$employee,$day]);$raw=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$db->prepare('SELECT id,action,punched_at FROM hr_manual_punch_events WHERE employee_id=? AND attendance_date=? ORDER BY punched_at,id');$q->execute([$employee,$day]);$manual=$q->fetchAll(PDO::FETCH_ASSOC);
 $saved=att_timeline_saved($db,$employee,$day);$sequence=ess_presence_sequence($attendance,$raw,$manual,$saved);
 if($sequence['state']==='review')throw new InvalidArgumentException('Resolve the unprocessed punches for this date before editing its timeline.');
 $revision=hash('sha256',json_encode([$attendance,$raw,$manual,$saved,$shift]));
 [$start,$end]=chr_shift_bounds($day,$shift);
 return compact('person','attendance','shift','raw','manual','saved','sequence','revision')+['day'=>$day,'start'=>$start->getTimestamp(),'end'=>$end->getTimestamp(),'as_of'=>time()];
}
function att_timeline_validate(array $input,int $cutoff,int $now):array {
 if(!$input||count($input)>200)throw new InvalidArgumentException('Add between 1 and 200 timeline boundaries.');
 if($cutoff>$now||$cutoff<$now-86400)throw new InvalidArgumentException('This timeline has expired. Reload before saving.');
 $events=[];$previous=null;
 foreach($input as $e){
  if(!is_array($e)||!isset($e['at'],$e['state'])||!is_int($e['at'])||!in_array($e['state'],['in','out'],true))throw new InvalidArgumentException('Invalid timeline boundary.');
  $at=$e['at'];if($at>$cutoff||($previous!==null&&$at<=$previous))throw new InvalidArgumentException('Times must be in order, without overlaps or future punches.');
  $events[]=['at'=>$at,'state'=>$e['state'],'inferred'=>false];$previous=$at;
 }
 if($events[0]['state']!=='in'||end($events)['at']-$events[0]['at']>36*3600)throw new InvalidArgumentException('Start with Check In and keep the timeline within 36 hours.');
 if(end($events)['state']==='in'&&$cutoff-$events[0]['at']>36*3600)throw new InvalidArgumentException('Add the missing final check-out for this older shift before saving.');
 return $events;
}
function att_timeline_record(array $record,array $sequence,int $now):array {
 if(($sequence['state']??'review')==='review'||empty($sequence['events']))return array_replace($record,['status'=>'Review needed']);
 $events=$sequence['events'];$first=$events[0]['at'];$last=end($events);$out=$sequence['state']==='out'?date('Y-m-d H:i:s',$last['at']):null;
 $shift=json_decode($record['shift_snapshot'],true);if(!$shift)return $record;
 [$start,$end]=chr_shift_bounds($record['attendance_date'],$shift);
 $timing=ess_shift_timer($events,$start->getTimestamp(),$end->getTimestamp(),$shift,$now);
 // Replace the fixed break deduction with the actual gaps in this timeline.
 $adjusted=$shift;$elapsed=max(0,(int)floor((($out?$last['at']:$now)-$first)/60));
 $adjusted['break_minutes']=max(0,$elapsed-(int)floor($timing['worked']/60));
 $metrics=chr_calculate($record['attendance_date'],date('Y-m-d H:i:s',$first),$out,$adjusted,true);
 $metrics['working_minutes']=(int)floor($timing['worked']/60);$metrics['overtime_minutes']=(int)floor($timing['overtime']/60);
 return array_replace($record,$metrics,['check_in'=>date('Y-m-d H:i:s',$first),'check_out'=>$out]);
}
function att_save_timeline(PDO $db,array $actor,array $in):int {
 if(!can($db,$actor,'attendance.manage'))throw new InvalidArgumentException('Attendance editing is not permitted.');
 $employee=(int)($in['employee_id']??0);$day=chr_date((string)($in['attendance_date']??''));$reason=ftext($in,'notes',3000,true);$requestId=(int)($in['request_id']??0);
 $encoded=(string)($in['timeline_events']??'');if(strlen($encoded)>30000)throw new InvalidArgumentException('Timeline is too large.');
 $input=json_decode($encoded,true);if(!is_array($input))throw new InvalidArgumentException('Reload the timeline and try again.');
 $owns=!$db->inTransaction();if($owns)$db->beginTransaction();
 try{
  chr_lock($db);$context=att_timeline_context($db,$employee,$day);
  if(!hash_equals($context['revision'],(string)($in['timeline_revision']??'')))throw new InvalidArgumentException('A punch, shift or correction changed while you were editing. Reload the timeline before saving.');
  $events=att_timeline_validate($input,(int)($in['timeline_cutoff']??0),time());
  if($requestId){
   if(!can($db,$actor,'attendance.approve'))throw new InvalidArgumentException('Attendance approval is not permitted.');
   $q=$db->prepare("SELECT * FROM hr_requests WHERE id=? AND kind='regularisation' FOR UPDATE");$q->execute([$requestId]);$request=$q->fetch(PDO::FETCH_ASSOC);
   if(!$request||$request['status']!=='Pending'||(int)$request['user_id']!==$employee||$request['start_date']!==$day||$request['end_date']!==$day)throw new InvalidArgumentException('This request is no longer pending or does not match this employee and date.');
  }
  $old=$context['attendance'];$last=end($events);$out=$last['state']==='out'?date('Y-m-d H:i:s',$last['at']):null;
  $id=chr_save_attendance($db,$actor,['id'=>$old['id']??0,'version'=>$old['version']??0,'employee_id'=>$employee,'attendance_date'=>$day,'check_in'=>date('Y-m-d H:i:s',$events[0]['at']),'check_out'=>$out,'source'=>'Admin Manual','notes'=>$reason],false,true);
  $q=$db->prepare('SELECT * FROM hr_attendance WHERE id=?');$q->execute([$id]);$record=$q->fetch(PDO::FETCH_ASSOC);
  $record=att_timeline_record($record,['state'=>$last['state'],'events'=>$events],time());
  if($record['status']==='Review needed')throw new InvalidArgumentException('The corrected times must overlap the assigned shift.');
  $db->prepare('UPDATE hr_attendance SET working_minutes=?,late_minutes=?,early_minutes=?,overtime_minutes=?,status=? WHERE id=?')->execute([$record['working_minutes'],$record['late_minutes'],$record['early_minutes'],$record['overtime_minutes'],$record['status'],$id]);
  $db->prepare("UPDATE hr_records SET status='Superseded',version=version+1 WHERE module='attendance_timeline' AND employee_id=? AND status='Active' AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.date'))=?")->execute([$employee,$day]);
  att_record_write($db,$actor,'attendance_timeline',['title'=>'Timeline correction '.$day],['date'=>$day,'attendance_id'=>$id,'events'=>$events,'cutoff'=>(int)$in['timeline_cutoff'],'reason'=>$reason,'before_events'=>$context['sequence']['events'],'before_attendance'=>$old,'request_id'=>$requestId,'corrected_at'=>date(DATE_ATOM)],$employee,'Active');
  if($requestId){$db->prepare("UPDATE hr_requests SET status='Approved',reviewer_id=? WHERE id=?")->execute([$actor['id'],$requestId]);att_record_write($db,$actor,'attendance_decisions',['title'=>'Request #'.$requestId],['request_id'=>$requestId,'decision'=>'Validated','reason'=>$reason],$employee);}
  faudit($db,$actor,'attendance.timeline.corrected',$id);if($owns)$db->commit();return $id;
 }catch(Throwable $e){if($owns&&$db->inTransaction())$db->rollBack();throw $e;}
}
/** Bulk report reconciliation; uses saved corrections plus later accepted punches. */
function att_timeline_reports(PDO $db,array &$ctx,string $from,string $to):void {
 $q=$db->prepare("SELECT * FROM hr_records WHERE module='attendance_timeline' AND status='Active' AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.date')) BETWEEN ? AND ? ORDER BY id");$q->execute([$from,$to]);$saved=[];foreach($q as $r){$r['values']=json_decode($r['data'],true);$saved[(int)$r['employee_id']][$r['values']['date']]=$r;}if(!$saved)return;
 $q=$db->prepare('SELECT p.id,p.punched_at,p.direction,x.status,x.employee_id,x.attendance_date FROM hr_punches p JOIN hr_punch_processing x ON x.punch_id=p.id WHERE x.attendance_date BETWEEN ? AND ? ORDER BY p.punched_at,p.id');$q->execute([$from,$to]);$raw=[];foreach($q as $r)if(isset($saved[(int)$r['employee_id']][$r['attendance_date']]))$raw[(int)$r['employee_id']][$r['attendance_date']][]=$r;
 $q=$db->prepare('SELECT * FROM hr_manual_punch_events WHERE attendance_date BETWEEN ? AND ? ORDER BY punched_at,id');$q->execute([$from,$to]);$manual=[];foreach($q as $r)if(isset($saved[(int)$r['employee_id']][$r['attendance_date']]))$manual[(int)$r['employee_id']][$r['attendance_date']][]=$r;
 foreach($saved as $employee=>$days)foreach($days as $day=>$r)if(isset($ctx['attendance'][$employee][$day]))$ctx['attendance'][$employee][$day]=att_timeline_record($ctx['attendance'][$employee][$day],att_timeline_sequence($r,$raw[$employee][$day]??[],$manual[$employee][$day]??[]),time());
}
