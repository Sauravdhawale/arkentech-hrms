<?php
$eligible=pay_simple_missing_salaries($pdo,$month);$total=count($eligible);
$missing=array_values(array_filter($eligible,fn($e)=>!$e['salary_saved']));
$sum=['gross'=>0,'deductions'=>0,'net'=>0];$generated=0;$published=0;$exceptions=0;
foreach($rows as $r){
 $exceptions+=(int)($r['exceptions']!=='');
 if($r['exceptions']===''){foreach($sum as $key=>$value)$sum[$key]+=(int)$r[$key];$generated+=(int)(pay_simple_stage($r)!=='Draft');}
 $published+=(int)(!empty($r['published_at'])&&in_array($r['run_status'],['Finalized','Paid'],true));
}
$currency=$run?(json_decode($run['settings_snapshot'],true)['payroll']['currency']??'INR'):'INR';
$monthName=date('F Y',strtotime($month.'-01'));
$nextStep=!$run?'Generate this month’s payslips':($exceptions?'Resolve '.$exceptions.' payroll issue'.($exceptions===1?'':'s'):($published===count($rows)&&$published?'Payslips are ready to download':'Review and publish payslips'));
?>
<header class="pay-heading"><div><span class="eyebrow">PAYROLL AT A GLANCE</span><h2><?=h($monthName)?></h2><p>Set salaries once. Manage each month’s payslips here.</p></div><form method="get" class="pay-inline"><input type="hidden" name="page" value="payroll_dashboard"><label>Month<input type="month" name="month" value="<?=h($month)?>" required></label><button class="button">View month</button></form></header>
<section class="card pay-next-step"><div><span class="eyebrow">YOUR NEXT STEP</span><h2><?=h($nextStep)?></h2><p><?=!$run?'Payroll uses the salary saved in each employee profile and that month’s attendance.':($exceptions?'Open Payslips to see exactly what needs to be corrected.':'All employee payslips for this month are in one list.')?></p></div><a class="button primary" href="?page=payroll_payslips&amp;month=<?=h($month)?>">Open Payslips →</a></section>
<div class="pay-metrics pay-overview-metrics">
<?php foreach(['Employees'=>[(string)$total,(count($missing)?count($missing).' need salary setup':'Salaries saved for all employees')],'Net salary total'=>[$run?$currency.' '.pay_amount($sum['net']):'—',$run?'Calculated entries without errors':'Generate payslips to calculate'],'Payslips published'=>[$published.' / '.$total,'Available for employees to download'],'Needs attention'=>[(string)$exceptions,$run?'Salary or attendance issues':'Checked when you generate']] as $label=>[$value,$hint]):?><article class="card pay-metric"><span><?=h($label)?></span><strong><?=h($value)?></strong><small><?=h($hint)?></small></article><?php endforeach;?>
</div>
<div class="pay-dashboard-columns"><section class="card"><h2>How payroll works</h2><ol class="pay-workflow-steps"><li><span>1</span><div><strong>Set employee salary</strong><p>Open People → Edit employee → Salary. Enter the monthly amount and when it starts. The saved company rules calculate the breakup.</p></div></li><li><span>2</span><div><strong>Generate monthly payslips</strong><p>Choose the month in Payslips. Attendance and saved deductions determine each employee’s net salary.</p></div></li><li><span>3</span><div><strong>Review, publish and download</strong><p>Review the amounts, resolve any issues, then publish after the month closes. Employees can download their own payslips.</p></div></li></ol></section>
<section class="card"><div class="pay-heading"><div><h2>Salary setup</h2><p>Only employees without a salary for <?=h($monthName)?>.</p></div><span class="pay-count"><?=count($missing)?></span></div>
<?php foreach(array_slice($missing,0,5) as $e):?><a class="pay-action-row" href="?page=employee-edit&amp;id=<?=(int)$e['user_id']?>#profile-salary"><span><strong class="pay-person-name"><?=h($e['name'])?></strong><small><?=h($e['employee_code'])?></small></span><span>Set salary →</span></a><?php endforeach;?>
<?php if(!$missing):?><div class="pay-empty pay-empty-small"><strong>Salary setup is complete</strong><p>Review this month’s attendance, then open Payslips.</p></div><?php elseif(count($missing)>5):?><p><a href="?page=payroll_payslips&amp;month=<?=h($month)?>&amp;attention=salary">View all <?=count($missing)?> employees</a></p><?php endif;?>
</section></div>
<section class="card pay-understanding"><div><h3>What changes the take-home amount?</h3><p>Saved salary + applicable earnings − unpaid attendance − configured deductions = net salary.</p><p class="pay-muted">Paid attendance and paid leave do not cause an absence deduction. Unpaid leave and absence follow your saved rules. Published payslips keep their original monthly calculation.</p></div><?php if(can($pdo,$user,'payroll.settings.view')):?><a class="button" href="?page=salary_components">View payroll rules</a><?php endif;?></section>
