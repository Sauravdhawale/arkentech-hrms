<?php
$tabs=['general'=>'General','rules'=>'Salary Rules','attendance'=>'Attendance & LOP','statutory'=>'Statutory','payslip'=>'Payslip Settings'];
$tab=isset($tabs[$_GET['tab']??''])?$_GET['tab']:'general';
$module=$page==='payslip_settings'?'pay_payslip':match($tab){'rules'=>(($_GET['kind']??'')==='structure'?'pay_structure':'pay_component'),'statutory'=>'pay_statutory','payslip'=>'pay_payslip',default=>'pay_settings'};
?>
<header class="pay-heading"><div><h2><?= $page==='payslip_settings'?'Payslip Designer':'Configuration' ?></h2><p class="pay-muted"><?= $page==='payslip_settings'?'One shared layout, populated with each employee’s saved payroll values.':'Salary rules, attendance deductions and payslip settings in one place.' ?></p></div></header>
<?php if($page!=='payslip_settings'):?>
<nav class="pay-config-tabs" aria-label="Payroll configuration"><?php foreach($tabs as $key=>$label):?><a href="?page=salary_components&amp;tab=<?=h($key)?>" <?=$key===$tab?'aria-current="page"':''?>><?=h($label)?></a><?php endforeach;?></nav>
<?php if($tab==='rules'):?><div class="pay-actions"><?php foreach(['Earning'=>'Allowances','Deduction'=>'Deductions','Employer'=>'Employer contributions'] as $key=>$label):?><a class="button" href="?page=salary_components&amp;tab=rules&amp;category=<?=h($key)?>"><?=h($label)?></a><?php endforeach;?><a class="button" href="?page=salary_components&amp;tab=rules&amp;kind=structure">Common salary structure</a></div><?php endif;?>
<?php if($tab==='attendance'):?><section class="card pay-card"><h3>Attendance determines payable days</h3><p>Present, paid leave, paid holidays and paid week offs do not cause loss of pay. Absence and unpaid leave follow the selected LOP rule. Half days use their recorded paid fraction. Resolve attendance exceptions before finalizing payroll.</p></section><?php endif;?>
<?php if($tab==='payslip'):?><section class="card pay-card"><p>Company name, address and logo come from Company Settings. Edit the shared HTML layout in <a href="?page=payslip_settings">Payslip Designer</a>.</p></section><?php endif;?>
<?php endif; require __DIR__.'/settings-view.php';?>
<script src="assets/payroll-four.js?v=1" defer></script>