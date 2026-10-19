<?php
$payEmployeeId=(int)$user['id'];
require_once __DIR__.'/../payroll/config.php';
require_once __DIR__.'/../payroll/statement.php';
if(!pay_ready($pdo)){echo '<section class="panel"><h2>Payroll</h2><p>Payroll is not configured yet. Contact HR for help.</p></section>';return;}
$q=$pdo->prepare("SELECT e.id,e.payslip_number,e.gross,e.deductions,e.net,r.month,r.settings_snapshot FROM hr_payroll_entries e JOIN hr_payroll_runs r ON r.id=e.run_id WHERE e.employee_id=? AND e.published_at IS NOT NULL AND e.status IN ('Finalized','Paid') ORDER BY r.month DESC,e.id DESC");
$q->execute([$payEmployeeId]);$mySlips=$q->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="panel ess-payslips"><div class="ess-section-heading"><div><span class="eyebrow">MY PAYROLL</span><h2>Published payslips</h2><p>Choose a month below to view your published payslip or download its PDF.</p></div><a href="?page=profile">My profile</a></div>
<div class="table-scroll"><table><thead><tr><th>Month</th><th>Payslip</th><th>Gross</th><th>Deductions</th><th>Net pay</th><th>Actions</th></tr></thead><tbody>
<?php foreach($mySlips as $slip):$slipSettings=json_decode($slip['settings_snapshot'],true);?><tr><td data-label="Month"><strong><time datetime="<?=h($slip['month'])?>"><?=h(pay_statement_month($slip['month']))?></time></strong></td><td data-label="Payslip"><?=h($slip['payslip_number'])?></td><td data-label="Gross" class="ess-pay-amount"><?=h(pay_statement_money((int)$slip['gross'],$slipSettings))?></td><td data-label="Deductions" class="ess-pay-amount"><?=h(pay_statement_money((int)$slip['deductions'],$slipSettings))?></td><td data-label="Net pay" class="ess-pay-amount"><strong><?=h(pay_statement_money((int)$slip['net'],$slipSettings))?></strong></td><td data-label="Actions"><div class="ess-slip-actions"><a href="payroll-slip.php?entry=<?=(int)$slip['id']?>">View payslip</a><a class="primary" href="payroll-slip.php?entry=<?=(int)$slip['id']?>&amp;download=pdf" aria-label="Download <?=h(pay_statement_month($slip['month']))?> payslip PDF">Download PDF</a></div></td></tr><?php endforeach;?>
<?php if(!$mySlips):?><tr><td colspan="6">No payslips published yet. They will appear here after HR publishes them.</td></tr><?php endif;?></tbody></table></div></section>



<?php require __DIR__.'/../payroll/employee-summary.php'; ?>
