<?php
$q=$pdo->prepare('SELECT * FROM hr_salary_assignments WHERE employee_id=? ORDER BY effective_from DESC,id DESC LIMIT 1');$q->execute([$id]);$latestSalary=$q->fetch(PDO::FETCH_ASSOC);
$salarySnapshot=$latestSalary?(json_decode($latestSalary['snapshot'],true)?:[]):[];
$salaryInput=['salary_basis'=>'Monthly Gross','reason'=>$latestSalary?'Salary revision':'Initial salary','effective_from'=>$latestSalary?date('Y-m-d'):($originalEmployee['joining_date']??''),'structure_id'=>$latestSalary['structure_id']??'','salary_amount'=>$latestSalary?pay_amount((int)$latestSalary['monthly_gross']):'','manual'=>$salarySnapshot['manual']??[],'employee_ot_rate'=>pay_amount((int)($salarySnapshot['ot_rate']??0)),'bank_last4'=>$salarySnapshot['bank_last4']??'','pan_last4'=>$salarySnapshot['pan_last4']??'','notes'=>$latestSalary['notes']??''];
$pending=$_SESSION['profile_salary_preview']??null;
if(!$pending||(int)$pending['actor']!==(int)$user['id']||(int)$pending['employee_id']!==$id)$pending=null;
$draft=$_SESSION['profile_salary_draft']??null;
if($draft&&(int)$draft['actor']===(int)$user['id']&&(int)$draft['employee_id']===$id){
 $salaryInput=$draft['input'];
 try{$pending=pay_profile_preview($pdo,$user,$id,$salaryInput);$_SESSION['profile_salary_preview']=$pending;unset($_SESSION['profile_salary_draft']);}catch(InvalidArgumentException $e){$salaryDraftError=$e->getMessage();}
}
if($pending)$salaryInput=$pending['input'];
if($error&&($_POST['action']??'')==='profile_salary_preview'){$salaryInput=$_POST['salary']??$salaryInput;$pending=null;}
?>
<link rel="stylesheet" href="assets/profile-salary.css?v=1">
<section class="card profile-salary" id="profile-salary">
 <div class="card-heading"><div><h2>Payroll / Salary</h2><p class="hint">Set salary here once. Employee Salaries and monthly payroll use the same record.</p></div><?php if(can($pdo,$user,'payroll.salary.view')):?><a class="button" href="?page=employee_salary&amp;employee_id=<?=$id?>">Salary history</a><?php endif;?></div>
 <?php if($latestSalary):$salaryState=$latestSalary['effective_from']>date('Y-m-d')?'Future':($latestSalary['effective_to']&&$latestSalary['effective_to']<date('Y-m-d')?'Ended':'Current');?>
 <div class="profile-salary-summary"><div><span>Latest salary · <?=h($salaryState)?></span><strong>₹<?=pay_amount((int)$latestSalary['monthly_gross'])?></strong><small>Monthly gross, before deductions</small></div><div><span>Salary structure</span><strong><?=h($salarySnapshot['structure_name']??'')?></strong></div><div><span>Effective period</span><strong><?=h($latestSalary['effective_from'])?></strong><small>Until <?=h($latestSalary['effective_to']?:'ongoing')?></small></div></div>
 <?php else:?><p class="hint">No salary assigned yet. Choose a structure and amount below.</p><?php endif;?>
 <?php if(isset($salaryDraftError)):?><div class="alert error"><?=h($salaryDraftError)?></div><?php endif;?>
 <details <?=($pending||isset($salaryDraftError)||!$latestSalary||($error&&str_starts_with($_POST['action']??'','profile_salary_')))?'open':''?>><summary><?=$latestSalary?'Revise salary':'Set employee salary'?></summary>
 <form method="post" action="?page=employee-edit&amp;id=<?=$id?>#profile-salary"><?php ftoken();?><input type="hidden" name="action" value="profile_salary_preview">
 <?php require __DIR__.'/profile-salary-fields.php';?>
 <div class="form-actions"><button class="button primary">Preview salary breakup</button></div></form>
 </details>
 <?php if($pending):$p=$pending['preview'];?>
 <section class="profile-salary-preview" aria-label="Salary preview"><h3>Review salary · <?=h($p['employee']['name'])?></h3><p><?=h($p['structure_name'])?> · <?=h($p['effective_from'])?> → <?=h($p['effective_to']?:'ongoing')?></p>
 <div class="table-scroll"><table><thead><tr><th>Component</th><th>Type</th><th>Monthly (₹)</th><th>Annualized (₹)</th></tr></thead><tbody><?php foreach($p['breakup']['components'] as $part):?><tr><td><?=h($part['name']??$part['code'])?></td><td><?=h($part['category'])?></td><td><?=pay_amount((int)$part['amount'])?></td><td><?=pay_amount((int)$part['amount']*12)?></td></tr><?php endforeach;?></tbody></table></div>
 <div class="profile-salary-summary"><div><span>Monthly gross</span><strong>₹<?=pay_amount($p['breakup']['gross'])?></strong></div><div><span>Monthly net estimate</span><strong>₹<?=pay_amount($p['breakup']['net'])?></strong></div><div><span>Annual CTC</span><strong>₹<?=pay_amount($p['breakup']['annual_ctc'])?></strong></div></div>
 <p class="hint">This is the salary breakup, not a payslip. Monthly pay uses attendance and approved adjustments. Existing payslips remain unchanged.</p>
 <form method="post" action="?page=employee-edit&amp;id=<?=$id?>#profile-salary"><?php ftoken();?><input type="hidden" name="action" value="profile_salary_save"><input type="hidden" name="salary_preview_token" value="<?=h($pending['token'])?>">
 <label class="profile-salary-toggle"><input type="checkbox" name="confirm" value="1" required>I reviewed this breakup and effective period</label><div class="form-actions"><button class="button primary">Confirm & save salary</button></div></form>
 </section>
 <?php endif;?>
</section>
