<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/foundation/core.php';require dirname(__DIR__).'/includes/foundation/employees.php';
function bulk_check($ok,$label){if(!$ok)throw new RuntimeException($label);}
function bulk_denied($fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Forbidden bulk change accepted');}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];$ids=[];
foreach(['One','Two'] as $name){$db->prepare("INSERT INTO users(name,username,password_hash,role,active) VALUES(?,?,?,'employee',1)")->execute(['Bulk '.$name,'bulk.ci.'.strtolower($name),password_hash('Ci-password-123',PASSWORD_DEFAULT)]);$id=(int)$db->lastInsertId();$ids[]=$id;$db->prepare('INSERT INTO employees(user_id,first_name,last_name) VALUES(?,?,?)')->execute([$id,'Bulk',$name]);}
$db->exec("INSERT INTO departments(name) VALUES('Bulk CI Department')");$department=(int)$db->lastInsertId();$db->exec("INSERT INTO designations(name) VALUES('Bulk CI Designation')");$designation=(int)$db->lastInsertId();
$input=['employee_ids'=>$ids,'versions'=>array_fill_keys($ids,1),'operation'=>'assign','confirmed'=>'1','bulk_department'=>$department,'bulk_designation'=>$designation];
bulk_denied(fn()=>foundation_bulk_employees($db,['id'=>2,'role'=>'employee'],$input));
bulk_denied(fn()=>foundation_bulk_employees($db,$admin,array_merge($input,['confirmed'=>'0'])));
bulk_denied(fn()=>foundation_bulk_employees($db,$admin,array_merge($input,['versions'=>[$ids[0]=>1,$ids[1]=>99]])));
bulk_check((int)$db->query('SELECT version FROM employees WHERE user_id='.$ids[0])->fetchColumn()===1,'Partial bulk update');
foundation_bulk_employees($db,$admin,$input);
foreach($ids as $id)bulk_check((int)$db->query('SELECT department_id FROM employees WHERE user_id='.$id)->fetchColumn()===$department,'Assignment missing');
bulk_denied(fn()=>foundation_bulk_employees($db,$admin,$input));
$archive=['employee_ids'=>$ids,'versions'=>array_fill_keys($ids,2),'operation'=>'delete','confirmed'=>'1','delete_mode'=>'permanent'];
bulk_denied(fn()=>foundation_bulk_employees($db,$admin,array_merge($archive,['delete_mode'=>'archive'])));
$db->prepare("INSERT INTO hr_attendance(employee_id,attendance_date,check_in,shift_snapshot,status,notes,updated_by) VALUES(?,'2026-09-30','2026-09-30 09:00:00','{}','Present','',1)")->execute([$ids[0]]);
$db->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('mapping',?,'Delete test','Active','{}',1)")->execute([$ids[0]]);
$mappingId=(int)$db->lastInsertId();
$db->prepare("INSERT INTO hr_announcements(title,body,author_id) VALUES('Shared deletion blocker','Keep shared content',?)")->execute([$ids[1]]);
$blocker=(int)$db->lastInsertId();
try{foundation_bulk_employees($db,$admin,$archive);throw new RuntimeException('Shared relationship unexpectedly deleted');}catch(PDOException $e){}
bulk_check((int)$db->query('SELECT COUNT(*) FROM employees WHERE user_id IN ('.implode(',',$ids).')')->fetchColumn()===2,'Failed bulk delete was not atomic');
bulk_check((int)$db->query('SELECT COUNT(*) FROM hr_attendance WHERE employee_id='.$ids[0])->fetchColumn()===1,'Failed delete lost attendance');
$db->prepare('DELETE FROM hr_announcements WHERE id=?')->execute([$blocker]);

foundation_bulk_employees($db,$admin,$archive);
foreach($ids as $id){bulk_check(!$db->query('SELECT id FROM users WHERE id='.$id)->fetchColumn(),'Account still exists');bulk_check(!$db->query('SELECT user_id FROM employees WHERE user_id='.$id)->fetchColumn(),'Profile still exists');}
bulk_check(!$db->query('SELECT id FROM hr_records WHERE id='.$mappingId)->fetchColumn(),'Mapping retained');
bulk_check(!(int)$db->query('SELECT COUNT(*) FROM hr_attendance WHERE employee_id='.$ids[0])->fetchColumn(),'Attendance retained');
// Freed usernames must be reusable with fresh internal IDs.
$db->exec("INSERT INTO users(name,username,password_hash,role) VALUES('Reuse','bulk.ci.one','unused','employee')");
bulk_denied(fn()=>foundation_bulk_employees($db,$admin,$archive));
echo "PASS: Super Admin-only bulk actions, confirmation, stale-selection rollback, assignment, permanent deletion and username reuse.\n";
