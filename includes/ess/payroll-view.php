<?php
$payEmployeeId=(int)$user['id'];
require __DIR__.'/../payroll/employee-summary.php';
if(!pay_ready($pdo))return;
$q=$pdo->prepare("SELECT e.id,e.payslip_number,e.gross,e.deductions,e.net,r.month FROM hr_payroll_entries e JOIN hr_payroll_runs r ON r.id=e.run_id WHERE e.employee_id=? AND e.published_at IS NOT NULL AND e.status IN ('Finalized','Paid') ORDER BY r.month DESC,e.id DESC");
$q->execute([$payEmployeeId]);$mySlips=$q->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="panel"><div class="ess-section-heading"><div><span class="eyebrow">MY PAYROLL</span><h2>Published payslips</h2><p>Your finalized salary slips, ready to view or download.</p></div><a href="?page=profile">My profile</a></div>
<div class="table-scroll"><table><thead><tr><th>Month</th><th>Payslip</th><th>Gross</th><th>Deductions</th><th>Net pay</th><th>Download</th></tr></thead><tbody>
<?php foreach($mySlips as $slip):?><tr><td><?=h($slip['month'])?></td><td><?=h($slip['payslip_number'])?></td><td><?=pay_amount((int)$slip['gross'])?></td><td><?=pay_amount((int)$slip['deductions'])?></td><td><strong><?=pay_amount((int)$slip['net'])?></strong></td><td><a href="payroll-slip.php?entry=<?=(int)$slip['id']?>">View</a> · <a href="payroll-slip.php?entry=<?=(int)$slip['id']?>&amp;download=pdf">PDF</a></td></tr><?php endforeach;?>
<?php if(!$mySlips):?><tr><td colspan="6">No payslips published yet. They will appear here after HR publishes them.</td></tr><?php endif;?></tbody></table></div></section>


