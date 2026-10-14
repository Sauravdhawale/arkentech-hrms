<?php
$payNav=['payroll_dashboard'=>'Dashboard','payroll_payslips'=>'Payslips','salary_components'=>'Configuration','payslip_settings'=>'Payslip Designer'];
$visiblePay=array_filter($payNav,fn($label,$key)=>can($pdo,$user,$payPages[$key][1]),ARRAY_FILTER_USE_BOTH);
if($visiblePay):?><details class="pay-nav" <?=isset($payPages[$page])?'open':''?>><summary>▤ Payroll</summary><div><?php foreach($visiblePay as $key=>$label){if($key==='salary_components'){echo '<a href="?page=salary_components" '.(in_array($page,['salary_components','salary_structures','payroll_settings','statutory_settings','employee_salary'],true)?'class="selected" aria-current="page"':'').'><span aria-hidden="true">⚙</span>Configuration</a>';}else fnav($key,$label);}?></div></details><?php endif;?>



