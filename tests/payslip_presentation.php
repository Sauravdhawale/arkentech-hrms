<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/payroll/calculation.php';
require __DIR__.'/../includes/payroll/templates.php';
require __DIR__.'/../includes/payroll/statement.php';
require __DIR__.'/../includes/payroll/statement-pdf.php';
set_error_handler(function($severity,$message,$file,$line){if(!(error_reporting()&$severity))return false;throw new ErrorException($message,0,$severity,$file,$line);});
function slip_check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$entry=['month'=>'2026-09','payslip_number'=>'SAMPLE-202609-001','status'=>'Finalized','gross'=>3500000,'deductions'=>125000,'net'=>3375000];
$settings=['company'=>['name'=>'Arkentech Solutions','address'=>'Sample office address — demonstration only','logo'=>''],'payroll'=>['currency'=>'INR','currency_position'=>'Before'],'payslip'=>['template_mode'=>'standard','show_logo'=>true,'show_address'=>true,'show_attendance'=>true,'show_bank'=>false,'show_pan'=>false,'signatory'=>'HR Department','footer'=>'SAMPLE — NOT FOR PAYMENT']];
$snapshot=['employee'=>['name'=>'Sample Employee','employee_code'=>'SAMPLE001','department_name'=>'Marketing','designation_name'=>'Team Leader','joining_date'=>'2024-09-01'],'attendance'=>['working_days'=>22,'present'=>21,'absent'=>1,'paid_leave'=>0,'unpaid_leave'=>0,'approved_overtime_hours'=>0],'bank_last4'=>'9876','pan_last4'=>'4321'];
$components=[['name'=>'Basic Salary','category'=>'Earning','amount'=>1750000],['name'=>'House Rent Allowance','category'=>'Earning','amount'=>875000],['name'=>'Bonus','category'=>'Earning','amount'=>145775],['name'=>'Special Allowance','category'=>'Earning','amount'=>729225],['name'=>'Loss of Pay','category'=>'Deduction','amount'=>125000]];
$before=serialize([$entry,$settings,$snapshot,$components]);$data=pay_statement_data($entry,$settings,$snapshot,$components);$html=pay_statement_html($data);$pdf=pay_statement_pdf($data);
slip_check($before===serialize([$entry,$settings,$snapshot,$components]),'Rendering changed payroll input');
slip_check(str_contains($html,'September 2026')&&str_contains($html,'INR 33,750.00'),'Monthly identity or net amount missing');
slip_check(str_contains($pdf,'INR 33,750.00')&&str_contains($pdf,'/Subtype /Image'),'PDF net amount/logo missing');
slip_check(!str_contains($html,'9876')&&!str_contains($pdf,'9876')&&!str_contains($pdf,'4321'),'Privacy flags ignored');
slip_check(str_contains($pdf,'/FontFile2')&&str_contains($pdf,'/Count 1'),'Embedded font or compact A4 layout missing');
slip_check(str_contains($pdf,'Sample Employee')&&str_contains($pdf,'Loss of Pay'),'Readable PDF text missing');
slip_check(pay_statement_is_standard(['template_mode'=>'html','html_template'=>pay_template_starters()['Annexure A']]),'Legacy starter not upgraded');
slip_check(!pay_statement_is_standard(['template_mode'=>'html','html_template'=>pay_template_starters()['Annexure A'].'<p>Custom note</p>']),'Custom HTML overridden');
$draft=$entry;$draft['status']='DRAFT — NOT FINAL';slip_check(str_contains(pay_statement_pdf(pay_statement_data($draft,$settings,$snapshot,$components)),'DRAFT - NOT FINAL'),'Draft watermark missing');
$private=$settings;$private['payslip']['show_logo']=false;$private['payslip']['show_attendance']=false;$private['payslip']['show_bank']=true;$private['payslip']['show_pan']=true;$pd=pay_statement_data($entry,$private,$snapshot,$components);slip_check($pd['logo']===''&&$pd['attendance']===[]&&$pd['info']['Bank account']==='****9876','Privacy configuration ignored');
$unsafe=$snapshot;$unsafe['employee']['name']='<script>alert(1)</script>';slip_check(str_contains(pay_statement_html(pay_statement_data($entry,$settings,$unsafe,$components)),'&lt;script&gt;'),'Employee HTML not escaped');
$many=$components;for($i=0;$i<70;$i++)$many[]=['name'=>'Long component '.$i.' with a descriptive label to wrap safely','category'=>'Employer','amount'=>0];$long=pay_statement_pdf(pay_statement_data($entry,$settings,$snapshot,$many));slip_check(substr_count($long,'/Type /Page ')>1&&str_contains($long,'Long component 69'),'PDF pagination dropped rows');
@mkdir('/tmp/shrms-ui-screenshots',0777,true);file_put_contents('/tmp/shrms-ui-screenshots/sample-payslip.pdf',$pdf);file_put_contents('/tmp/shrms-ui-screenshots/sample-payslip.html',pay_template_document($html,'Sample monthly payslip',pay_statement_css()));file_put_contents('/tmp/shrms-ui-screenshots/sample-payslip-long.pdf',$long);
echo "PASS: monthly statement, logo PDF, pagination, snapshot immutability, custom HTML preservation, draft and privacy flags.\n";
