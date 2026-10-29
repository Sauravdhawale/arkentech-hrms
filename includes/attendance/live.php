<?php
/** Admin presence view uses the same shift-aware state as the employee header. */
require_once dirname(__DIR__).'/ess/punch-status.php';

function att_live_revision(array $status):string {
 return hash('sha256',json_encode([$status['attendance_date']??'', $status['attendance_id']??0,
  $status['attendance_version']??0,$status['state'],$status['last_punch_at']??'']));
}
function att_live_people(PDO $db,array $filters):array {
 $where=["e.deleted_at IS NULL","u.active=1","e.employment_status IN ('Active','Notice Period')"];$args=[];
 foreach(['employee_id'=>'e.user_id','department_id'=>'e.department_id'] as $key=>$column)if(!empty($filters[$key])){$where[]=$column.'=?';$args[]=(int)$filters[$key];}
 if(trim((string)($filters['search']??''))!==''){$where[]='(u.name LIKE ? OR e.employee_code LIKE ?)';$args[]='%'.trim($filters['search']).'%';$args[]='%'.trim($filters['search']).'%';}
 $q=$db->prepare('SELECT e.user_id,e.employee_code,u.name,d.name department FROM employees e JOIN users u ON u.id=e.user_id LEFT JOIN departments d ON d.id=e.department_id WHERE '.implode(' AND ',$where).' ORDER BY u.name LIMIT 201');$q->execute($args);$people=$q->fetchAll(PDO::FETCH_ASSOC);
 $limited=count($people)>200;$people=array_slice($people,0,200);
 foreach($people as &$person){$person['presence']=ess_punch_status($db,(int)$person['user_id']);$person['revision']=att_live_revision($person['presence']);}unset($person);
 return ['people'=>$people,'limited'=>$limited];
}

/** Current-time administrative correction; all timestamps/shift dates are server-resolved. */
function att_live_punch(PDO $db,array $actor,array $in):int {
 if(!can($db,$actor,'attendance.manage'))throw new InvalidArgumentException('Attendance editing is not permitted.');
 $employee=(int)($in['employee_id']??0);$person=att_active_employee($db,$employee);
 if(!in_array($person['employment_status'],['Active','Notice Period'],true))throw new InvalidArgumentException('Choose an active employee.');
 $action=(string)($in['punch_action']??'');if(!in_array($action,['Check In','Check Out'],true))throw new InvalidArgumentException('Choose Check In or Check Out.');
 $reason=ftext($in,'notes',3000,true);$nonce=(string)($in['request_key']??'');
 if(!preg_match('/^[a-f0-9]{64}$/D',$nonce))throw new InvalidArgumentException('Reload the punch form.');
 $key=hash('sha256',$actor['id'].':'.$nonce);$owns=!$db->inTransaction();if($owns)$db->beginTransaction();
 try{
  chr_lock($db);$q=$db->prepare('SELECT id FROM hr_manual_punch_events WHERE request_key=?');$q->execute([$key]);if($q->fetchColumn())throw new InvalidArgumentException('This punch was already saved.');
  $status=ess_punch_status($db,$employee);
  if(!hash_equals(att_live_revision($status),(string)($in['presence_revision']??'')))throw new InvalidArgumentException('A punch or attendance change arrived while this form was open. Close it, refresh and try again.');
  if($status['state']==='review'||empty($status['attendance_date']))throw new InvalidArgumentException('Resolve the shift or punch issue before recording a live punch. Use manual attendance for historical corrections.');
  if(($action==='Check In'&&$status['state']==='in')||($action==='Check Out'&&$status['state']!=='in'))throw new InvalidArgumentException('The employee status has changed. Refresh before recording this punch.');
  $at=date('Y-m-d H:i:s');$resolved=att_punch_shift($db,$employee,$at);$day=$resolved['day'];
  if($day!==$status['attendance_date'])throw new InvalidArgumentException('The shift has changed. Refresh before recording a punch.');
  $q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=? FOR UPDATE');$q->execute([$employee,$day]);$old=$q->fetch(PDO::FETCH_ASSOC);
  $checkIn=$old['check_in']??$at;
  $id=chr_save_attendance($db,$actor,['id'=>$old['id']??0,'version'=>$old['version']??0,'employee_id'=>$employee,'attendance_date'=>$day,'check_in'=>$checkIn,'check_out'=>$action==='Check Out'?$at:null,'source'=>'Admin Manual','notes'=>$reason]);
  $db->prepare('INSERT INTO hr_manual_punch_events(employee_id,attendance_date,action,punched_at,source,actor_id,notes,request_key) VALUES(?,?,?,?,?,?,?,?)')->execute([$employee,$day,$action,$at,'Admin Manual',$actor['id'],$reason,$key]);
  if($owns)$db->commit();return $id;
 }catch(Throwable $e){if($owns&&$db->inTransaction())$db->rollBack();throw $e;}
}
