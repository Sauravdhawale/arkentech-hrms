<?php
$rows=pay_report_rows($pdo,array_merge($_GET,['month'=>$month]));$canReview=can($pdo,$user,'payroll.process.view');
if(!$canReview)$rows=array_values(array_filter($rows,fn($r)=>!empty($r['published_at'])));
$stageFor=fn($r)=>match($r['run_status']){'Paid'=>'Paid','Finalized','Locked'=>'Confirmed','Draft'=>'Draft',default=>'Review'};
$counts=['Draft'=>0,'Review'=>0,'Confirmed'=>0,'Paid'=>0];foreach($rows as $r)$counts[$stageFor($r)]++;
?>
<section class="card pay-panel" data-pay-list>
<div class="pay-panel-heading"><div><h2>Payslips <span class="pay-count"><?=count($rows)?></span></h2><p class="pay-muted">Review monthly payroll entries. Employees see published payslips only.</p></div><div class="pay-list-actions"><label class="pay-search">Search<input type="search" data-pay-search placeholder="Employee name or ID" aria-label="Search payslips"></label><?php if(can($pdo,$user,'payroll.process.manage')):?><button class="button primary" type="button" onclick="document.getElementById('create-monthly-payroll').showModal()">+ Create</button><?php endif;?></div></div>
<div class="pay-filter-chips"><button class="button is-active" type="button" data-pay-filter="" aria-pressed="true">All <span><?=count($rows)?></span></button><?php foreach($counts as $stage=>$count):?><button type="button" class="button" data-pay-filter="<?=h($stage)?>" aria-pressed="false"><span class="pay-dot pay-dot-<?=strtolower($stage)?>"></span><?=h($stage)?> <span><?=$count?></span></button><?php endforeach;?></div>
<div class="pay-table pay-modern-table"><table><thead><tr><th>Employee</th><th>Period</th><th>Payroll batch</th><th>Gross pay</th><th>Deductions</th><th>Net pay</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $r):$s=json_decode($r['snapshot'],true);$name=$s['employee']['name']??$r['name'];$code=$s['employee']['employee_code']??$r['employee_code'];$stage=$stageFor($r);?><tr data-pay-row data-pay-category="<?=h($stage)?>"><td><div class="pay-person"><span class="pay-avatar" aria-hidden="true"><?=h(substr($name,0,1))?></span><div><strong><?=h($name)?></strong><small><?=h($code)?></small></div></div></td><td><?=h($r['month'].'-01')?><small>to <?=h(date('Y-m-t',strtotime($r['month'].'-01')))?></small></td><td>Run #<?=(int)$r['run_id']?></td><td class="pay-money"><?=pay_amount((int)$r['gross'])?></td><td class="pay-money"><?=pay_amount((int)$r['deductions'])?></td><td class="pay-money"><strong><?=pay_amount((int)$r['net'])?></strong></td><td><span class="pay-badge pay-badge-<?=strtolower($stage)?>"><?=h($stage)?></span><small><?=!empty($r['published_at'])?'Published':'Not published'?></small></td><td><div class="pay-list-actions"><?php if(!empty($r['published_at'])&&in_array($r['run_status'],['Finalized','Paid'],true)):?><a class="button" href="payroll-slip.php?entry=<?=(int)$r['id']?>">View / PDF</a><?php endif;if($canReview):?><a class="button" href="?page=payroll_processing&run=<?=(int)$r['run_id']?>">Review run</a><?php endif;?></div></td></tr><?php endforeach;?>
<tr data-pay-empty <?=$rows?'hidden':''?>><td colspan="8">No matching payslips. Select another month or create a payroll run.</td></tr></tbody></table></div><p class="pay-muted" data-pay-result><?=count($rows)?> records</p></section>


<?php if(can($pdo,$user,'payroll.process.manage')):?>
<dialog id="create-monthly-payroll" class="pay-create-dialog" aria-labelledby="create-payroll-title">
<div class="pay-panel-heading"><h2 id="create-payroll-title">Create monthly payslips</h2><button type="button" class="button" onclick="this.closest('dialog').close()" aria-label="Close create payroll">✕</button></div>
<p>Choose a month and employees. Payroll uses their saved salary and attendance for that month.</p>
<?php pay_form('pay_create');?><div class="pay-grid"><?php pay_text('month','Payroll month',['month'=>$month],'month',true);pay_select('employee_id','Employees',$_GET['employee_id']??'',$employeeOptions);?></div>
<p class="pay-muted">Review absences, unpaid leave and missing punches before finalizing. Creating a draft does not publish payslips.</p><button class="button primary">Create draft</button></form>
</dialog>
<?php if(($_GET['create']??'')==='1'):?><script>document.getElementById('create-monthly-payroll').showModal();</script><?php endif;?>
<details class="card pay-card"><summary>Test a full-attendance payslip</summary><p class="pay-muted">Preview last month using a saved salary and no absence deductions. Test copies are clearly marked and do not change attendance or publish payroll.</p><a class="button" href="payroll-test-preview.php" target="_blank" rel="noopener">Preview last month · full attendance</a></details>
<?php endif;?>
