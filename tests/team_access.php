<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/includes/foundation/employees.php';
require dirname(__DIR__).'/includes/team/service.php';
function team_test(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function team_denied(callable $fn,string $message):void{$denied=false;try{$fn();}catch(InvalidArgumentException $e){$denied=true;}team_test($denied,$message);}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];
$leaderRole=(int)$db->query("SELECT id FROM roles WHERE slug='team-leader'")->fetchColumn();
$employeeRole=(int)$db->query("SELECT id FROM roles WHERE slug='employee'")->fetchColumn();
$pair=$db->query('SELECT g.id designation_id,d.id department_id FROM designations g JOIN departments d ON d.id=g.department_id WHERE g.active=1 AND d.active=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$people=[];
foreach(['lead','otherlead','empty','member','outsider','unassigned','removed'] as $name){
 $id=save_employee($db,$admin,['first_name'=>'Scope','last_name'=>ucfirst($name),'username'=>'scope.'.$name,'employee_code'=>'SCOPE-'.strtoupper($name),'department_id'=>$pair['department_id'],'designation_id'=>$pair['designation_id'],'role_id'=>in_array($name,['lead','otherlead','empty'],true)?$leaderRole:$employeeRole,'joining_date'=>'2026-01-01','employment_status'=>'Active','employment_type'=>'Full Time'],[]);
 $db->prepare('UPDATE users SET must_change_password=0 WHERE id=?')->execute([$id]);
 $people[$name]=['id'=>$id,'role'=>'employee'];
}
foreach(['member','removed'] as $name)$db->prepare('UPDATE employees SET manager_id=? WHERE user_id=?')->execute([$people['lead']['id'],$people[$name]['id']]);
$db->prepare('UPDATE employees SET manager_id=? WHERE user_id=?')->execute([$people['otherlead']['id'],$people['outsider']['id']]);
$db->prepare('UPDATE employees SET deleted_at=NOW() WHERE user_id=?')->execute([$people['removed']['id']]);
$lead=$people['lead'];$member=$people['member']['id'];$outsider=$people['outsider']['id'];
team_test(can($db,$lead,'team.view'),'Team Leader does not get scoped access');
team_test(!can($db,$people['member'],'team.view'),'Regular employee gets team access');
team_test(array_map('intval',array_column(team_members($db,$lead),'user_id'))===[$member],'Team includes another manager, unassigned or deleted employees');
team_test(team_members($db,$lead,'Outsider')===[],'Search leaks another team');
team_test(team_members($db,$people['empty'])===[],'Empty team fell back to all employees');
team_test(count(team_members($db,$lead,'SCOPE-MEMBER'))===1,'Employee ID search failed');
foreach([0,$outsider,$lead['id'],$people['unassigned']['id'],$people['removed']['id']] as $id)team_denied(fn()=>team_attendance($db,$lead,$id,'2031-02'),'Out-of-team attendance accepted');
team_denied(fn()=>team_members($db,$people['member']),'Ordinary employee lists team');
team_denied(fn()=>team_attendance($db,$lead,$member,'2031-99'),'Invalid month accepted');
$records=team_attendance($db,$lead,$member,'2031-02');
team_test(count($records)===28&&array_unique(array_column($records,'employee_id'))===[$member],'Monthly report scope or full month failed');
team_test(!isset($records[0]['note'])&&!isset($records[0]['record_id']),'Report exposes internal fields');
$live=team_live($db,$lead,team_members($db,$lead));team_test(count($live)===1&&(int)$live[0]['user_id']===$member,'Live attendance scope failed');
team_denied(fn()=>team_live($db,$lead,[['user_id'=>$outsider]]),'Forged live member accepted');
$db->beginTransaction();try{
 // Even an accidental full-access role configuration cannot bypass the team boundary.
 $db->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions')->execute([$leaderRole]);
 foreach(['dashboard.view','employees.view','employees.edit','attendance.view','attendance.manage','attendance.reports','payroll.view','documents.view','settings.edit','roles.edit'] as $permission){team_test(!can($db,$lead,$permission),'Broad permission bypass: '.$permission);team_test(can($db,$admin,$permission),'Super Admin permissions changed');}
 team_test(can($db,$lead,'leave.approve'),'Existing routed leave approval permission lost');
 $db->prepare('UPDATE employees SET manager_id=? WHERE user_id=?')->execute([$people['otherlead']['id'],$member]);
 team_denied(fn()=>team_attendance($db,$lead,$member,'2031-02'),'Manager transfer did not revoke old leader');
 team_test(count(team_attendance($db,$people['otherlead'],$member,'2031-02'))===28,'New leader cannot view transferred employee');
 $db->prepare('UPDATE roles SET active=0 WHERE id=?')->execute([$leaderRole]);team_test(!can($db,$lead,'team.view'),'Disabled role retains access');team_denied(fn()=>team_members($db,$lead),'Disabled role lists people');
 $db->prepare('UPDATE roles SET active=1 WHERE id=?')->execute([$leaderRole]);
 $db->prepare('UPDATE user_roles SET role_id=? WHERE user_id=?')->execute([$employeeRole,$lead['id']]);team_test(!can($db,$lead,'team.view'),'Changed role retains access');
}finally{$db->rollBack();}
team_test(team_csv_cell('=CMD()')==="'=CMD()"&&team_csv_cell(' Normal name')===' Normal name','CSV formula protection failed');
file_put_contents('/tmp/team-access-fixtures.json',json_encode($people));
echo "PASS: direct-report scope, search, live/monthly attendance, role revocation, manager transfer, private fields, write permissions and CSV safety.\n";
