<?php
require_once __DIR__.'/../ess/punch-status.php';

/** Map only accepted sequence events; preserve device direction and unresolved punches. */
function att_punch_type_events(?array $attendance,array $punches):array {
 $sequence=ess_presence_sequence($attendance,$punches);$types=[];
 foreach($sequence['events']??[] as $event){
  if($event['punch_id']!==null)$types[(int)$event['punch_id']]=['direction'=>$event['state'],'inferred'=>$event['inferred']];
 }
 return $types;
}

/** Complete employee/shift timelines make labels independent of filters and pagination. */
function att_display_punch_type(PDO $db,array $row,array &$cache):array {
 $known=in_array($row['direction'],['in','out'],true);
 $row['display_direction']=$known?$row['direction']:'unknown';
 $row['direction_note']=$known?'Recorded by device':'Direction unavailable';
 if($known||empty($row['employee_id'])||empty($row['attendance_date']))return $row;
 $key=$row['employee_id'].'/'.$row['attendance_date'];
 if(!array_key_exists($key,$cache)){
  $q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?');
  $q->execute([$row['employee_id'],$row['attendance_date']]);$attendance=$q->fetch(PDO::FETCH_ASSOC);
  $q=$db->prepare('SELECT p.id,p.punched_at,p.direction,x.status FROM hr_punches p JOIN hr_punch_processing x ON x.punch_id=p.id WHERE x.employee_id=? AND x.attendance_date=? AND p.punched_at<=? ORDER BY p.punched_at,p.id');
  $q->execute([$row['employee_id'],$row['attendance_date'],date('Y-m-d H:i:s')]);
  $cache[$key]=att_punch_type_events($attendance?:null,$q->fetchAll(PDO::FETCH_ASSOC));
 }
 if(isset($cache[$key][(int)$row['id']])){
  $event=$cache[$key][(int)$row['id']];$row['display_direction']=$event['direction'];
  $row['direction_note']=$event['inferred']?'Inferred · matches employee status':'Recorded';
 }
 return $row;
}
