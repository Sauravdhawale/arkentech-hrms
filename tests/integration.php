<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Only the disposable CI database is supported.');
require dirname(__DIR__).'/auth.php';
$pdo=db();
foreach(['schema.sql','002-workspaces.sql','003-hr-suite.sql'] as $file){foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/'.$file)) as $sql)if(trim($sql)!=='')$pdo->exec($sql);}
require dirname(__DIR__).'/includes/employee-import.php';install_login_columns($pdo);install_login_columns($pdo);
$insert=$pdo->prepare('INSERT INTO users(name,email,username,password_hash,role) VALUES(?,?,?,?,?)');
foreach([['Test Admin','admin@example.test','test.admin','super_admin'],['Test One',null,'test.one','employee'],['Test Two',null,'test.two','employee']] as [$name,$email,$username,$role])$insert->execute([$name,$email,$username,password_hash('User@123',PASSWORD_DEFAULT),$role]);
$user=['id'=>1,'role'=>'super_admin'];$admin=true;$page='overview';$_SERVER['REQUEST_METHOD']='GET';
require dirname(__DIR__).'/includes/suite.php';require dirname(__DIR__).'/includes/leave-balances.php';
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$record=$pdo->prepare('INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES(?,?,?,?,?,1)');
$record->execute(['employment',2,'Private One','Active','{}']);$record->execute(['employment',3,'Private Two','Active','{}']);
check(count(suite_rows($pdo,'employment',['id'=>2,'role'=>'employee'],$suite['employment']))===1,'Employee isolation failed');
check(count(suite_rows($pdo,'devices',['id'=>2,'role'=>'employee'],$suite['devices']))===0,'Admin scope failed');
$pay=['basic'=>'10000','hra'=>'4000','other'=>'500','overtime'=>'100','incentive'=>'200','deductions'=>'700','lwp'=>'500','advance'=>'1000'];check(suite_net($pay)===12600.0,'Net pay mismatch');
$record->execute(['payroll',2,'Hidden draft','Draft',json_encode($pay)]);$record->execute(['payroll',2,'Visible payslip','Published',json_encode($pay)]);
check(count(suite_rows($pdo,'payroll',['id'=>2,'role'=>'employee'],$suite['payroll']))===1,'Draft visibility failed');
check(count(suite_rows($pdo,'payroll',['id'=>3,'role'=>'employee'],$suite['payroll']))===0,'Payslip isolation failed');
$bad=false;try{suite_data(['','','',false,[],['date'=>['Date','date',true]]],['date'=>'2026-02-30']);}catch(InvalidArgumentException $e){$bad=true;}check($bad,'Invalid calendar date accepted');
$bad=false;try{suite_data(['','','',false,[],['amount'=>['Amount','number',true]]],['amount'=>'-1']);}catch(InvalidArgumentException $e){$bad=true;}check($bad,'Negative amount accepted');
$record->execute(['balances',2,'Entitlement','Active',json_encode(['year'=>'2026','CL'=>'10','SL'=>'5','PL'=>'10'])]);
$pdo->exec("INSERT INTO hr_requests(user_id,kind,category,subject,details,start_date,end_date,status) VALUES(2,'leave','CL','Test','Test','2026-01-01','2026-01-03','Approved')");
check(leave_used($pdo,2,'CL',2026)===3,'Calendar-day leave count failed');check(leave_entitlement($pdo,2,'CL',2026)===10.0,'Entitlement failed');
$pdo->beginTransaction();$bad=false;try{validate_leave_approval($pdo,['id'=>999,'user_id'=>2,'category'=>'CL','start_date'=>'2026-01-03','end_date'=>'2026-01-04']);}catch(InvalidArgumentException $e){$bad=true;}$pdo->rollBack();check($bad,'Overlapping leave accepted');
$tmp=tempnam(sys_get_temp_dir(),'hrms-test-');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);
$zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Name</t></is></c><c r="B1" t="inlineStr"><is><t>Designation</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Example Person</t></is></c><c r="B2" t="inlineStr"><is><t>Analyst</t></is></c></row><row r="3"><c r="A3" t="inlineStr"><is><t>Example Person</t></is></c></row></sheetData></worksheet>');$zip->close();$rows=import_xlsx($tmp);unlink($tmp);check(count($rows)===1&&$rows[0]['username']==='example.person','XLSX deduplication or username failed');
echo "PASS: schema, repeat migration, row isolation, published payslip access, payroll arithmetic, input validation, leave balances and XLSX import.\n";
