<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/includes/payroll/service.php';
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function pc($ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function pd(callable $fn,string $why):void{try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($why);}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];$viewer=['id'=>3,'role'=>'employee'];
$tables=['users','employees','hr_attendance','hr_records','hr_requests','hr_leave_details','hr_leave_days','hr_files','hr_punches'];$before=[];foreach($tables as $t)$before[$t]=$db->query('SELECT * FROM '.$t)->fetchAll(PDO::FETCH_ASSOC);
pay_install($db,$admin);pay_install($db,$admin);
foreach($tables as $t)pc($before[$t]===$db->query('SELECT * FROM '.$t)->fetchAll(PDO::FETCH_ASSOC),'Migration changed existing '.$t);
pd(fn()=>pay_install($db,$viewer),'Migration permission bypass');
pc(pay_money('123.45')===12345,'Minor units');
pd(fn()=>pay_money('-1'),'Negative input');pd(fn()=>pay_expression('PHPINFO()',[]),'Executable expression accepted');
pc(pay_expression('(GROSS * 0.4) + 10',['GROSS'=>1000])===410.0,'Formula arithmetic');
$base=['module'=>'pay_component','title'=>'Basic','code'=>'BASIC','effective_from'=>'2025-01-01','active'=>1,'category'=>'Earning','calculation'=>'Percentage','section'=>'Fixed','basis'=>'GROSS','value'=>'40','include_gross'=>1,'include_ctc'=>1,'visible'=>1];
$basic=pay_save_config($db,$admin,$base);
$hra=pay_save_config($db,$admin,array_merge($base,['title'=>'HRA','code'=>'HRA','basis'=>'BASIC','value'=>'50']));
$bonus=pay_save_config($db,$admin,array_merge($base,['title'=>'Bonus','code'=>'BONUS','calculation'=>'Fixed','value'=>'1000']));
$balance=pay_save_config($db,$admin,array_merge($base,['title'=>'Special Allowance','code'=>'SPECIAL','calculation'=>'Balance','value'=>'0']));
$structure=pay_save_config($db,$admin,['module'=>'pay_structure','title'=>'CI Salary','code'=>'CI_STRUCTURE','effective_from'=>'2025-01-01','active'=>1,'component_ids'=>[$basic,$hra,$bonus,$balance],'variable_enabled'=>1,'variable_cap'=>10]);
$st=chr_record($db,'pay_structure',$structure)['values'];$breakup=pay_breakup($st,3000000);$amounts=array_column($breakup['components'],'amount','code');
pc($amounts['BASIC']===1200000&&$amounts['HRA']===600000&&$amounts['SPECIAL']===1100000,'Percentage and balance');pc($breakup['net']===3000000,'Normal monthly net');
pc(pay_from_ctc($st,36000000)['gross']===3000000,'Annual CTC preview');
$invalid=$st;$invalid['components'][]=array_merge($st['components'][3],['code'=>'OTHER_BALANCE']);pd(fn()=>pay_breakup($invalid,3000000),'Multiple balance accepted');
$invalid=$st;$invalid['components'][0]['basis']='HRA';pd(fn()=>pay_breakup($invalid,3000000),'Circular dependency accepted');
$rule=['name'=>'CI contribution','code'=>'CI_PF','basis'=>'BASIC','method'=>'Percentage','employee_value'=>10,'employer_value'=>12,'wage_cap'=>1000000,'eligibility_limit'=>0];
$withRule=pay_breakup($st+['statutory'=>[$rule]],3000000);pc($withRule['deductions']===100000&&$withRule['employer']===120000,'Statutory capped basis');pc($withRule['annual_ctc']===37440000,'Employer cost in CTC');
$settings=pay_setting_defaults();$settings['ot_enabled']=true;$settings['ot_rate']='100';$settings['variable_enabled']=true;
$e=['user_id'=>999,'name'=>'Test employee','employee_code'=>'CI-PAY','department'=>'CI','department_name'=>'CI'];
$days=[];for($day=1;$day<=30;$day++)$days[]=['employee_id'=>999,'name'=>'Test employee','employee_code'=>'CI-PAY','department'=>'CI','date'=>sprintf('2025-04-%02d',$day),'status'=>'Present','note'=>'','working_days'=>1,'present'=>1,'absent'=>0,'leave'=>0,'paid_leave'=>0,'unpaid_leave'=>0,'holidays'=>0,'week_offs'=>0,'late_count'=>0,'half_days'=>0,'working_minutes'=>480,'overtime_minutes'=>$day===1?120:0];
$salary=['structure'=>$st,'manual'=>[],'ot_rate'=>0,'bank_last4'=>'1234','pan_last4'=>'4567'];
$salaries=[['id'=>1,'effective_from'=>'2025-01-01','effective_to'=>null,'monthly_gross'=>3000000,'snapshot'=>pay_json($salary)]];
$snap=pay_calculate_employee($e,$days,$salaries,$settings,[],[],30);pc($snap['gross']===3000000&&$snap['net']===3000000&&!$snap['exceptions'],'Normal salary calculation');
$days[1]['unpaid_leave']=1;$days[1]['present']=0;$days[1]['leave']=1;$days[1]['status']='Leave';
$adjust=[['id'=>1,'type'=>'Overtime','units'=>2,'amount'=>0,'approved_by'=>1,'component'=>'Approved OT'],['id'=>2,'type'=>'Variable','units'=>0,'amount'=>100000,'approved_by'=>null,'component'=>'Performance pay']];
$snap=pay_calculate_employee($e,$days,$salaries,$settings,[],$adjust,30);
pc($snap['lop']===100000&&$snap['gross']===3120000&&$snap['deductions']===100000&&$snap['net']===3020000,'LOP / overtime / variable totals');
pc($snap['gross']-$snap['deductions']===$snap['net'],'Payslip reconciliation');
$adjust[0]['approved_by']=null;$pending=pay_calculate_employee($e,$days,$salaries,$settings,[],$adjust,30);pc(in_array('Overtime approval pending',$pending['exceptions'],true)&&$pending['ot_hours']===0.0,'Unapproved overtime paid');
$missing=pay_calculate_employee($e,$days,[],$settings,[],[],30);pc(str_contains(implode(' ',$missing['exceptions']),'Missing Salary Configuration'),'Missing salary not flagged');
$adjust[0]['approved_by']=1;$adjust[1]['amount']=400000;$capped=pay_calculate_employee($e,$days,$salaries,$settings,[],$adjust,30);pc(in_array('Variable pay exceeds the configured salary-structure limit',$capped['exceptions'],true),'Variable cap bypass');
$settings['proration']='Working Days';$working=pay_calculate_employee($e,$days,$salaries,$settings,[],[],30);pc($working['lop']===100000,'Working day LOP');
$days[0]['status']='Not started';$partial=pay_calculate_employee($e,$days,$salaries,$settings,[],[],30);pc($partial['gross']===2900000,'Joining-date proration');
$settings['proration']='Calendar Days';
$days[0]['status']='Present';$days[0]['note']='';$days[2]['status']='Absent';$days[2]['absent']=1;$days[2]['present']=0;$days[2]['note']='Admin approved absence';
$manualAbsence=pay_calculate_employee($e,$days,$salaries,$settings,[],[],30);pc(!$manualAbsence['exceptions'],'Ordinary absence note incorrectly blocks payroll');
$conf=pay_setting_defaults();$conf=array_merge($conf,['module'=>'pay_settings','title'=>'CI payroll rules','effective_from'=>'2025-01-01','active'=>1]);pay_save_config($db,$admin,$conf);
$db->prepare("INSERT INTO users(name,username,password_hash,role,active) VALUES(?,?,?,'employee',1)")->execute(['Payroll Test','payroll.ci',password_hash('Payroll-CI-2026!',PASSWORD_DEFAULT)]);$employee=(int)$db->lastInsertId();
$db->prepare("INSERT INTO employees(user_id,first_name,last_name,employee_code,joining_date,employment_status) VALUES(?,?,?,?,?,'Active')")->execute([$employee,'Payroll','Test','PAY-CI-001','2025-01-01']);
$in=['employee_id'=>$employee,'structure_id'=>$structure,'salary_basis'=>'Monthly Gross','salary_amount'=>'30000','effective_from'=>'2025-01-01','reason'=>'Initial salary','confirm'=>1,'previous_id'=>0];
$preview=pay_assignment_preview($db,$in);$in['preview_hash']=hash('sha256',pay_json($preview));$assignment=pay_assign($db,$admin,$in);
pd(fn()=>pay_assign($db,$admin,$in),'Duplicate salary revision');pd(fn()=>pay_assign($db,$viewer,$in),'Salary permission bypass');
$shift=chr_save_config($db,$admin,'shifts',['title'=>'Payroll CI Shift','code'=>'PAY-CI','start'=>'09:00','end'=>'17:00','required_hours'=>8,'half_day_hours'=>4,'grace'=>0,'early_grace'=>0,'break_minutes'=>0,'overtime_rule'=>'After both','week_off'=>[7],'active'=>1]);
chr_save_config($db,$admin,'roster',['title'=>'Payroll CI roster','employee_id'=>$employee,'shift_id'=>$shift,'from'=>'2025-01-01','active'=>1]);
$run=pay_create_run($db,$admin,'2025-02',[$employee]);pd(fn()=>pay_add_employees($db,$admin,['run_id'=>$run,'version'=>1,'employee_id'=>$employee]),'Duplicate run membership accepted');pd(fn()=>pay_create_run($db,$admin,'2025-02',[$employee]),'Duplicate month');
pd(fn()=>pay_create_run($db,$viewer,'2025-03',[$employee]),'Process permission bypass');
$ri=['run_id'=>$run,'version'=>1];pay_calculate_run($db,$admin,$ri);pd(fn()=>pay_calculate_run($db,$admin,$ri),'Stale run write');
$entry=$db->query('SELECT * FROM hr_payroll_entries WHERE run_id='.$run)->fetch(PDO::FETCH_ASSOC);pc($entry['exceptions']==='','Existing attendance integration exceptions: '.$entry['exceptions']);
pc((int)$entry['gross']===3000000&&(int)$entry['deductions']>0,'Attendance absence not integrated');
$transition=function($action)use($db,$admin,$run){$r=pay_run($db,$run);pay_transition($db,$admin,['run_id'=>$run,'version'=>$r['version'],'transition'=>$action,'reason'=>'CI workflow verification','confirm'=>1]);};
pd(fn()=>pay_transition($db,$viewer,['run_id'=>$run,'version'=>2,'transition'=>'review','reason'=>'Forbidden','confirm'=>1]),'Review permission bypass');
$transition('review');$transition('approve');$transition('finalize');$transition('publish');
$locked=$db->query('SELECT * FROM hr_payroll_entries WHERE run_id='.$run)->fetch(PDO::FETCH_ASSOC);pc($locked['published_at']&&$locked['payslip_number'],'Payslip publication missing');
$r=pay_run($db,$run);pd(fn()=>pay_calculate_run($db,$admin,['run_id'=>$run,'version'=>$r['version']]),'Finalized recalculation allowed');
pd(fn()=>pay_adjust($db,$admin,['run_id'=>$run,'version'=>$r['version'],'entry_id'=>$locked['id'],'type'=>'Earning','amount'=>'1','reason'=>'Forbidden']),'Finalized adjustment allowed');
pd(fn()=>pay_transition($db,$admin,['run_id'=>$run,'version'=>$r['version'],'transition'=>'draft','confirm'=>1,'reason'=>'Forbidden']),'Finalized reopened');
$in=array_merge($in,['effective_from'=>'2025-03-01','salary_amount'=>'40000','previous_id'=>$assignment,'reason'=>'Salary revision']);$in['preview_hash']=hash('sha256',pay_json(pay_assignment_preview($db,$in)));pay_assign($db,$admin,$in);
pc($db->query('SELECT snapshot FROM hr_payroll_entries WHERE id='.$locked['id'])->fetchColumn()===$locked['snapshot'],'Historical payslip changed after salary revision');
pc($db->query('SELECT effective_to FROM hr_salary_assignments WHERE id='.$assignment)->fetchColumn()==='2025-02-28','Salary period overlap');
$transition('paid');pc(pay_run($db,$run)['status']==='Paid','Payment state');
$oldRunSnapshot=pay_run($db,$run)['settings_snapshot'];$settingRow=chr_rows($db,'pay_settings')[0];$optional=array_merge($conf,['previous_id'=>$settingRow['id'],'effective_from'=>'2025-03-01','approval_required'=>false]);pay_save_config($db,$admin,$optional);
foreach(['2025-06'=>false,'2025-07'=>true] as $month=>$approve){
 $optionalRun=pay_create_run($db,$admin,$month,[$employee]);pay_calculate_run($db,$admin,['run_id'=>$optionalRun,'version'=>1]);
 foreach(array_merge(['review'],$approve?['approve']:[],['finalize']) as $action){$state=pay_run($db,$optionalRun);pay_transition($db,$admin,['run_id'=>$optionalRun,'version'=>$state['version'],'transition'=>$action,'reason'=>'CI optional approval','confirm'=>1]);}
 pc(pay_run($db,$optionalRun)['status']==='Finalized','Optional approval finalization');
}
pc(pay_run($db,$run)['settings_snapshot']===$oldRunSnapshot,'Historical settings snapshot changed');
$legacy=['month'=>'2025-05'];$db->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('payroll',?,'CI legacy preservation','Published',?,?)")->execute([$employee,pay_json($legacy),$admin['id']]);pd(fn()=>pay_create_run($db,$admin,'2025-05',[$employee]),'Legacy duplicate payroll allowed');

$bulkRun=pay_create_run($db,$admin,'2025-08',[$employee]);
$bulkEntry=(int)$db->query('SELECT id FROM hr_payroll_entries WHERE run_id='.$bulkRun)->fetchColumn();
$bulk=['run_id'=>$bulkRun,'version'=>1,'target'=>'Employee','entry_id'=>$bulkEntry,'type'=>'Earning','component'=>'Travel allowance','amount'=>'100','reason'=>'Targeted allocation test','confirm'=>1];
pd(fn()=>pay_bulk_adjust($db,$viewer,$bulk),'Bulk permission bypass');
pd(fn()=>pay_bulk_adjust($db,$admin,array_merge($bulk,['confirm'=>0])),'Bulk confirmation bypass');
pd(fn()=>pay_bulk_adjust($db,$admin,array_merge($bulk,['entry_id'=>$locked['id']])),'Cross-run allocation');
pc(pay_bulk_adjust($db,$admin,$bulk)===1,'Employee allocation');
pd(fn()=>pay_bulk_adjust($db,$admin,$bulk),'Duplicate stale bulk allocation');
$department=(int)$db->query('SELECT id FROM departments ORDER BY id LIMIT 1')->fetchColumn();
$db->prepare('UPDATE employees SET department_id=? WHERE user_id=?')->execute([$department,$employee]);
$deptBulk=array_merge($bulk,['version'=>2,'target'=>'Department','department_id'=>$department,'type'=>'Deduction','component'=>'Approved deduction']);
pc(pay_bulk_adjust($db,$admin,$deptBulk)===1,'Department allocation');
pc((int)$db->query('SELECT COUNT(*) FROM hr_payroll_adjustments WHERE entry_id='.$bulkEntry)->fetchColumn()===2,'Allocation duplicated');
$state=pay_run($db,$run);pd(fn()=>pay_bulk_adjust($db,$admin,array_merge($bulk,['run_id'=>$run,'version'=>$state['version'],'entry_id'=>$locked['id']])),'Locked allocation allowed');
file_put_contents('/tmp/payroll-fixture.json',pay_json(['employee'=>$employee,'run'=>$run,'entry'=>$locked['id'],'structure'=>$structure]));
require dirname(__DIR__).'/includes/payroll/pdf.php';$pdf=pay_pdf(['Payslip','Gross 100.00','Net 90.00']);pc(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'startxref'),'PDF output');
echo "PASS: Payroll additive migration, components, formulas, balancing, CTC, statutory configuration, LOP, OT approval, variable limits, proration, permissions, concurrency, workflow, finalization locks, salary history, snapshot stability and PDF.\n";

require dirname(__DIR__).'/includes/payroll/starter.php';
$oldSettings=chr_rows($db,'pay_settings');$oldSalaries=$db->query('SELECT * FROM hr_salary_assignments ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
pd(fn()=>pay_install_starter($db,$viewer),'Starter permission bypass');
$starter=pay_install_starter($db,$admin);$recordCount=(int)$db->query('SELECT COUNT(*) FROM hr_records')->fetchColumn();
pc(pay_install_starter($db,$admin)===$starter,'Starter repeat changed structure');pc((int)$db->query('SELECT COUNT(*) FROM hr_records')->fetchColumn()===$recordCount,'Starter duplicated records');
pc(chr_rows($db,'pay_settings')===$oldSettings,'Starter replaced payroll rules');pc($db->query('SELECT * FROM hr_salary_assignments ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$oldSalaries,'Starter assigned employee salaries');
$starterRow=chr_record($db,'pay_structure',$starter);$breakup=pay_breakup($starterRow['values'],1900000,['ARK_VARIABLE'=>'0','ARK_PF'=>'0','ARK_LOAN'=>'0','ARK_ADVANCE'=>'0']);
pc($breakup['gross']===1900000&&$breakup['net']===1900000,'Starter introduced deductions');
echo "PASS: starter salary structure, zero optional recoveries, permissions, repeat safety and preservation.\n";

require dirname(__DIR__).'/includes/payroll/automation.php';
pd(fn()=>pay_generate_monthly($db,$viewer,'2025-06'),'Monthly automation permission bypass');
pd(fn()=>pay_generate_monthly($db,$admin,date('Y-m')),'Current month automation accepted');
$existingRun=pay_run($db,$run);$existingResult=pay_generate_monthly($db,$admin,$existingRun['month']);
pc(!$existingResult['created']&&pay_run($db,$run)===$existingRun,'Automation changed existing payroll');
$attendanceBefore=$db->query('SELECT * FROM hr_attendance ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$generated=pay_generate_monthly($db,$admin,'2025-12');pc($generated['created'],'Monthly draft not created');
$generatedRun=pay_run($db,$generated['run_id']);pc($generatedRun['status']==='Calculated','Monthly draft not calculated');
$again=pay_generate_monthly($db,$admin,'2025-12');pc(!$again['created']&&$again['run_id']===$generated['run_id'],'Monthly duplicate created');
pc($db->query('SELECT * FROM hr_attendance ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$attendanceBefore,'Automation altered attendance');
$published=$db->prepare('SELECT COUNT(*) FROM hr_payroll_entries WHERE run_id=? AND published_at IS NOT NULL');$published->execute([$generated['run_id']]);pc((int)$published->fetchColumn()===0,'Automation published without review');
echo "PASS: monthly automation permissions, closed periods, idempotency, attendance preservation and unpublished drafts.\n";

require dirname(__DIR__).'/includes/payroll/profile-salary.php';
$profileInput=['structure_id'=>$structure,'salary_basis'=>'Monthly Gross','salary_amount'=>'42000','effective_from'=>'2025-04-01','reason'=>'Profile salary revision'];
$salaryCount=(int)$db->query('SELECT COUNT(*) FROM hr_salary_assignments')->fetchColumn();
$pendingProfile=pay_profile_preview($db,$admin,$employee,$profileInput);
pc((int)$db->query('SELECT COUNT(*) FROM hr_salary_assignments')->fetchColumn()===$salaryCount,'Profile preview wrote salary');
pd(fn()=>pay_profile_preview($db,$viewer,$employee,$profileInput),'Profile salary permission bypass');
pd(fn()=>pay_profile_input($db,$admin,array_merge($profileInput,['effective_from'=>'2024-12-01']),'2025-01-01'),'Salary before joining accepted');
pd(fn()=>pay_profile_preview($db,$admin,$employee,array_merge($profileInput,['effective_from'=>'2025-02-01'])),'Profile overwrote salary history');
$confirmation=['confirm'=>1,'salary_preview_token'=>$pendingProfile['token']];
pd(fn()=>pay_profile_confirm($db,$admin,$employee,$pendingProfile,['confirm'=>1,'salary_preview_token'=>'bad']),'Wrong salary preview token accepted');
pd(fn()=>pay_profile_confirm($db,$admin,$employee+1,$pendingProfile,$confirmation),'Cross employee salary confirmation accepted');
pd(fn()=>pay_profile_confirm($db,$viewer,$employee,$pendingProfile,$confirmation),'Profile confirmation permission bypass');
pd(fn()=>pay_profile_confirm($db,$admin,$employee,$pendingProfile,['salary_preview_token'=>$pendingProfile['token']]),'Unconfirmed profile salary accepted');
$savedProfile=pay_profile_confirm($db,$admin,$employee,$pendingProfile,$confirmation+['salary_amount'=>'1','employee_id'=>1]);
$savedSalary=$db->query('SELECT * FROM hr_salary_assignments WHERE id='.$savedProfile)->fetch(PDO::FETCH_ASSOC);
pc((int)$savedSalary['employee_id']===$employee&&(int)$savedSalary['monthly_gross']===4200000,'Profile salary did not use reviewed server snapshot');
pd(fn()=>pay_profile_confirm($db,$admin,$employee,$pendingProfile,$confirmation),'Profile salary replay duplicated revision');
pc($db->query('SELECT snapshot FROM hr_payroll_entries WHERE id='.$locked['id'])->fetchColumn()===$locked['snapshot'],'Profile revision changed published payslip');
echo "PASS: profile salary preview, effective dates, permissions, employee binding, confirmation, replay protection and payslip preservation.\n";

// Explicit final-attendance cases: paid statuses, absent/LWP and unpaid half day.
$cleanDays=$days;foreach($cleanDays as &$d){$d['status']='Present';$d['present']=1;$d['absent']=$d['leave']=$d['paid_leave']=$d['unpaid_leave']=$d['holidays']=$d['week_offs']=0;$d['note']='';}unset($d);
foreach(['Present'=>0,'Leave'=>0,'Holiday'=>0,'Week Off'=>0,'Absent'=>100000,'LWP'=>100000,'Half Day'=>50000] as $status=>$expected){
 $case=$cleanDays;$case[0]['status']=$status;
 if($status==='Leave'){$case[0]['paid_leave']=1;$case[0]['present']=0;}
 if($status==='Absent'){$case[0]['absent']=1;$case[0]['present']=0;}
 if($status==='LWP'){$case[0]['unpaid_leave']=1;$case[0]['present']=0;}
 if($status==='Half Day'){$case[0]['absent']=0.5;$case[0]['present']=0.5;}
 $result=pay_calculate_employee($e,$case,$salaries,pay_setting_defaults(),[],[],30);pc($result['lop']===$expected,'Final status deduction: '.$status);
}
foreach(['Check-out missing','Leave Pending','Half Day Leave Pending'] as $status){$case=$cleanDays;$case[0]['status']=$status;$result=pay_calculate_employee($e,$case,$salaries,pay_setting_defaults(),[],[],30);pc(count($result['exceptions'])>0,'Unresolved attendance accepted: '.$status);}
pc(!pay_scheduled_monthly($db,$admin,'2025-12')['created'],'Disabled schedule ran');
$settingRow=chr_rows($db,'pay_settings')[0];$autoConf=array_merge($conf,['previous_id'=>$settingRow['id'],'effective_from'=>'2025-10-01','auto_payroll'=>1,'auto_generate'=>1,'schedule_time'=>'12:00']);pay_save_config($db,$admin,$autoConf);
pc(!pay_scheduled_monthly($db,$admin,'2025-10',new DateTimeImmutable('2025-11-01 08:00',new DateTimeZone('Asia/Kolkata')))['created'],'Scheduler ran before configured time');
$auto=pay_scheduled_monthly($db,$admin,'2025-10');pc($auto['created'],'Scheduled calculation did not run');
$q=$db->prepare('SELECT * FROM hr_payroll_entries WHERE run_id=? AND employee_id=?');$q->execute([$auto['run_id'],$employee]);$draft=$q->fetch(PDO::FETCH_ASSOC);pc($draft['exceptions']===''&&!empty(json_decode($draft['snapshot'],true)['draft_generated_at']),'Draft payslip not generated');
pc(!$draft['published_at'],'Approval-required payroll auto published');
pc(!pay_scheduled_monthly($db,$admin,'2025-10')['created'],'Scheduler duplicated run');
$oldDraft=$draft['snapshot'];$ctx=chr_report_context($db,'2025-10-01','2025-10-31');
// A final sheet correction changes draft payroll, without mutating attendance punches.
$db->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('attendance_sheet_status',?,'CI payroll correction','Active',?,1)")->execute([$employee,pay_json(['date'=>'2025-10-01','status'=>'Present','notes'=>'CI approved correction'])]);
$state=pay_run($db,$auto['run_id']);pay_calculate_run($db,$admin,['run_id'=>$state['id'],'version'=>$state['version']]);$q->execute([$auto['run_id'],$employee]);$corrected=$q->fetch(PDO::FETCH_ASSOC);pc((int)$corrected['net']>(int)$draft['net']&&$corrected['snapshot']!==$oldDraft,'Final attendance correction did not update draft');
$archiveRun=pay_run($db,$optionalRun);$archiveInput=['run_id'=>$optionalRun,'version'=>$archiveRun['version'],'transition'=>'reopen','reason'=>'CI correction with saved archive','confirm'=>1];
pd(fn()=>pay_transition($db,$viewer,$archiveInput),'Employee reopened payroll');pay_transition($db,$admin,$archiveInput);pc(pay_run($db,$optionalRun)['status']==='Draft','Super Admin reopen failed');
pc((int)$db->query("SELECT COUNT(*) FROM hr_records WHERE module='payroll_archive'")->fetchColumn()>0,'Reopen did not archive old snapshot');
pc($db->query('SELECT snapshot FROM hr_payroll_entries WHERE id='.$locked['id'])->fetchColumn()===$locked['snapshot'],'Unrelated finalized payroll changed');
$fixture=json_decode(file_get_contents('/tmp/payroll-fixture.json'),true);$fixture['draft_entry']=(int)$draft['id'];file_put_contents('/tmp/payroll-fixture.json',pay_json($fixture));
echo "PASS: paid/unpaid/half days, unresolved attendance, scheduled drafts, idempotency, correction recalculation, audited reopen and historical preservation.\n";


