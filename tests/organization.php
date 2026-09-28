<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/foundation/organization.php';require dirname(__DIR__).'/includes/foundation/employees.php';
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
function oc($ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function ob(callable $fn,string $why):void{try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($why);}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];$viewer=['id'=>3,'role'=>'employee'];
$empty=['departments'=>[],'designations'=>[],'employees'=>[]];$plan=org_plan($empty);
oc(count($plan['departments'])===16&&count($plan['designations'])===57,'Catalog count');
oc(org_name('designations','Sr.Team Leader Operations')==='Senior Team Leader – Operations','Senior spelling');
oc(org_name('designations','Team leader - Operations')==='Team Leader – Operations','Dash spelling');
foreach(['Hr Manager'=>'HR Manager','Quality analyst'=>'Quality Analyst','web Developer'=>'Web Developer','Web Developer Inern'=>'Web Developer Intern','accountant'=>'Accountant','Assitant Manager'=>'Assistant Manager','Digital marketing manager'=>'Marketing Manager','Team Lead – Digital Marketing'=>'Team Lead – Marketing'] as $from=>$to)oc(org_name('designations',$from)===$to,'Alias '.$from);
$conflict=$empty;$conflict['departments']=[['id'=>1,'name'=>'HR','active'=>1],['id'=>2,'name'=>'Human Resources','active'=>1]];$p=org_plan($conflict);$hr=array_values(array_filter($p['departments'],fn($r)=>$r['name']==='Human Resources'))[0];oc($hr['action']==='conflict','Ambiguous HR merged');
$conflict=$empty;$conflict['departments']=[['id'=>1,'name'=>'Digital Marketing','active'=>1],['id'=>2,'name'=>'Marketing','active'=>1]];$p=org_plan($conflict);oc(count($p['conflicts'])===1,'Marketing duplicate conflict missing');
$conflict=$empty;$conflict['departments']=[['id'=>1,'name'=>'Operations','active'=>1],['id'=>2,'name'=>'Quality','active'=>1]];$conflict['designations']=[['id'=>5,'name'=>'Assitant Manager','department_id'=>2,'active'=>1]];$conflict['employees']=[['user_id'=>9,'department_id'=>2,'designation_id'=>5]];$p=org_plan($conflict);oc(str_contains(implode(' ',$p['conflicts']),'another department'),'Unsafe relink not blocked');
$conflict['employees'][0]['department_id']=null;$p=org_plan($conflict);$entry=array_values(array_filter($p['designations'],fn($r)=>$r['name']==='Assistant Manager'))[0];oc($entry['id']===5&&$entry['action']==='rename + link','Unassigned employee prevented safe ID reuse');
$conflict['designations'][]=['id'=>6,'name'=>'Assistant Manager','department_id'=>null,'active'=>1];$p=org_plan($conflict); // unbound candidate is preferred to an unrelated scoped title
oc(count(array_filter($p['designations'],fn($r)=>$r['name']==='Assistant Manager'&&$r['id']===6))===1,'Unbound matching precedence');
$conflict['designations'][]=['id'=>7,'name'=>'Assitant Manager','department_id'=>null,'active'=>1];$p=org_plan($conflict);oc(str_contains(implode(' ',$p['conflicts']),'multiple matching designation'),'Duplicate unbound title not reported');
$inactive=$empty;$inactive['departments']=[['id'=>1,'name'=>'Marketing','active'=>0]];$ip=org_plan($inactive);oc(str_contains(implode(' ',$ip['conflicts']),'inactive'),'Inactive department silently reactivated');
$db->exec("INSERT INTO departments(name,code,description) VALUES('Digital Marketing','ORG-MKT','Preserve description'),('HR','ORG-HR','Existing HR')");
$marketing=(int)$db->query("SELECT id FROM departments WHERE name='Digital Marketing'")->fetchColumn();$hr=(int)$db->query("SELECT id FROM departments WHERE name='HR'")->fetchColumn();$operations=(int)$db->query("SELECT id FROM departments WHERE name='Operations'")->fetchColumn();
$db->prepare("INSERT INTO designations(name,department_id,description) VALUES('Digital Marketing Manager',?,'Keep metadata'),('Assitant Manager',?,''),('Web Developer Inern',NULL,''),('Hr Manager',?,'')")->execute([$marketing,$operations,$hr]);
$manager=(int)$db->query("SELECT id FROM designations WHERE name='Digital Marketing Manager'")->fetchColumn();
$db->prepare("INSERT INTO users(name,username,password_hash,role,active) VALUES('Organization Test','organization.ci',?,'employee',1)")->execute([password_hash('Organization-CI-2026!',PASSWORD_DEFAULT)]);$employee=(int)$db->lastInsertId();
$db->prepare("INSERT INTO employees(user_id,first_name,last_name,department_id,designation_id,employee_code,joining_date) VALUES(?,'Organization','Test',?,?,'ORG-CI-001','2026-01-01')")->execute([$employee,$marketing,$manager]);
$protected=['employees','users','roles','user_roles','role_permissions','hr_attendance','hr_requests','hr_leave_details','hr_leave_days','hr_punches','hr_salary_assignments','hr_payroll_runs','hr_payroll_entries'];$before=[];foreach($protected as $table)$before[$table]=$db->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
$preview=org_plan(org_snapshot($db));ob(fn()=>org_apply($db,$viewer,$preview['fingerprint']),'Permission bypass');ob(fn()=>org_apply($db,$admin,'stale'),'Stale preview accepted');
$result=org_apply($db,$admin,$preview['fingerprint']);
foreach($protected as $table)oc($before[$table]===$db->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC),'Changed protected data '.$table);
oc($result['employee_assignments_preserved'],'Preservation flag missing');oc(!$result['conflicts'],'Unexpected fixture conflict: '.implode(' ',$result['conflicts']));
oc($db->query('SELECT name FROM departments WHERE id='.$marketing)->fetchColumn()==='Marketing','Marketing department ID lost');
oc($db->query('SELECT name FROM designations WHERE id='.$manager)->fetchColumn()==='Marketing Manager','Marketing designation ID lost');
oc($db->query('SELECT description FROM departments WHERE id='.$marketing)->fetchColumn()==='Preserve description','Department metadata lost');
oc($db->query('SELECT description FROM designations WHERE id='.$manager)->fetchColumn()==='Keep metadata','Designation metadata lost');
$ids=[];foreach(org_catalog() as $name=>$titles){$q=$db->prepare('SELECT id FROM departments WHERE name=?');$q->execute([$name]);$d=$q->fetchAll(PDO::FETCH_COLUMN);oc(count($d)===1,'Missing / duplicate department '.$name);$ids[$name]=(int)$d[0];foreach($titles as $title){$q=$db->prepare('SELECT id FROM designations WHERE name=? AND department_id=?');$q->execute([$title,$d[0]]);oc(count($q->fetchAll(PDO::FETCH_COLUMN))===1,'Missing / duplicate designation '.$title);}}
$after=org_snapshot($db);$second=org_apply($db,$admin,org_fingerprint($after));oc($second['changed']===0,'Second application modified catalog');oc(org_snapshot($db)===$after,'Second application changed IDs or timestamps');
ob(fn()=>org_duplicate_check($db,'departments','Digital Marketing',0),'Alias duplicate department accepted');
ob(fn()=>org_duplicate_check($db,'designations','Digital marketing manager',0,$marketing),'Alias duplicate designation accepted');
$q=$db->prepare("SELECT * FROM designations WHERE department_id=? AND name='Team Lead – BCL'");$q->execute([$ids['BCL']]);$bcl=$q->fetch(PDO::FETCH_ASSOC);oc(org_designation_allowed($bcl,$ids['BCL'],null)&&!org_designation_allowed($bcl,$operations,null),'Scoped designation validation');
$global=['id'=>999,'department_id'=>null,'active'=>1];oc(!org_designation_allowed($global,$operations,null),'Global designation offered for new employee');
oc(org_designation_allowed($global,$operations,['designation_id'=>999,'department_id'=>$operations]),'Existing unbound assignment not preserved');
oc(!org_designation_allowed($global,$ids['BCL'],['designation_id'=>999,'department_id'=>$operations]),'Old global assignment leaked to other department');
$role=(int)$db->query("SELECT id FROM roles WHERE slug='employee'")->fetchColumn();
$input=['first_name'=>'New','last_name'=>'BCL Employee','username'=>'bcl.organization.ci','department_id'=>$ids['BCL'],'designation_id'=>$bcl['id'],'role_id'=>$role,'joining_date'=>'2026-01-01','employment_type'=>'Full Time','employment_status'=>'Active'];
$new=save_employee($db,$admin,$input,[]);oc(foundation_employee($db,$new)['department_name']==='BCL','Employee create did not use BCL');
ob(fn()=>save_employee($db,$admin,array_merge($input,['department_id'=>$operations,'username'=>'wrong.organization.ci']),[]),'Cross-department employee create accepted');
$edit=foundation_employee($db,$new);save_employee($db,$admin,array_merge($input,['id'=>$new,'version'=>$edit['version'],'mobile'=>'123']),[]);oc(foundation_employee($db,$new)['designation_id']==$bcl['id'],'Employee edit changed designation');
$designationCount=(int)$db->query('SELECT COUNT(*) FROM designations')->fetchColumn();
$importRows=[['name'=>'Organization Import','designation'=>'Digital marketing manager','phone'=>'','birth_date'=>'','blood_group'=>'','emergency_contact'=>'','source_status'=>'Test']];
[$created,$skipped]=import_foundation($db,$admin,$importRows);oc($created===1&&$skipped===0,'Import failed');
oc((int)$db->query('SELECT COUNT(*) FROM designations')->fetchColumn()===$designationCount,'Import duplicated a normalized designation');
$imported=$db->query("SELECT e.department_id,e.designation_id FROM employees e JOIN users u ON u.id=e.user_id WHERE u.username='organization.import'")->fetch(PDO::FETCH_ASSOC);oc($imported['department_id']===null&&(int)$imported['designation_id']===$manager,'Import invented department or lost designation ID');
file_put_contents('/tmp/organization-fixture.json',json_encode(['employee'=>$employee,'department'=>$marketing,'designation'=>$manager,'bcl'=>$ids['BCL'],'bcl_designation'=>$bcl['id'],'report'=>$result['report_id']]));
echo "PASS: 16 departments, 57 designations, semantic aliases, conflicts, repeatability, original IDs, metadata, complete employee/role/attendance/payroll preservation, and scoped employee create/edit.\n";
