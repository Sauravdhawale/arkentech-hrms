<?php
require_once __DIR__.'/punch-status.php';

/** Original, attributed events only. Corrections and attendance baselines are not scans. */
function ess_punch_history_rows(?array $attendance,array $raw,array $manual,?array $timeline=null):array {
 $sequence=ess_presence_sequence($attendance,$raw,$manual,$timeline);$types=[];
 foreach($sequence['events']??[] as $event){
  if(isset($event['punch_id']))$types[(int)$event['punch_id']]=$event;
 }
 $rows=[];$seen=[];
 foreach($raw as $p){
  $key='device:'.$p['id'];if(isset($seen[$key]))continue;$seen[$key]=true;
  $known=in_array($p['direction'],['in','out'],true);$type=$types[(int)$p['id']]??null;
  $direction=$known?$p['direction']:($type['state']??'unknown');
  $note=$known?'Recorded by device':($type?'Inferred from punch sequence':'Direction needs review');
  if(!in_array($p['status'],['Processed','Manual protected'],true))$note.=' · Processing incomplete';
  $rows[]=['id'=>$key,'at'=>$p['punched_at'],'direction'=>$direction,'source'=>'Biometric','note'=>$note];
 }
 foreach($manual as $p){
  $key='manual:'.$p['id'];if(isset($seen[$key]))continue;$seen[$key]=true;
  $direction=$p['action']==='Check In'?'in':($p['action']==='Check Out'?'out':'unknown');
  $rows[]=['id'=>$key,'at'=>$p['punched_at'],'direction'=>$direction,'source'=>$p['source']==='Admin Manual'?'Admin manual':'Web / manual','note'=>'Recorded punch'];
 }
 usort($rows,fn($a,$b)=>strcmp($a['at'],$b['at'])?:strnatcmp($a['id'],$b['id']));
 $counts=['total'=>count($rows),'in'=>0,'out'=>0,'unknown'=>0];
 foreach($rows as &$row){$counts[$row['direction']]++;$at=new DateTimeImmutable($row['at']);$row['at']=$at->format(DATE_ATOM);$row['date_label']=$at->format('d M Y');$row['time_label']=$at->format('h:i:s A');}unset($row);
 return ['counts'=>$counts,'punches'=>$rows,'corrected'=>$timeline!==null];
}

function ess_punch_history(PDO $db,int $owner,string $requested=''):array {
 $today=date('Y-m-d');
 if($requested!==''){
  $date=DateTimeImmutable::createFromFormat('!Y-m-d',$requested);
  if(!$date||$date->format('Y-m-d')!==$requested||$requested>$today)throw new InvalidArgumentException('Choose a valid shift date, today or earlier.');
 }
 $base=['date'=>$requested?:$today,'today'=>$today,'timezone'=>date_default_timezone_get(),'as_of'=>date(DATE_ATOM),'updated_label'=>date('h:i:s A'),'counts'=>['total'=>0,'in'=>0,'out'=>0,'unknown'=>0],'punches'=>[],'corrected'=>false];
 if(!foundation_employee($db,$owner))return $base+['message'=>'Your account has no linked employee profile. Contact HR.'];
 if(!att_ready($db))throw new RuntimeException('Attendance is unavailable.');
 if($requested===''){$status=ess_punch_status($db,$owner);$base['date']=$status['attendance_date']??$today;}
 $day=$base['date'];$now=date('Y-m-d H:i:s');
 $q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$owner,$day]);$attendance=$q->fetch(PDO::FETCH_ASSOC);
 $q=$db->prepare('SELECT p.id,p.punched_at,p.direction,x.status FROM hr_punches p JOIN hr_punch_processing x ON x.punch_id=p.id WHERE x.employee_id=? AND x.attendance_date=? AND p.punched_at<=? ORDER BY p.punched_at,p.id');$q->execute([$owner,$day,$now]);$raw=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$db->prepare('SELECT id,action,punched_at,source FROM hr_manual_punch_events WHERE employee_id=? AND attendance_date=? AND punched_at<=? ORDER BY punched_at,id');$q->execute([$owner,$day,$now]);$manual=$q->fetchAll(PDO::FETCH_ASSOC);
 return array_replace($base,ess_punch_history_rows($attendance?:null,$raw,$manual,att_timeline_saved($db,$owner,$day)),['message'=>'']);
}
