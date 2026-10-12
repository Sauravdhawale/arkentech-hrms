<?php
require __DIR__.'/includes/access.php';$user=require_user();
require_once __DIR__.'/includes/payroll/config.php';
header('Content-Type: application/json');header('Cache-Control: no-store, private');
try{
 if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit(json_encode(['error'=>'POST required.']));}
 csrf();pay_allow(db(),$user,'payroll.settings.manage');$o=pay_template_config($_POST)+pay_slip_defaults();
 foreach(['show_logo','show_address','show_attendance','show_bank','show_pan'] as $key)$o[$key]=!empty($_POST[$key]);
 $o['footer']=(string)($_POST['footer']??'');$o['signatory']=(string)($_POST['signatory']??'');
 if($o['template_mode']!=='html')throw new InvalidArgumentException('Select Custom HTML to preview a template.');
 $entry=['month'=>'Sample month','payslip_number'=>'PREVIEW-001','gross'=>3000000,'deductions'=>100000,'net'=>2900000];
 $settings=['payslip'=>$o,'company'=>['name'=>'Sample Company — Preview only','address'=>'Sample office address','logo'=>''],'payroll'=>['currency'=>'INR','currency_position'=>'Before']];
 $snapshot=['employee'=>['name'=>'Sample Employee','employee_code'=>'SAMPLE001','department_name'=>'Sample Department','designation_name'=>'Sample Designation'],'attendance'=>['working_days'=>22,'present'=>22,'absent'=>0,'paid_leave'=>0,'unpaid_leave'=>0,'approved_overtime_hours'=>0],'bank_last4'=>'1234','pan_last4'=>'1234'];
 $components=[['code'=>'BASIC','name'=>'Basic salary','category'=>'Earning','amount'=>2000000],['code'=>'ALLOWANCE','name'=>'Allowance','category'=>'Earning','amount'=>1000000],['code'=>'OTHER','name'=>'Sample deduction','category'=>'Deduction','amount'=>100000]];
 echo json_encode(['html'=>pay_template_document(pay_template_render($entry,$settings,$snapshot,$components))],JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
