<?php if(($user['role']??'')!=='super_admin')return;
$datePreview=$_SESSION['pay_setup_date_preview']??null;
if($datePreview&&(int)$datePreview['actor']!==(int)$user['id'])$datePreview=null;
$dateEmployees=$pdo->query("SELECT DISTINCT u.id,u.name,e.employee_code FROM users u JOIN employees e ON e.user_id=u.id JOIN hr_salary_assignments s ON s.employee_id=u.id WHERE u.active=1 AND e.employment_status='Active' AND e.deleted_at IS NULL ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
?>
<details class="card" id="payroll-start-date" <?= $datePreview?'open':'' ?>><summary>Correct initial payroll start date</summary>
<p>Use this only when payroll was first set up too late. Extend the initial company rules and one employee’s initial salary to an earlier month. Amounts, later salary revisions, attendance and saved payslips stay unchanged.</p>
<?php pay_form('pay_setup_date_preview'); ?>
<div class="pay-grid"><label>Employee<select name="employee_id" required><option value="">Choose employee</option><?php foreach($dateEmployees as $e):?><option value="<?=(int)$e['id']?>"><?=h($e['name'].' · '.$e['employee_code'])?></option><?php endforeach;?></select></label>
<label>Start payroll from<input type="date" name="effective_from" required></label>
<label>Reason for correction<input name="reason" maxlength="1000" required placeholder="Why should the initial setup cover this earlier month?"></label></div>
<button class="button">Preview date correction</button></form>
<?php if($datePreview):$dp=$datePreview['plan'];?>
<h3>Review date correction</h3><p><strong><?=h($dp['employee']['name'].' · '.$dp['employee']['code'])?></strong> — monthly gross ₹<?=h(pay_amount((int)$dp['salary']['monthly_gross']))?> stays the same.</p>
<div class="pay-table"><table><thead><tr><th>Record</th><th>Current start</th><th>Corrected start</th></tr></thead><tbody>
<?php foreach($dp['records'] as $r):?><tr><td><?=h($r['title'])?></td><td><?=h($r['before']['effective_from'])?></td><td><?=h($r['after']['effective_from'])?></td></tr><?php endforeach;?>
<tr><td><?=h($dp['employee']['name'])?> — initial salary</td><td><?=h($dp['salary']['effective_from'])?></td><td><?=h($dp['salary_from'])?></td></tr></tbody></table></div>
<p>Company rules are shared. This extends their availability for earlier payroll; only the selected employee’s salary start changes. No payslip is generated or published by this action.</p>
<?php pay_form('pay_setup_date_apply',['fingerprint'=>$dp['fingerprint'],'confirm'=>1]);?><button class="button primary">Save corrected start date</button></form>
<?php endif;?></details>
