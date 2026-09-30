<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/foundation/core.php';require dirname(__DIR__).'/includes/foundation/employees.php';
$db=db();$actor=['id'=>1,'role'=>'super_admin'];
function csv_check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$db->exec("INSERT INTO departments(name) VALUES('CSV Test Department')");$dep=(int)$db->lastInsertId();$db->prepare('INSERT INTO designations(name,department_id) VALUES(?,?)')->execute(['CSV Test Designation',$dep]);
$file=tempnam(sys_get_temp_dir(),'employees');$header=file_get_contents(dirname(__DIR__).'/assets/employee-template.csv');
file_put_contents($file,$header."Csv,Person,CSVTEST001,csvtest@example.test,csv.test,CSV Test Department,CSV Test Designation,2026-09-01,1234567890\n");
$rows=employee_csv_read($db,$file);csv_check(count($rows)===1&&!$rows[0]['_skip'],'Preview failed');$result=employee_csv_commit($db,$actor,$rows);csv_check($result===[1,0],'Import failed');csv_check(employee_csv_commit($db,$actor,$rows)===[0,1],'Duplicate not skipped');
$q=$db->query("SELECT e.department_id,e.joining_date,u.must_change_password FROM employees e JOIN users u ON e.user_id=u.id WHERE e.employee_code='CSVTEST001'")->fetch(PDO::FETCH_ASSOC);csv_check((int)$q['department_id']===$dep&&$q['joining_date']==='2026-09-01'&&(int)$q['must_change_password']===1,'Fields lost');
$bad=$rows[0];$bad['employee_code']='CSVTEST002';$bad['email']='csvtest2@example.test';$bad['username']='csv.test2';$bad['_skip']=false;$invalid=$bad;$invalid['employee_code']='CSVTEST003';$invalid['email']='csvtest3@example.test';$invalid['username']='csv.test3';$invalid['designation_id']=0;
try{employee_csv_commit($db,$actor,[$bad,$invalid]);throw new RuntimeException('Invalid designation accepted');}catch(InvalidArgumentException $e){}
csv_check(!$db->query("SELECT id FROM users WHERE username='csv.test2'")->fetchColumn(),'Partial import persisted');
file_put_contents($file,$header."Csv,Wrong,,,,Missing Department,Wrong,2026-09-01,\n");try{employee_csv_read($db,$file);throw new RuntimeException('Invalid department accepted');}catch(InvalidArgumentException $e){}unlink($file);
echo "PASS: employee CSV parsing, assignment, duplicate protection and atomic rollback.\n";
