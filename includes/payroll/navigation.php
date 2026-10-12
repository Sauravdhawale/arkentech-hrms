<?php
$payNav=['payroll_dashboard'=>'Dashboard','payroll_payslips'=>'Payslips','payroll_loans'=>'Loans & Salary Advances'];
$visiblePay=array_filter($payNav,fn($label,$key)=>can($pdo,$user,$payPages[$key][1]),ARRAY_FILTER_USE_BOTH);
if($visiblePay):?><details class="pay-nav" <?=isset($payPages[$page])?'open':''?>><summary>▤ Payroll</summary><div><?php foreach($visiblePay as $key=>$label)fnav($key,$label);?></div></details><?php endif;?>

