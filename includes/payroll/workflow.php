<?php
function pay_workflow_ready(PDO $db): bool {
 try{return (bool)$db->query("SELECT name FROM hr_migrations WHERE name='010-payroll-workflow'")->fetchColumn();}catch(Throwable $e){return false;}
}
function pay_workflow_install(PDO $db,array $actor): void {
 if($actor['role']!=='super_admin')throw new InvalidArgumentException('Super Admin required.');
 if(!pay_ready($db))throw new InvalidArgumentException('Enable the existing Payroll upgrade first.');
 if(pay_workflow_ready($db))return;
 if(!$db->query("SELECT GET_LOCK('peopleflow_payroll_workflow',10)")->fetchColumn())throw new RuntimeException('Payroll workflow upgrade is already running.');
 try{
  if(pay_workflow_ready($db))return;
  foreach(explode(';',file_get_contents(dirname(__DIR__,2).'/database/010-payroll-workflow.sql')) as $sql)if(trim($sql)!=='')$db->exec($sql);
  $db->beginTransaction();
  $permissions=[
   'payroll.salary.edit'=>'Edit salary',
   'payroll.process'=>'Process payroll',
   'payroll.adjust'=>'Adjust payroll',
   'payroll.approve'=>'Approve payroll',
   'payroll.lock'=>'Lock payroll',
   'payroll.reopen'=>'Reopen payroll',
   'payroll.payslip.generate'=>'Generate payslips',
   'payroll.payslip.publish'=>'Publish payslips',
   'payroll.reports'=>'View payroll reports'
  ];
  foreach($permissions as $code=>$label)$db->prepare('INSERT IGNORE INTO permissions(code,label) VALUES(?,?)')->execute([$code,$label]);
  $db->exec("INSERT IGNORE INTO hr_migrations(name) VALUES('010-payroll-workflow')");
  pay_event($db,$actor,'workflow.installed');
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 finally{$db->query("SELECT RELEASE_LOCK('peopleflow_payroll_workflow')");}
}
function pay_lifecycle_labels(): array {
 return [
  'Draft'=>'DRAFT',
  'Attendance Review'=>'ATTENDANCE REVIEW',
  'Ready to Calculate'=>'READY TO CALCULATE',
  'Calculated'=>'CALCULATED',
  'Under Review'=>'UNDER REVIEW',
  'Approved'=>'APPROVED',
  'Locked'=>'LOCKED',
  'Payslips Generated'=>'PAYSLIPS GENERATED',
  'Published'=>'PUBLISHED',
  // Legacy statuses remain readable during migration.
  'Reviewed'=>'UNDER REVIEW',
  'Finalized'=>'LOCKED',
  'Paid'=>'PUBLISHED'
 ];
}
function pay_is_locked_status(string $status): bool {
 return in_array($status,['Locked','Payslips Generated','Published','Finalized','Paid'],true);
}
function pay_template_variables(): array {
 return [
  'Company'=>['logo','company_name','company_address','company_email','company_phone'],
  'Employee'=>['employee_id','fname','lname','full_name','department','designation','joining_date','location','shift'],
  'Payroll'=>['pay_month','pay_period','working_days','present_days','paid_leave','unpaid_leave','lop_days','payable_days','overtime_hours'],
  'Salary'=>['basic','hra','allowances','bonus','incentive','overtime_amount','arrears','gross_salary'],
  'Deductions'=>['pf','esi','professional_tax','tds','loan','advance','lop_amount','other_deductions','total_deductions'],
  'Final'=>['net_salary','net_salary_words'],
  'Bank'=>['bank_name','account_number','ifsc'],
  'Statutory'=>['pan','uan','esi_number'],
  'Tables'=>['earnings_table','deductions_table','attendance_table','salary_summary'],
  'Other'=>['payslip_number','generated_date','authorized_signatory','footer_note']
 ];
}
