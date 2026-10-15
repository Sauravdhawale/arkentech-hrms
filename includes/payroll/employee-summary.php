<?php
// Caller must authorize this employee ID. This partial never reads employee IDs from the URL.
require_once __DIR__.'/config.php';
if(!isset($payEmployeeId)||!pay_ready($pdo)){echo '<section class="panel card"><p>Payroll is not configured yet.</p></section>';return;}
$q=$pdo->prepare('SELECT * FROM hr_salary_assignments WHERE employee_id=? AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?) ORDER BY effective_from DESC,id DESC LIMIT 1');
$q->execute([(int)$payEmployeeId,date('Y-m-d'),date('Y-m-d')]);$salary=$q->fetch(PDO::FETCH_ASSOC);
?>
<section class="panel card pay-card"><div class="pay-section-head"><div><span class="eyebrow">SALARY STRUCTURE</span><h2>Current salary</h2></div></div>
<?php if(!$salary):?><p>No current salary structure has been assigned. HR can set an effective salary from this employee’s Edit Profile page.</p>
<?php else:$salarySnapshot=json_decode($salary['snapshot'],true)?:[];?>
<p><?=h($salarySnapshot['structure_name']??'Salary structure')?> · Effective <?=h($salary['effective_from'])?> · Last revised <?=h($salary['created_at'])?> · Active</p>
<div class="pay-grid"><div>Monthly gross<strong class="pay-stat"><?=pay_amount((int)$salary['monthly_gross'])?></strong></div><div>Annual CTC<strong class="pay-stat"><?=pay_amount((int)$salary['annual_ctc'])?></strong></div></div>
<div class="table-scroll pay-table"><table><thead><tr><th>Component</th><th>Type</th><th>Monthly</th><th>Annualized</th></tr></thead><tbody>
<?php foreach($salarySnapshot['breakup']['components']??[] as $part):?><tr><td><?=h($part['name']??$part['code']??'Component')?></td><td><?=h($part['category']??'')?></td><td><?=pay_amount((int)$part['amount'])?></td><td><?=pay_amount((int)$part['amount']*12)?></td></tr><?php endforeach;?></tbody></table></div>
<p class="pay-muted">Annualized component amounts are monthly amounts × 12. Published payslips reflect the month’s attendance and approved adjustments.</p>
<?php endif;?></section>
<?php
$q=$pdo->prepare('SELECT s.*,u.name AS changed_by FROM hr_salary_assignments s JOIN users u ON u.id=s.created_by WHERE s.employee_id=? ORDER BY s.effective_from DESC,s.id DESC');$q->execute([(int)$payEmployeeId]);$history=$q->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="panel card pay-card"><h2>Salary history</h2><div class="table-scroll pay-table"><table><thead><tr><th>Effective from</th><th>Effective to</th><th>Monthly salary</th><th>Annual CTC</th><th>Changed by</th><th>Reason</th><th>Status</th></tr></thead><tbody>
<?php foreach($history as $revision):?><tr><td><?=h($revision['effective_from'])?></td><td><?=h($revision['effective_to']??'Until next revision')?></td><td><?=pay_amount((int)$revision['monthly_gross'])?></td><td><?=pay_amount((int)$revision['annual_ctc'])?></td><td><?=h($revision['changed_by'])?></td><td><?=h($revision['reason'])?></td><td><?=$revision['effective_from']>date('Y-m-d')?'Upcoming':(($revision['effective_to']&&$revision['effective_to']<date('Y-m-d'))?'Previous':'Current')?></td></tr><?php endforeach;if(!$history):?><tr><td colspan="7">No salary saved yet.</td></tr><?php endif;?></tbody></table></div></section>


