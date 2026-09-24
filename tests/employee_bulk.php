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
$archive=['employee_ids'=>$ids,'versions'=>array_fill_keys($ids,2),'operation'=>'delete','confirmed'=>'1'];
foundation_bulk_employees($db,$admin,$archive);
foreach($ids as $id){$r=$db->query('SELECT u.active,u.session_version,u.password_hash,e.deleted_at FROM users u JOIN employees e ON e.user_id=u.id WHERE u.id='.$id)->fetch(PDO::FETCH_ASSOC);bulk_check($r&&!$r['active']&&$r['deleted_at']&&(int)$r['session_version']===1&&password_verify('Ci-password-123',$r['password_hash']),'Archive must retain identity/password and revoke login');}
bulk_denied(fn()=>foundation_bulk_employees($db,$admin,$archive));
echo "PASS: Super Admin-only bulk actions, confirmation, stale-selection rollback, assignment, archive and session revocation.\n";
