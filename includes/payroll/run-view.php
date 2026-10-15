<?php
try{$run=pay_run($pdo,(int)$_GET['run']);}catch(InvalidArgumentException $e){echo '<p>'.h($e->getMessage()).'</p>';return;}
$runSettings=json_decode($run['settings_snapshot'],true);$locked=in_array($run['status'],['Finalized','Paid'],true);$editing=in_array($run['status'],['Draft','Calculated'],true);$hidden=['run_id'=>$run['id'],'version'=>$run['version']];
$q=$pdo->prepare('SELECT e.*,u.name,p.employee_code FROM hr_payroll_entries e JOIN users u ON u.id=e.employee_id JOIN employees p ON p.user_id=e.employee_id WHERE e.run_id=? ORDER BY u.name');$q->execute([$run['id']]);$entries=$q->fetchAll(PDO::FETCH_ASSOC);$allEntries=$entries;$entryOptions=[];foreach($entries as $e)$entryOptions[$e['id']]=$e['name'].' · '.$e['employee_code'];
?>
<section class="card pay-card"><div class="pay-actions"><a class="button" href="?page=payroll_processing&month=<?=h($run['month'])?>">All runs</a><span class="badge"><?=h($run['status'])?></span></div><h2><?=h($run['month'])?> payroll · <?=h($runSettings['payroll']['currency'])?></h2>
<p class="pay-muted">Review attendance deductions, recalculate after corrections, then finalize. <?=!empty($runSettings['payroll']['approval_required'])?'Your company requires review and approval before finalization.':''?> Finalized amounts stay locked.</p>
<div class="pay-grid"><?php foreach(['gross'=>'Gross','deductions'=>'Deductions','net'=>'Net pay'] as $key=>$label):?><div><?=h($label)?><strong class="pay-stat"><?=pay_amount(array_sum(array_map(fn($e)=>(int)$e[$key],$entries)))?></strong></div><?php endforeach;?></div>
<?php if($editing&&can($pdo,$user,'payroll.process.manage')){pay_form('pay_calculate',$hidden);echo '<div class="pay-actions"><button class="button primary">Calculate / recalculate</button></div></form>';}?>
</section>
<?php
$transitions=[];if(!$locked&&can($pdo,$user,'payroll.process.manage')){$transitions['draft']='Return to Draft';if($run['status']==='Calculated')$transitions['review']='Mark Reviewed';if($editing){$transitions['hold']='Put employee On Hold';$transitions['release']='Release employee hold';}}
if($run['status']==='Reviewed'&&can($pdo,$user,'payroll.process.approve'))$transitions['approve']='Approve payroll';
if(($run['status']==='Approved'||($run['status']==='Reviewed'&&!$runSettings['payroll']['approval_required']))&&can($pdo,$user,'payroll.process.finalize'))$transitions['finalize']='Finalize and lock';
if($run['status']==='Finalized'&&can($pdo,$user,'payroll.process.pay'))$transitions['paid']='Mark Paid';
if($locked&&$user['role']==='super_admin')$transitions['reopen']='Reopen payroll (withdraws published copies)';
if($locked&&can($pdo,$user,'payslip.generate'))$transitions['publish']='Generate / publish payslips';
if($transitions):?>
<details open class="card pay-card"><summary>Payroll workflow actions</summary><?php pay_form('pay_transition',$hidden);?><div class="pay-grid"><?php pay_select('transition','Action','',$transitions);pay_select('entry_id','Employee (hold / release only)','',$entryOptions);pay_text('reason','Reason / payment reference',[],'text',true);?></div><?php pay_check('confirm','Confirm this workflow change',[]);?><div class="pay-actions"><button class="button primary">Apply action</button></div></form></details><?php endif;?>
<?php if($editing&&can($pdo,$user,'payroll.process.manage')):?>
<details class="card pay-card"><summary>Add individual adjustment</summary><p class="pay-muted">Use a later month to correct finalized payroll. Include the original payslip reference in the reason. Adjustments remain in the audit history. Overtime hours need approval before calculation.</p><?php pay_form('pay_adjust',$hidden);?><div class="pay-grid"><?php pay_select('entry_id','Employee','',$entryOptions);choose('type','Adjustment type',[],['Earning','Deduction','Bonus','Incentive','Variable','Variable Percentage','Overtime']);pay_text('component','Payslip label',[]);pay_text('amount','Amount (except OT / percentage)',['amount'=>'0']);pay_text('units','OT hours or variable percentage',['units'=>'0']);pay_text('reason','Reason / original payroll reference',[],'text',true);?></div><div class="pay-actions"><button class="button primary">Add adjustment</button></div></form></details>
<?php endif;?>
<?php if($editing&&can($pdo,$user,'payroll.process.manage')):?>
<details class="card pay-card"><summary>Add employees to this draft</summary><?php pay_form('pay_add_employees',$hidden);pay_select('employee_id','Eligible employees','',$employeeOptions);?><p>Existing entries are retained. Only eligible employees not already in this run are added.</p><button class="button primary">Add to payroll</button></form></details><?php endif;?>
<?php if($editing&&can($pdo,$user,'payroll.process.manage')):?>
<details class="card pay-card"><summary>Allocate allowance / deduction</summary>
<p class="pay-muted">Apply the same amount per employee to one employee or a department in this draft. Choose only one target. Recalculate to preview the result before approval.</p>
<?php pay_form('pay_bulk_adjust',$hidden);?><div class="pay-grid"><?php
pay_select('target','Apply to','Employee',['Employee'=>'Employee','Department'=>'Department']);
pay_select('entry_id','Employee','',[''=>'Choose employee']+$entryOptions);
pay_select('department_id','Department','',[''=>'Choose department']+array_diff_key($departmentOptions,[''=>true]));
pay_select('type','Adjustment','Earning',['Earning'=>'Allowance','Deduction'=>'Deduction']);
pay_text('component','Payslip attribute / label',[],'text',true);pay_text('amount','Amount per employee',[],'text',true);pay_text('reason','Reason',[],'text',true);
?></div><?php pay_check('confirm','Apply this amount to every employee in the selected target for this run',[]);?><button class="button primary">Apply allocation</button></form></details>
<?php endif;?>
<?php
$structureFilter=[''=>'All salary structures'];foreach(chr_rows($pdo,'pay_structure') as $structure)$structureFilter[$structure['id']]=$structure['title'].' · '.$structure['values']['effective_from'];
$entries=array_values(array_filter($allEntries,function($entry){
 $s=json_decode($entry['snapshot'],true);
 if(!empty($_GET['employee_id'])&&(int)$_GET['employee_id']!==(int)$entry['employee_id'])return false;
 if(!empty($_GET['department_id'])&&(int)$_GET['department_id']!==(int)($s['employee']['department_id']??0))return false;
 if(!empty($_GET['status'])&&$_GET['status']!==$entry['status'])return false;
 if(!empty($_GET['structure_id'])){foreach($s['salary_segments']??[] as $segment)if((int)$segment['salary']['structure_id']===(int)$_GET['structure_id'])return true;return false;}
 return true;
}));
?><form method="get" class="card pay-card pay-toolbar"><?php pay_hidden('page','payroll_processing');pay_hidden('run',$run['id']);pay_select('employee_id','Employee',$_GET['employee_id']??'',$employeeOptions);pay_select('department_id','Department',$_GET['department_id']??'',$departmentOptions);pay_select('status','Status',$_GET['status']??'',[''=>'All statuses']+pay_options(['Draft','Calculated','Reviewed','Approved','Finalized','Paid','On Hold']));pay_select('structure_id','Salary structure',$_GET['structure_id']??'',$structureFilter);?><button class="button primary">Apply filters</button></form>
<?php require __DIR__.'/monthly-table.php';?>
<section class="card pay-card"><h2>Adjustments and overtime approvals</h2><div class="pay-table"><table><thead><tr><th>Employee</th><th>Type</th><th>Amount / units</th><th>Reason</th><th>Approval</th></tr></thead><tbody>
<?php $q=$pdo->prepare('SELECT a.*,u.name FROM hr_payroll_adjustments a JOIN hr_payroll_entries e ON e.id=a.entry_id JOIN users u ON u.id=e.employee_id WHERE e.run_id=? ORDER BY a.id');$q->execute([$run['id']]);foreach($q as $a):?><tr><td><?=h($a['name'])?></td><td><?=h($a['type'])?></td><td><?=pay_amount((int)$a['amount'])?> / <?=h($a['units'])?></td><td><?=h($a['reason'])?></td><td><?php if($a['type']==='Overtime'&&!$a['approved_by']&&$editing&&can($pdo,$user,'payroll.process.approve')){pay_form('pay_transition',$hidden+['transition'=>'approve_ot','adjustment_id'=>$a['id'],'reason'=>'Approve recorded overtime adjustment #'.$a['id']]);pay_check('confirm','Hours reviewed',[]);echo '<button class="button">Approve OT</button></form>';}else echo $a['approved_by']?'Approved '.h($a['approved_at']):($a['type']==='Overtime'?'Pending':'Payroll workflow');?></td></tr><?php endforeach;?></tbody></table></div></section>
<details class="card pay-card"><summary>Audit trail and saved settings</summary><div class="pay-table"><table><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Reason</th></tr></thead><tbody><?php $q=$pdo->prepare('SELECT e.*,u.name FROM hr_payroll_events e JOIN users u ON u.id=e.actor_id WHERE e.run_id=? ORDER BY e.id DESC');$q->execute([$run['id']]);foreach($q as $event)echo '<tr><td>'.h($event['created_at']).'</td><td>'.h($event['name']).'</td><td>'.h($event['action']).'</td><td>'.h($event['reason']).'</td></tr>';?></tbody></table></div><pre class="pay-detail"><?=h(json_encode(['payroll'=>$runSettings['payroll'],'payslip'=>$runSettings['payslip']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre></details>



