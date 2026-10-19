<?php
require __DIR__.'/includes/access.php';$user=require_user();
require_once __DIR__.'/includes/foundation/core.php';require_once __DIR__.'/includes/payroll/config.php';
$db=db();header('Cache-Control: no-store, private');
if(!pay_ready($db)){http_response_code(404);exit('Payroll is not enabled.');}
$id=(int)($_GET['entry']??0);$all=can($db,$user,'payslip.view');
$printRequested=($_GET['print']??'')==='1';
if(!$id){
 $q=$db->prepare('SELECT e.id,e.payslip_number,e.net,r.month FROM hr_payroll_entries e JOIN hr_payroll_runs r ON r.id=e.run_id WHERE e.employee_id=? AND e.published_at IS NOT NULL ORDER BY r.month DESC');$q->execute([$user['id']]);?>
 <!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My payslips</title><link rel="stylesheet" href="assets/foundation.css"><?php require __DIR__.'/includes/theme-assets.php';?><body class="shrms-ui"><main><h1>My payslips</h1><p><a href="index.php">Back to workspace</a></p><section class="card"><?php $found=false;foreach($q as $row){$found=true;echo '<p><a href="?entry='.$row['id'].'">'.h($row['month'].' · '.$row['payslip_number']).'</a> · Net '.pay_amount((int)$row['net']).'</p>';}if(!$found)echo '<p>No published payroll payslips yet.</p>';?></section></main></html><?php exit;
}
$q=$db->prepare('SELECT e.*,r.month,r.status AS run_status,r.settings_snapshot FROM hr_payroll_entries e JOIN hr_payroll_runs r ON r.id=e.run_id WHERE e.id=?');$q->execute([$id]);$entry=$q->fetch(PDO::FETCH_ASSOC);
$draft=$entry&&(!pay_entry_published($entry));
$draftAllowed=$draft&&can($db,$user,'payroll.process.view')&&$entry['exceptions']===''&&!empty(json_decode($entry['snapshot'],true)['draft_generated_at']);
if(!$entry||($draft&&!$draftAllowed)||(!$all&&!$draftAllowed&&(int)$entry['employee_id']!==(int)$user['id'])){http_response_code(404);exit('Payslip not found.');}
if($draft){$entry['payslip_number']='DRAFT-'.$id;$entry['status']='DRAFT — NOT FINAL';}
$s=json_decode($entry['snapshot'],true);$settings=json_decode($entry['settings_snapshot'],true);$company=$settings['company'];$options=$settings['payslip'];$e=$s['employee'];$currency=$settings['payroll']['currency'];
function slip_money(int $amount,array $settings):string{$v=pay_amount($amount);$p=$settings['payroll'];return $p['currency_position']==='After'?$v.' '.$p['currency']:$p['currency'].' '.$v;}
$visible=array_values(array_filter($s['components'],fn($c)=>!empty($c['visible'])));
// Hidden components are aggregated so the printed line totals still reconcile.
foreach(['Earning','Deduction','Employer'] as $category){$hidden=0;foreach($s['components'] as $c)if($c['category']===$category&&empty($c['visible']))$hidden+=$c['amount'];if($hidden)$visible[]=['name'=>'Other '.strtolower($category).' components','category'=>$category,'amount'=>$hidden];}
require_once __DIR__.'/includes/payroll/statement.php';
$statement=pay_statement_data($entry,$settings,$s,$visible);
$standard=pay_statement_is_standard($options);
if(($_GET['download']??'')==='pdf'){
 require __DIR__.'/includes/payroll/statement-pdf.php';
 header('Content-Type: application/pdf');
 header('Content-Disposition: attachment; filename="payslip-'.preg_replace('/[^a-zA-Z0-9_-]/','-',(string)$entry['payslip_number']).'.pdf"');
 echo pay_statement_pdf($statement);exit;
}
// The preview and browser print use one document, including saved custom HTML.
if($standard)$body=pay_statement_html($statement);
else{
 $renderSettings=$settings;$renderSettings['company']['logo']=$statement['logo'];
 $body=($draft?'<h2 style="color:#b12625;text-align:center">DRAFT — NOT FINAL</h2>':'').pay_template_render($entry,$renderSettings,$s,$visible);
}
$document=pay_template_document($body,$entry['payslip_number'].' · '.$statement['month'],$standard?pay_statement_css():'');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($entry['payslip_number'])?></title><link rel="stylesheet" href="assets/payslip-document.css?v=1"><link rel="icon" href="assets/arkentech-logo.webp"></head>
<body class="payslip-viewer"><header class="payslip-toolbar"><div><a class="payslip-back" href="<?=$all?'super-admin.php?page=payroll_payslips&amp;month='.h($entry['month']):'employee.php?page=my_salary'?>">← Back to payslips</a><h1><?=h($statement['month'])?> payslip</h1><p><?=h($e['name'])?> · <?=h($entry['payslip_number'])?></p></div><div class="payslip-actions"><button type="button" data-print-payslip>Print / Save PDF</button><a class="payslip-download" href="?entry=<?=$id?>&amp;download=pdf">Download PDF</a></div></header>
<p class="payslip-help"><?=$standard?'Your saved monthly payroll, formatted for A4 paper.':'Preview uses your saved HTML design. Download PDF uses the standard monthly layout; choose Print / Save PDF to keep the custom design.'?> For browser printing, turn off “Headers and footers” for a clean page.</p>
<iframe id="payslip-document" title="Payslip document" sandbox="allow-same-origin allow-modals" srcdoc="<?=h($document)?>" <?=$printRequested?'data-print-on-load':''?>></iframe>
<script src="assets/payslip-document.js?v=1" defer></script></body></html>
