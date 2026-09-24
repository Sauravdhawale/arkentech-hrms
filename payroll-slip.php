<?php
require __DIR__.'/includes/access.php';$user=require_user();
require_once __DIR__.'/includes/foundation/core.php';require_once __DIR__.'/includes/payroll/config.php';
$db=db();header('Cache-Control: no-store, private');
if(!pay_ready($db)){http_response_code(404);exit('Payroll is not enabled.');}
$id=(int)($_GET['entry']??0);$all=can($db,$user,'payslip.view');
if(!$id){
 $q=$db->prepare('SELECT e.id,e.payslip_number,e.net,r.month FROM hr_payroll_entries e JOIN hr_payroll_runs r ON r.id=e.run_id WHERE e.employee_id=? AND e.published_at IS NOT NULL ORDER BY r.month DESC');$q->execute([$user['id']]);?>
 <!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My payslips</title><link rel="stylesheet" href="assets/foundation.css"><main><h1>My payslips</h1><p><a href="index.php">Back to workspace</a></p><section class="card"><?php $found=false;foreach($q as $row){$found=true;echo '<p><a href="?entry='.$row['id'].'">'.h($row['month'].' · '.$row['payslip_number']).'</a> · Net '.pay_amount((int)$row['net']).'</p>';}if(!$found)echo '<p>No published payroll payslips yet.</p>';?></section></main></html><?php exit;
}
$q=$db->prepare('SELECT e.*,r.month,r.settings_snapshot FROM hr_payroll_entries e JOIN hr_payroll_runs r ON r.id=e.run_id WHERE e.id=? AND e.published_at IS NOT NULL AND r.status IN ("Finalized","Paid")');$q->execute([$id]);$entry=$q->fetch(PDO::FETCH_ASSOC);
if(!$entry||(!$all&&(int)$entry['employee_id']!==(int)$user['id'])){http_response_code(404);exit('Payslip not found.');}
$s=json_decode($entry['snapshot'],true);$settings=json_decode($entry['settings_snapshot'],true);$company=$settings['company'];$options=$settings['payslip'];$e=$s['employee'];$currency=$settings['payroll']['currency'];
function slip_money(int $amount,array $settings):string{$v=pay_amount($amount);$p=$settings['payroll'];return $p['currency_position']==='After'?$v.' '.$p['currency']:$p['currency'].' '.$v;}
$visible=array_values(array_filter($s['components'],fn($c)=>!empty($c['visible'])));
// Hidden components are aggregated so the printed line totals still reconcile.
foreach(['Earning','Deduction','Employer'] as $category){$hidden=0;foreach($s['components'] as $c)if($c['category']===$category&&empty($c['visible']))$hidden+=$c['amount'];if($hidden)$visible[]=['name'=>'Other '.strtolower($category).' components','category'=>$category,'amount'=>$hidden];}
if(($_GET['download']??'')==='pdf'){
 require __DIR__.'/includes/payroll/pdf.php';$lines=[$company['name'],'PAYSLIP '.$entry['payslip_number'].' | '.$entry['month'],$e['name'].' | '.$e['employee_code'],'Department: '.($e['department_name']??'').' | Designation: '.($e['designation_name']??'')];
 if($options['show_address'])$lines=array_merge($lines,explode("\n",wordwrap($company['address'],90)));
 $lines[]='';foreach($visible as $c)$lines[]=$c['category'].' | '.$c['name'].' | '.slip_money($c['amount'],$settings);
 $lines[]='';$lines[]='Gross earnings: '.slip_money((int)$entry['gross'],$settings);$lines[]='Total deductions: '.slip_money((int)$entry['deductions'],$settings);$lines[]='Net pay: '.slip_money((int)$entry['net'],$settings);$lines[]=pay_words((int)$entry['net']).' '.$currency;
 if($options['show_attendance'])foreach(['working_days','present','absent','paid_leave','unpaid_leave','holidays','week_offs','approved_overtime_hours'] as $key)$lines[]=ucwords(str_replace('_',' ',$key)).': '.($s['attendance'][$key]??0);
 if($options['show_bank'])$lines[]='Bank account: ****'.($s['bank_last4']?:'Not entered');if($options['show_pan'])$lines[]='PAN: ****'.($s['pan_last4']?:'Not entered');
 $lines[]='Authorized signatory: '.$options['signatory'];$lines=array_merge($lines,explode("\n",wordwrap($options['footer'],90)));
 header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="payslip-'.$id.'.pdf"');echo pay_pdf($lines);exit;
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($entry['payslip_number'])?></title><link rel="stylesheet" href="assets/foundation.css"><link rel="stylesheet" href="assets/payroll.css"><style>.slip{max-width:900px;margin:35px auto;padding:35px}.slip-logo{max-width:150px;max-height:75px}.slip table{min-width:0}@media print{.slip{margin:0;padding:10px;max-width:none}}</style></head><body><main class="slip card">
<div class="pay-actions"><a class="button" href="?">My payslips</a><button class="button primary" onclick="window.print()">Print / Save branded PDF</button><a class="button" href="?entry=<?=$id?>&download=pdf">Download text PDF</a></div>
<?php if($options['show_logo']&&$company['logo']):?><img class="slip-logo" src="<?=h($company['logo'])?>" alt="Company logo"><?php endif;?>
<h1><?=h($company['name'])?></h1><?php if($options['show_address']):?><p><?=h($company['address'])?></p><?php endif;?>
<h2>Payslip · <?=h($entry['month'])?></h2><p><?=h($entry['payslip_number'])?> · <?=h($entry['status'])?></p>
<div class="pay-grid"><p><strong><?=h($e['name'])?></strong><br>Employee ID: <?=h($e['employee_code'])?></p><p>Department: <?=h($e['department_name']??'—')?><br>Designation: <?=h($e['designation_name']??'—')?></p></div>
<?php if($options['show_bank']):?><p>Bank account: ****<?=h($s['bank_last4']?:'Not entered')?></p><?php endif;if($options['show_pan']):?><p>PAN: ****<?=h($s['pan_last4']?:'Not entered')?></p><?php endif;?>
<div class="pay-table"><table><thead><tr><th>Component</th><th>Type</th><th class="pay-money">Amount</th></tr></thead><tbody><?php foreach($visible as $c):?><tr><td><?=h($c['name'])?></td><td><?=h($c['category'])?></td><td class="pay-money"><?=h(slip_money($c['amount'],$settings))?></td></tr><?php endforeach;?></tbody></table></div>
<div class="pay-grid"><p>Gross earnings<strong class="pay-stat"><?=h(slip_money((int)$entry['gross'],$settings))?></strong></p><p>Total deductions<strong class="pay-stat"><?=h(slip_money((int)$entry['deductions'],$settings))?></strong></p><p>Net pay<strong class="pay-stat"><?=h(slip_money((int)$entry['net'],$settings))?></strong></p></div><p><strong><?=h(pay_words((int)$entry['net']).' '.$currency)?></strong></p>
<?php if($options['show_attendance']):?><h3>Attendance summary</h3><div class="pay-grid"><?php foreach(['working_days','present','absent','paid_leave','unpaid_leave','holidays','week_offs','approved_overtime_hours'] as $key):?><p><?=h(ucwords(str_replace('_',' ',$key)))?>: <?=h((string)($s['attendance'][$key]??0))?></p><?php endforeach;?></div><?php endif;?>
<p><?=h($options['footer'])?></p><p>Authorized signatory: <?=h($options['signatory'])?></p></main></body></html>
