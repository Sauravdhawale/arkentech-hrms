<?php $salaryInput=($error&&is_array($_POST['salary']??null))?$_POST['salary']:['salary_basis'=>'Monthly Gross','reason'=>'Initial salary'];$salaryChecked=$error&&!empty($_POST['setup_salary']);?>
<link rel="stylesheet" href="assets/profile-salary.css?v=1">
<section class="card profile-salary" id="profile-salary"><div class="card-heading"><h2>Payroll / Salary</h2><span class="badge">Optional setup</span></div>
<p class="hint">Enter the agreed salary here. Save the employee first, then review and confirm the breakup on this profile. Salary becomes active only after confirmation.</p>
<label class="profile-salary-toggle"><input type="checkbox" name="setup_salary" value="1" id="setup-profile-salary" <?=$salaryChecked?'checked':''?>>Set up salary for this employee</label>
<fieldset id="profile-salary-fields" <?=$salaryChecked?'':'disabled hidden'?>><?php require __DIR__.'/profile-salary-fields.php';?></fieldset>
</section>
<script src="assets/profile-salary.js?v=1" defer></script>

