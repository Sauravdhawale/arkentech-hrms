<?php
require_once __DIR__.'/profile-salary.php';
$salaryInput=($error&&is_array($_POST['salary']??null))?$_POST['salary']:['salary_basis'=>'Monthly Gross','reason'=>'Initial salary','effective_from'=>pay_profile_default_date((string)($employee['joining_date']??$originalEmployee['joining_date']??''))];
$salaryChecked=$error&&!empty($_POST['setup_salary']);
?>
<link rel="stylesheet" href="assets/profile-salary.css?v=3">
<section class="card profile-salary" id="profile-salary"><div class="card-heading"><h2>Salary</h2><span class="badge">Optional</span></div>
<p class="hint">Add a monthly salary now or after creating the employee. Company rules calculate the breakup automatically.</p>
<label class="profile-salary-toggle"><input type="checkbox" name="setup_salary" value="1" id="setup-profile-salary" <?=$salaryChecked?'checked':''?>>Add salary for this employee</label>
<fieldset id="profile-salary-fields" <?=$salaryChecked?'':'disabled hidden'?>><?php require __DIR__.'/profile-salary-fields.php';?><p class="hint">After saving the employee, review and confirm the salary on the profile. It becomes active after confirmation.</p></fieldset>
</section>
<script src="assets/profile-salary.js?v=3" defer></script>
