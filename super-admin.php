<?php
require __DIR__.'/includes/access.php';
$user=require_user();
require_once __DIR__.'/includes/foundation/core.php';
if(($_GET['page']??'')==='regularisation')$_GET['page']='attendance_requests';
$foundationPages=['overview','employees','employee-add','employee-edit','employee-view','settings','departments','designations','roles','account','system','shifts','roster','holidays','attendance','manual_attendance','attendance_history','monthly','leaves','leave_history','leave_policy','balances','attendance_settings','shift_defaults','time_policies','biometric_integration','attendance_dashboard','attendance_requests','daily_work_status','punch_log','attendance_exceptions','company_calendar','devices','mapping','sync','raw_logs','payroll_dashboard','employee_salary','payroll_processing','payroll_payslips','payroll_history','payroll_reports','salary_components','salary_structures','statutory_settings','payroll_settings','payslip_settings'];
if(in_array($_GET['page']??'overview',$foundationPages,true))require __DIR__.'/includes/foundation/controller.php';
else{if(in_array($_GET['page']??'',['device_attendance','device_monthly'],true)){require_once __DIR__.'/includes/core-hr/service.php';if(!att_biometric_enabled(db())){http_response_code(403);exit('Biometric attendance is disabled.');}}if($user['role']!=='super_admin'){http_response_code(403);exit('Super Admin access required.');}require __DIR__.'/includes/workspace.php';}
