<?php
// Old URLs and old form submissions must never recreate the retired payroll data.
$retiredPayrollPages=['payroll','my_salary','salary','contracts','advances','components','payroll_dashboard','payroll_loans','employee_salary','payroll_processing','payroll_payslips','payroll_history','payroll_reports','salary_components','salary_structures','statutory_settings','payroll_settings','payslip_settings'];
$retiredPayrollAction=(string)($_POST['action']??'');
if(in_array((string)($_GET['page']??''),$retiredPayrollPages,true)||str_starts_with($retiredPayrollAction,'pay_')||str_starts_with($retiredPayrollAction,'profile_salary_')||!empty($_POST['setup_salary'])){
 http_response_code(410);header('Cache-Control: no-store');exit('The old Payroll module has been removed.');
}
