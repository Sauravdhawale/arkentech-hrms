<?php
// Included by payroll.php only against its explicitly disposable CI database.
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require_once dirname(__DIR__).'/includes/payroll/setup-date.php';
(function()use($db,$admin,$viewer){
 $beforeEntries=$db->query('SELECT * FROM hr_payroll_entries ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
 $beforeAttendance=$db->query('SELECT * FROM hr_attendance ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
 $beforeSalaries=$db->query('SELECT * FROM hr_salary_assignments ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
 $component=pay_save_config($db,$admin,['module'=>'pay_component','title'=>'Date test basic','code'=>'DATE_BASIC','effective_from'=>'2024-10-01','active'=>1,'category'=>'Earning','calculation'=>'Percentage','basis'=>'GROSS','value'=>'100','include_gross'=>1,'include_ctc'=>1,'visible'=>1]);
 $structure=pay_save_config($db,$admin,['module'=>'pay_structure','title'=>'Date test structure','code'=>'DATE_STRUCTURE','effective_from'=>'2024-10-01','active'=>1,'component_ids'=>[$component]]);
 $settings=pay_save_config($db,$admin,array_merge(pay_setting_defaults(),['module'=>'pay_settings','title'=>'Date test settings','effective_from'=>'2024-10-01','active'=>1]));
 $design=pay_save_config($db,$admin,array_merge(pay_slip_defaults(),['module'=>'pay_payslip','title'=>'Date test design','effective_from'=>'2024-10-01','active'=>1]));
 $db->prepare("INSERT INTO users(name,username,password_hash,role,active) VALUES('Date test','payroll.date.ci',?,'employee',1)")->execute([password_hash('Date-test-CI-only!',PASSWORD_DEFAULT)]);$emp=(int)$db->lastInsertId();
 $db->prepare("INSERT INTO employees(user_id,first_name,employee_code,joining_date,employment_status) VALUES(?,'Date','DATE-CI','2024-09-01','Active')")->execute([$emp]);
 $in=['employee_id'=>$emp,'structure_id'=>$structure,'salary_basis'=>'Monthly Gross','salary_amount'=>'35000','effective_from'=>'2024-10-01','reason'=>'Initial','confirm'=>1,'previous_id'=>0];
 $in['preview_hash']=hash('sha256',pay_json(pay_assignment_preview($db,$in)));$sid=pay_assign($db,$admin,$in);
 $in=array_merge($in,['salary_amount'=>'40000','effective_from'=>'2024-11-01','previous_id'=>$sid,'reason'=>'Increment']);$in['preview_hash']=hash('sha256',pay_json(pay_assignment_preview($db,$in)));$later=pay_assign($db,$admin,$in);
 $laterBefore=$db->query('SELECT * FROM hr_salary_assignments WHERE id='.$later)->fetch(PDO::FETCH_ASSOC);
 $get=fn()=>pay_setup_date_plan($db,$admin,$emp,'2024-09-01','Correct initial setup');
 pd(fn()=>pay_setup_date_plan($db,$viewer,$emp,'2024-09-01','Not allowed'),'Date correction permission bypass');
 pd(fn()=>pay_setup_date_plan($db,$admin,$emp,'2024-09-02','Wrong day'),'Non-month start accepted');
 pd(fn()=>pay_setup_date_plan($db,$admin,$emp,'2024-09-01',''),'Missing correction reason accepted');
 $plan=$get();pc(count($plan['records'])===4&&$plan['salary_from']==='2024-09-01','Date dependencies incomplete');
 $apply=['employee_id'=>$emp,'effective_from'=>'2024-09-01','reason'=>'Correct initial setup','fingerprint'=>$plan['fingerprint'],'confirm'=>1];
 pd(fn()=>pay_setup_date_apply($db,$admin,array_merge($apply,['confirm'=>0])),'Unreviewed correction accepted');
 $db->prepare('UPDATE hr_records SET version=version+1 WHERE id=?')->execute([$component]);
 pd(fn()=>pay_setup_date_apply($db,$admin,$apply),'Stale date correction accepted');
 pc($db->query('SELECT effective_from FROM hr_salary_assignments WHERE id='.$sid)->fetchColumn()==='2024-10-01','Failed correction partially wrote salary');
 // A payroll already created in the added month blocks this initial-setup tool.
 $db->prepare("INSERT INTO hr_payroll_runs(month,status,settings_snapshot,created_by) VALUES('2024-09','Draft','{}',1)")->execute([]);$block=(int)$db->lastInsertId();
 pd($get,'Existing payroll period overwritten');$db->prepare('DELETE FROM hr_payroll_runs WHERE id=?')->execute([$block]);
 $apply['fingerprint']=$get()['fingerprint'];$audit=pay_setup_date_apply($db,$admin,$apply);
 $saved=$db->query('SELECT * FROM hr_salary_assignments WHERE id='.$sid)->fetch(PDO::FETCH_ASSOC);
 pc($saved['effective_from']==='2024-09-01'&&$saved['effective_to']==='2024-10-31'&&(int)$saved['monthly_gross']===3500000,'Initial salary changed incorrectly');
 pc($laterBefore===$db->query('SELECT * FROM hr_salary_assignments WHERE id='.$later)->fetch(PDO::FETCH_ASSOC),'Later salary revision changed');
 foreach([$component,$structure,$settings,$design] as $id)pc(json_decode($db->query('SELECT data FROM hr_records WHERE id='.$id)->fetchColumn(),true)['effective_from']==='2024-09-01','Dependent setup date not corrected');
 $trail=chr_record($db,'payroll_setup_date',$audit)['values'];pc($trail['salary']['effective_from']==='2024-10-01'&&$trail['salary_after']['effective_from']==='2024-09-01','Original salary date not audited');
 pd(fn()=>pay_setup_date_apply($db,$admin,$apply),'Repeated correction accepted');
 pc($beforeEntries===$db->query('SELECT * FROM hr_payroll_entries ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Date correction changed payslips');
 pc($beforeAttendance===$db->query('SELECT * FROM hr_attendance ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Date correction changed attendance');
 $db->prepare('DELETE FROM hr_salary_assignments WHERE employee_id=?')->execute([$emp]);
 pc($beforeSalaries===$db->query('SELECT * FROM hr_salary_assignments ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Other salaries changed');
 foreach([$component,$structure,$settings,$design,$audit] as $id)$db->prepare('DELETE FROM hr_records WHERE id=?')->execute([$id]);
 $db->prepare('DELETE FROM employees WHERE user_id=?')->execute([$emp]);$db->prepare('DELETE FROM users WHERE id=?')->execute([$emp]);
 echo "PASS: initial payroll date correction, dependencies, permissions, stale previews, repeat safety, audited history and preservation.\n";
})();
