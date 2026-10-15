<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/maintenance/payroll-cleanup.php';$db=db();
// Exercise both deployed payroll schemas, including optional foreign-key dependencies.
$db->exec(file_get_contents(dirname(__DIR__).'/database/007-payroll.sql'));
$db->exec(file_get_contents(dirname(__DIR__).'/database/010-payroll-workflow.sql'));
// Populate the FK chain so cleanup is tested against actual salary and payslip rows.
$actor=(int)$db->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();
$owner=(int)$db->query("SELECT user_id FROM employees ORDER BY user_id LIMIT 1")->fetchColumn();
$db->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('pay_structure',?,'Retirement fixture','Active','{}',?)")->execute([$owner,$actor]);$structure=(int)$db->lastInsertId();
$db->prepare("INSERT INTO hr_salary_assignments(employee_id,structure_id,effective_from,monthly_gross,annual_ctc,snapshot,reason,notes,created_by) VALUES(?,?,'2026-01-01',100000,1200000,'{}','Test','',?)")->execute([$owner,$structure,$actor]);
$db->prepare("INSERT INTO hr_payroll_runs(month,settings_snapshot,created_by) VALUES('2099-01','{}',?)")->execute([$actor]);$run=(int)$db->lastInsertId();
$db->prepare("INSERT INTO hr_payroll_entries(run_id,employee_id,snapshot,exceptions) VALUES(?,?,'{}','')")->execute([$run,$owner]);$entry=(int)$db->lastInsertId();
$db->prepare("INSERT INTO hr_payroll_adjustments(entry_id,type,component,reason,created_by) VALUES(?,'earning','Test','Test',?)")->execute([$entry,$actor]);
$db->prepare("INSERT INTO hr_payroll_events(run_id,entry_id,actor_id,action,reason) VALUES(?,?,?,'Test','Test')")->execute([$run,$entry,$actor]);
$db->prepare("INSERT INTO hr_payslip_templates(template_code,name,version,effective_from,builder_json,html_template,css_template,variables_json,created_by) VALUES('retire','Test',1,'2099-01-01','{}','private salary','','{}',?)")->execute([$actor]);$template=(int)$db->lastInsertId();
$db->prepare("INSERT INTO hr_payslip_documents(entry_id,template_id,template_snapshot,rendered_html,generated_by,generated_at) VALUES(?,?,'{}','private payslip',?,NOW())")->execute([$entry,$template,$actor]);
$db->prepare("INSERT INTO hr_payroll_overrides(run_id,entry_id,field_name,old_value,new_value,reason,created_by) VALUES(?,?,'net','1','2','Test',?)")->execute([$run,$entry,$actor]);
$db->prepare("INSERT INTO hr_payroll_exceptions(run_id,entry_id,employee_id,code,details) VALUES(?,?,?,'Test','Test')")->execute([$run,$entry,$owner]);
$db->prepare("INSERT INTO hr_payroll_attendance_snapshots(run_id,employee_id,payroll_month,snapshot,source_hash,captured_by,captured_at) VALUES(?,?,'2099-01','{}',?, ?,NOW())")->execute([$run,$owner,str_repeat('a',64),$actor]);
$protected=['users','employees','departments','designations','hr_attendance','hr_requests','hr_punches'];$before=[];foreach($protected as $table)$before[$table]=$db->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);
$unrelated=$db->query("SELECT * FROM hr_records WHERE module NOT IN ('salary','payroll','contracts','advances','components','pay_component','pay_structure','pay_statutory','pay_settings','pay_payslip','payroll_archive') AND NOT (module='inbox_state' AND title LIKE 'payslip:%') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$preview=payroll_cleanup($db);if($preview['applied']||($preview['rows']['payroll_hr_records']??0)<1)throw new RuntimeException('Expected legacy payroll fixtures and dry-run.');
$result=payroll_cleanup($db,true);if(!$result['applied']||array_sum($result['remaining'])!==0)throw new RuntimeException('Payroll cleanup failed.');
foreach($protected as $table)if($before[$table]!==$db->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC))throw new RuntimeException('Changed protected data: '.$table);
if($unrelated!==$db->query('SELECT * FROM hr_records ORDER BY id')->fetchAll(PDO::FETCH_ASSOC))throw new RuntimeException('Unrelated records changed.');
if(payroll_cleanup($db,true)['deleted_rows']!==0)throw new RuntimeException('Cleanup not idempotent.');
echo "PASS: payroll cleanup scope, dry-run, repeat safety and employee/attendance/leave preservation.\n";
