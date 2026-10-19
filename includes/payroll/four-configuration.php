<?php
$tabs=['general'=>'Monthly payroll','rules'=>'Salary rules','attendance'=>'Attendance deductions','statutory'=>'PF / ESI & other rules','payslip'=>'Payslip details'];
$tab=isset($tabs[$_GET['tab']??''])?$_GET['tab']:'general';
$module=$page==='payslip_settings'?'pay_payslip':match($tab){'rules'=>(($_GET['kind']??'')==='structure'?'pay_structure':'pay_component'),'statutory'=>'pay_statutory','payslip'=>'pay_payslip',default=>'pay_settings'};
?>
<header class="pay-heading"><div><h2><?= $page==='payslip_settings'?'Payslip Designer':'Configuration' ?></h2><p class="pay-muted"><?= $page==='payslip_settings'?'One shared layout, populated with each employee’s saved payroll values.':'Set your company’s rules once. Enter each employee’s salary in their profile.' ?></p></div></header>
<?php if($page!=='payslip_settings'):?>
<section class="pay-setup-guide" aria-label="How payroll works">
<div><span>1</span><div><strong>Set the salary</strong><p>Open an employee profile and enter their salary and start date.</p></div></div>
<div><span>2</span><div><strong>Check attendance</strong><p>Paid days keep their pay. Unpaid days follow your deduction rules.</p></div></div>
<div><span>3</span><div><strong>Get the payslips</strong><p>Open <a href="?page=payroll_payslips">Payslips</a>, choose a month, review and download.</p></div></div>
</section>
<nav class="pay-config-tabs" aria-label="Payroll configuration"><?php foreach($tabs as $key=>$label):?><a href="?page=salary_components&amp;tab=<?=h($key)?>" <?=$key===$tab?'aria-current="page"':''?>><?=h($label)?></a><?php endforeach;?></nav>
<?php if($tab==='general'):?><section class="pay-config-help"><h3>Monthly payslip preparation</h3><p>Choose when to prepare the completed month’s salaries and whether they need approval. Automatic preparation also requires the payroll cron job to be configured in Hostinger; the switches below do not confirm that it is running.</p><p class="pay-muted">Example: closing day 1 prepares September payroll on 1 October, after the final shift has closed. You can also prepare a month from <a href="?page=payroll_payslips">Payslips</a>.</p></section><?php endif;?>
<?php if($tab==='rules'):?><section class="pay-config-help"><h3>One salary amount, a shared breakup</h3><p>The employee profile stores the salary amount. These rules split it into Basic, allowances and deductions. Keep the common structure for employees who use the same rules.</p></section><div class="pay-actions"><?php foreach(['Earning'=>'Allowances','Deduction'=>'Deductions','Employer'=>'Employer contributions'] as $key=>$label):?><a class="button" href="?page=salary_components&amp;tab=rules&amp;category=<?=h($key)?>"><?=h($label)?></a><?php endforeach;?><a class="button" href="?page=salary_components&amp;tab=rules&amp;kind=structure">Common salary structure</a></div><?php endif;?>
<?php if($tab==='attendance'):?><section class="pay-config-help"><h3>Paid and unpaid days</h3><p><strong>No attendance deduction:</strong> present days, paid leave, paid holidays and paid week offs.</p><p><strong>Deduct pay:</strong> absence and unpaid leave, according to the rule below. Half days use their recorded paid fraction. Correct missing or incomplete attendance before making payslips final.</p></section><?php endif;?>
<?php if($tab==='statutory'):?><section class="pay-config-help"><h3>Optional company deductions and contributions</h3><p>Keep only the rules your company uses. Adding a rule here does not change an employee’s saved salary structure automatically.</p></section><?php endif;?>
<?php if($tab==='payslip'):?><section class="pay-config-help"><h3>What appears on the payslip</h3><p>Company name, address and logo come from Company Settings. Choose the details to display below. Change the shared HTML layout in <a href="?page=payslip_settings">Payslip Designer</a>.</p></section><?php endif;?>
<?php endif; require __DIR__.'/settings-view.php';
if($page==='salary_components'&&$tab==='general')require __DIR__.'/setup-date-view.php';?>
<script src="assets/payroll-four.js?v=2" defer></script>
