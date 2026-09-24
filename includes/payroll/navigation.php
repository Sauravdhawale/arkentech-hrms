<?php
$payNav=['payroll_dashboard'=>'Dashboard','employee_salary'=>'Employee Salary','payroll_processing'=>'Payroll Processing','payroll_payslips'=>'Payslips','payroll_history'=>'Payroll History','payroll_reports'=>'Reports'];
$visiblePay=array_filter($payNav,fn($label,$key)=>can($pdo,$user,$payPages[$key][1]),ARRAY_FILTER_USE_BOTH);
if($visiblePay):?><details class="pay-nav" <?=isset($payNav[$page])?'open':''?>><summary>▤ Payroll</summary><div><?php foreach($visiblePay as $key=>$label)fnav($key,$label);?></div></details><?php endif;?>
