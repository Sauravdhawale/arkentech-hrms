<?php
/** Team access is a read-only role boundary, not a department-wide permission. */
function team_is_leader(PDO $db,array $actor):bool {
 if(($actor['role']??'')!=='employee'||empty($actor['id']))return false;
 try{
  $q=$db->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id JOIN users u ON u.id=ur.user_id JOIN employees e ON e.user_id=u.id WHERE ur.user_id=? AND r.slug='team-leader' AND r.active=1 AND u.active=1 AND e.deleted_at IS NULL");
  $q->execute([(int)$actor['id']]);return (bool)$q->fetchColumn();
 }catch(Throwable $e){return false;}
}

function team_require_leader(PDO $db,array $actor):void {
 if(!team_is_leader($db,$actor))throw new InvalidArgumentException('Team access is not available for this account.');
}

/** Only public work-profile fields leave this query. Never select personal/payroll data. */
function team_members(PDO $db,array $actor,string $search=''):array {
 team_require_leader($db,$actor);
 $args=[(int)$actor['id'],(int)$actor['id']];
 $where='e.manager_id=? AND e.user_id<>? AND e.deleted_at IS NULL';
 $search=trim($search);
 if(strlen($search)>100)throw new InvalidArgumentException('Keep the search within 100 characters.');
 if($search!==''){$where.=' AND (u.name LIKE ? OR e.employee_code LIKE ?)';$args[]='%'.$search.'%';$args[]='%'.$search.'%';}
 $q=$db->prepare('SELECT e.user_id,u.name,e.employee_code,e.employment_status,e.joining_date,d.name department,g.name designation FROM employees e JOIN users u ON u.id=e.user_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN designations g ON g.id=e.designation_id WHERE '.$where.' ORDER BY u.name,e.user_id');
 $q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
}

function team_member(PDO $db,array $actor,int $employee):array {
 team_require_leader($db,$actor);
 if($employee<1)throw new InvalidArgumentException('Choose an employee in your team.');
 // Re-read membership on every request, including exports and after a manager change.
 $q=$db->prepare('SELECT e.user_id,u.name,e.employee_code,e.employment_status,e.joining_date,d.name department,g.name designation FROM employees e JOIN users u ON u.id=e.user_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN designations g ON g.id=e.designation_id WHERE e.manager_id=? AND e.user_id=? AND e.user_id<>? AND e.deleted_at IS NULL');
 $q->execute([(int)$actor['id'],$employee,(int)$actor['id']]);
 $member=$q->fetch(PDO::FETCH_ASSOC);if($member)return $member;
 throw new InvalidArgumentException('This employee is not in your team.');
}
