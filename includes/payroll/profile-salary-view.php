<?php
require_once __DIR__.'/profile-salary.php';
$q=$pdo->prepare('SELECT * FROM hr_salary_assignments WHERE employee_id=? ORDER BY effective_from DESC,id DESC LIMIT 1');$q->execute([$id]);$latestSalary=$q->fetch(PDO::FETCH_ASSOC);
$salarySnapshot=$latestSalary?(json_decode($latestSalary['snapshot'],true)?:[]):[];
$salaryInput=['salary_basis'=>'Monthly Gross','reason'=>$latestSalary?'Salary revision':'Initial salary','effective_from'=>pay_profile_default_date((string)($originalEmployee['joining_date']??''),$latestSalary['effective_from']??null),'structure_id'=>$latestSalary['structure_id']??'','salary_amount'=>$latestSalary?pay_amount((int)$latestSalary['monthly_gross']):'','manual'=>$salarySnapshot['manual']??[],'employee_ot_rate'=>pay_amount((int)($salarySnapshot['ot_rate']??0)),'bank_last4'=>$salarySnapshot['bank_last4']??'','pan_last4'=>$salarySnapshot['pan_last4']??'','notes'=>$latestSalary['notes']??''];
$pending=$_SESSION['profile_salary_preview']??null;
if(!$pending||(int)$pending['actor']!==(int)$user['id']||(int)$pending['employee_id']!==$id)$pending=null;
$draft=$_SESSION['profile_salary_draft']??null;
if($draft&&(int)$draft['actor']===(int)$user['id']&&(int)$draft['employee_id']===$id){
 $salaryInput=$draft['input'];
 try{$pending=pay_profile_preview($pdo,$user,$id,$salaryInput);$_SESSION['profile_salary_preview']=$pending;unset($_SESSION['profile_salary_draft']);}catch(InvalidArgumentException $e){$salaryDraftError=$e->getMessage();}
}
if($pending)$salaryInput=$pending['input'];
if($error&&in_array($_POST['action']??'',['profile_salary_preview','profile_salary_quick_save'],true)){$salaryInput=$_POST['salary']??$salaryInput;$pending=null;}
?>
<link rel="stylesheet" href="assets/profile-salary.css?v=3">
<section class="card profile-salary" id="profile-salary">
 <div class="card-heading"><div><h2>Salary</h2><p class="hint">Set the monthly amount here. Payroll combines it with attendance to calculate each month’s payslip.</p></div></div>
 <?php if($latestSalary):$salaryState=$latestSalary['effective_from']>date('Y-m-d')?'Future':($latestSalary['effective_to']&&$latestSalary['effective_to']<date('Y-m-d')?'Ended':'Current');?>
 <div class="profile-salary-summary"><div><span>Latest salary · <?=h($salaryState)?></span><strong>₹<?=pay_amount((int)$latestSalary['monthly_gross'])?></strong><small>Monthly gross, before deductions</small></div><div><span>Effective period</span><strong><?=h($latestSalary['effective_from'])?></strong><small>Until <?=h($latestSalary['effective_to']?:'ongoing')?></small></div></div>
 <?php else:?><p class="hint">No salary saved yet. Enter the monthly amount and when it starts.</p><?php endif;?>
 <?php if(isset($salaryDraftError)):?><div class="alert error"><?=h($salaryDraftError)?></div><?php endif;?>
 <div class="profile-salary-editor"><h3><?=$latestSalary?'Change salary':'Set employee salary'?></h3>
 <p class="hint">Enter the amount and start date, then save. Salary history stays below; monthly slips are in Payroll → Payslips.</p>
 <form method="post" action="?page=employee-edit&amp;id=<?=$id?>#profile-salary"><?php ftoken();?><input type="hidden" name="salary_previous_id" value="<?=(int)($latestSalary['id']??0)?>">
 <?php $simpleSalaryEditor=true; require __DIR__.'/profile-salary-fields.php'; unset($simpleSalaryEditor);?>
 <div class="form-actions"><button class="button primary" name="action" value="profile_salary_quick_save">Save salary</button></div></form>
 </div>
 <?php if($pending):$p=$pending['preview'];?>
 <section class="profile-salary-preview" aria-label="Salary preview">
<?php if($latestSalary):$salaryDifference=(int)$p['breakup']['gross']-(int)$latestSalary['monthly_gross'];?>
<div class="profile-salary-summary"><div><span>Previous monthly gross</span><strong>₹<?=pay_amount((int)$latestSalary['monthly_gross'])?></strong></div><div><span>New monthly gross</span><strong>₹<?=pay_amount((int)$p['breakup']['gross'])?></strong></div><div><span><?=($salaryDifference>=0?'Increase':'Decrease')?> per month</span><strong>₹<?=pay_amount(abs($salaryDifference))?></strong><small>From <?=h($p['effective_from'])?></small></div></div>
<?php endif;?><h3>Review salary · <?=h($p['employee']['name'])?></h3><p><?=h($p['structure_name'])?> · <?=h($p['effective_from'])?> → <?=h($p['effective_to']?:'ongoing')?></p>
 <div class="table-scroll"><table><thead><tr><th>Component</th><th>Type</th><th>Monthly (₹)</th><th>Annualized (₹)</th></tr></thead><tbody><?php foreach($p['breakup']['components'] as $part):?><tr><td><?=h($part['name']??$part['code'])?></td><td><?=h($part['category'])?></td><td><?=pay_amount((int)$part['amount'])?></td><td><?=pay_amount((int)$part['amount']*12)?></td></tr><?php endforeach;?></tbody></table></div>
 <div class="profile-salary-summary"><div><span>Monthly gross</span><strong>₹<?=pay_amount($p['breakup']['gross'])?></strong></div><div><span>Monthly net estimate</span><strong>₹<?=pay_amount($p['breakup']['net'])?></strong></div><div><span>Annual CTC</span><strong>₹<?=pay_amount($p['breakup']['annual_ctc'])?></strong></div></div>
 <p class="hint">This is the salary breakup, not a payslip. Monthly pay uses attendance and approved adjustments. Existing payslips remain unchanged.</p>
 <form method="post" action="?page=employee-edit&amp;id=<?=$id?>#profile-salary"><?php ftoken();?><input type="hidden" name="action" value="profile_salary_save"><input type="hidden" name="salary_preview_token" value="<?=h($pending['token'])?>">
 <label class="profile-salary-toggle"><input type="checkbox" name="confirm" value="1" required>I reviewed this breakup and effective period</label><div class="form-actions"><button class="button primary">Confirm & save salary</button></div></form>
 </section>
 <?php endif;?>
 <?php if(can($pdo,$user,'payroll.salary.view')):$historyQuery=$pdo->prepare('SELECT monthly_gross,effective_from,effective_to FROM hr_salary_assignments WHERE employee_id=? ORDER BY effective_from DESC,id DESC');$historyQuery->execute([$id]);$salaryHistory=$historyQuery->fetchAll(PDO::FETCH_ASSOC);?>
 <details class="profile-salary-history"><summary>Salary history</summary><div class="table-scroll"><table><thead><tr><th>Monthly salary</th><th>Change</th><th>Applies from</th><th>Applies until</th></tr></thead><tbody><?php foreach($salaryHistory as $historyIndex=>$history):$priorSalary=$salaryHistory[$historyIndex+1]??null;$salaryChange=$priorSalary?(int)$history['monthly_gross']-(int)$priorSalary['monthly_gross']:0;?><tr><td>₹<?=pay_amount((int)$history['monthly_gross'])?></td><td><?php if(!$priorSalary):?>Initial salary<?php elseif($salaryChange===0):?>No amount change<?php else:?><?=$salaryChange>0?'Increase':'Decrease'?> ₹<?=pay_amount(abs($salaryChange))?><?php endif;?></td><td><?=h($history['effective_from'])?></td><td><?=h($history['effective_to']?:'Ongoing')?></td></tr><?php endforeach;if(!$salaryHistory):?><tr><td colspan="4">No salary saved yet. Your initial salary and future increases will appear here.</td></tr><?php endif;?></tbody></table></div></details>
 <?php endif;?>
</section>


<script src="assets/profile-salary.js?v=3" defer></script>
