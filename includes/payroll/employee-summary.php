<?php
// Caller must authorize this employee ID. This partial never reads employee IDs from the URL.
require_once __DIR__.'/config.php';
if(!isset($payEmployeeId)||!pay_ready($pdo)){echo '<section class="panel card"><p>Payroll is not configured yet.</p></section>';return;}
$q=$pdo->prepare('SELECT * FROM hr_salary_assignments WHERE employee_id=? AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?) ORDER BY effective_from DESC,id DESC LIMIT 1');
$q->execute([(int)$payEmployeeId,date('Y-m-d'),date('Y-m-d')]);$salary=$q->fetch(PDO::FETCH_ASSOC);
?>
<section class="panel card pay-card"><div class="pay-section-head"><div><span class="eyebrow">SALARY STRUCTURE</span><h2>Current salary</h2></div></div>
<?php if(!$salary):?><p>No current salary structure has been assigned. HR can set an effective salary from Payslips.</p>
<?php else:$salarySnapshot=json_decode($salary['snapshot'],true)?:[];?>
<p><?=h($salarySnapshot['structure_name']??'Salary structure')?> · Effective <?=h($salary['effective_from'])?></p>
<div class="pay-grid"><div>Monthly gross<strong class="pay-stat"><?=pay_amount((int)$salary['monthly_gross'])?></strong></div><div>Annual CTC<strong class="pay-stat"><?=pay_amount((int)$salary['annual_ctc'])?></strong></div></div>
<div class="table-scroll pay-table"><table><thead><tr><th>Component</th><th>Type</th><th>Monthly</th><th>Annualized</th></tr></thead><tbody>
<?php foreach($salarySnapshot['breakup']['components']??[] as $part):?><tr><td><?=h($part['name']??$part['code']??'Component')?></td><td><?=h($part['category']??'')?></td><td><?=pay_amount((int)$part['amount'])?></td><td><?=pay_amount((int)$part['amount']*12)?></td></tr><?php endforeach;?></tbody></table></div>
<p class="pay-muted">Annualized component amounts are monthly amounts × 12. Published payslips reflect the month’s attendance and approved adjustments.</p>
<?php endif;?></section>
