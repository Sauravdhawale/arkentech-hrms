<?php
/** Presentation only. Never recalculate or write a published payroll snapshot. */
function pay_statement_money(int $amount,array $settings):string {
 $p=$settings['payroll'];$value=number_format($amount/100,2,'.',',');
 return ($p['currency_position']??'Before')==='After'?$value.' '.$p['currency']:$p['currency'].' '.$value;
}
function pay_statement_month(string $month):string {
 $date=DateTimeImmutable::createFromFormat('!Y-m',$month);
 return $date&&$date->format('Y-m')===$month?$date->format('F Y'):$month;
}
function pay_statement_is_standard(array $options):bool {
 if(($options['template_mode']??'standard')!=='html'||empty($options['html_template']))return true;
 // Upgrade only the unmodified shipped Annexure, not an administrator's custom HTML.
 return pay_template_clean($options['html_template'])===pay_template_clean(pay_template_starters()['Annexure A']);
}
function pay_statement_logo(array $settings):string {
 if(empty($settings['payslip']['show_logo']))return '';
 $logo=(string)($settings['company']['logo']??'');
 if(preg_match('~^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$~D',$logo))return $logo;
 // Local brand asset only: never fetch remote URLs while producing a private payslip.
 $path=dirname(__DIR__,2).'/assets/arkentech-logo-print.jpg';
 return is_file($path)&&filesize($path)<=2000000?'data:image/jpeg;base64,'.base64_encode(file_get_contents($path)):'';
}
function pay_statement_data(array $entry,array $settings,array $snapshot,array $components):array {
 $o=$settings['payslip'];$e=$snapshot['employee']??[];$a=$snapshot['attendance']??[];
 $info=['Employee name'=>$e['name']??'—','Employee ID'=>$e['employee_code']??'—','Department'=>($e['department_name']??'')?:'—','Designation'=>($e['designation_name']??'')?:'—'];
 if(!empty($e['joining_date']))$info['Joining date']=$e['joining_date'];
 if(!empty($o['show_bank']))$info['Bank account']=!empty($snapshot['bank_last4'])?'****'.$snapshot['bank_last4']:'Not entered';
 if(!empty($o['show_pan']))$info['PAN']=!empty($snapshot['pan_last4'])?'****'.$snapshot['pan_last4']:'Not entered';
 $groups=['Earning'=>[],'Deduction'=>[],'Employer'=>[]];
 foreach($components as $c)if(isset($groups[$c['category']]))$groups[$c['category']][]=[(string)($c['name']??$c['code']??'Component'),pay_statement_money((int)$c['amount'],$settings)];
 $attendance=[];if(!empty($o['show_attendance']))foreach(['working_days'=>'Scheduled days','present'=>'Present days','absent'=>'Absent days','paid_leave'=>'Paid leave days','unpaid_leave'=>'Unpaid leave days','approved_overtime_hours'=>'Approved overtime hours'] as $key=>$label)$attendance[$label]=(string)($a[$key]??0);
 return ['company'=>(string)($settings['company']['name']??''),'address'=>!empty($o['show_address'])?(string)($settings['company']['address']??''):'','logo'=>pay_statement_logo($settings),'month'=>pay_statement_month($entry['month']),'number'=>(string)$entry['payslip_number'],'draft'=>str_starts_with((string)($entry['status']??''),'DRAFT'),'info'=>$info,'groups'=>$groups,'gross'=>pay_statement_money((int)$entry['gross'],$settings),'deductions'=>pay_statement_money((int)$entry['deductions'],$settings),'net'=>pay_statement_money((int)$entry['net'],$settings),'words'=>pay_words((int)$entry['net']).' '.$settings['payroll']['currency'],'attendance'=>$attendance,'attendance_note'=>'Attendance values are from the saved monthly payroll. Present days can include manual attendance outside scheduled days.','signatory'=>(string)($o['signatory']??''),'footer'=>(string)($o['footer']??'')];
}
function pay_statement_html(array $d):string {
 $esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 ob_start(); ?>
 <article class="salary-statement">
 <header class="statement-head"><div><?php if($d['logo']):?><img class="statement-logo" alt="Company logo" src="<?=$esc($d['logo'])?>"><?php endif;?><h1><?=$esc($d['company'])?></h1><?php if($d['address']):?><p class="statement-address"><?=nl2br($esc($d['address']))?></p><?php endif;?></div><div class="statement-period"><span>MONTHLY PAYSLIP</span><h2><?=$esc($d['month'])?></h2><p><?=$esc($d['number'])?></p><?php if($d['draft']):?><strong class="statement-draft">DRAFT — NOT FINAL</strong><?php endif;?></div></header>
 <dl class="statement-employee"><?php foreach($d['info'] as $label=>$value):?><div><dt><?=$esc($label)?></dt><dd><?=$esc($value)?></dd></div><?php endforeach;?></dl>
 <div class="statement-columns"><?php foreach(['Earning'=>['Earnings','Gross earnings',$d['gross']],'Deduction'=>['Deductions','Total deductions',$d['deductions']]] as $category=>$labels):?><section><h3><?=$labels[0]?></h3><table><thead><tr><th scope="col">Component</th><th scope="col" class="statement-amount">Amount</th></tr></thead><tbody><?php foreach($d['groups'][$category] as [$name,$amount]):?><tr><td><?=$esc($name)?></td><td class="statement-amount"><?=$esc($amount)?></td></tr><?php endforeach;if(!$d['groups'][$category]):?><tr><td colspan="2">No <?=strtolower($labels[0])?></td></tr><?php endif;?></tbody><tfoot><tr><th scope="row"><?=$labels[1]?></th><td class="statement-amount"><?=$esc($labels[2])?></td></tr></tfoot></table></section><?php endforeach;?></div>
 <section class="statement-net"><div><span>Net pay</span><h2><?=$esc($d['net'])?></h2></div><p><?=$esc($d['words'])?></p></section>
 <?php if($d['groups']['Employer']):?><section><h3>Employer contributions</h3><p class="statement-muted">Shown separately; not deducted from net pay.</p><table><?php foreach($d['groups']['Employer'] as [$name,$amount]):?><tr><td><?=$esc($name)?></td><td class="statement-amount"><?=$esc($amount)?></td></tr><?php endforeach;?></table></section><?php endif;?>
 <?php if($d['attendance']):?><section class="statement-attendance"><h3>Attendance summary</h3><dl><?php foreach($d['attendance'] as $label=>$value):?><div><dt><?=$esc($label)?></dt><dd><?=$esc($value)?></dd></div><?php endforeach;?></dl><p class="statement-muted"><?=$esc($d['attendance_note'])?></p></section><?php endif;?>
 <footer class="statement-footer"><div><strong>Private &amp; confidential</strong><p>This payslip records the published payroll amounts; it is not a payment receipt.</p><?php if($d['footer']):?><p><?=nl2br($esc($d['footer']))?></p><?php endif;?></div><?php if($d['signatory']):?><div><strong><?=$esc($d['signatory'])?></strong><p>Authorized signatory</p></div><?php endif;?></footer>
 </article>
 <?php return ob_get_clean();
}
function pay_statement_css():string {
 return '.salary-statement{font:12px/1.5 Arial,sans-serif;color:#202630}.statement-head{display:grid;grid-template-columns:1.2fr 1fr;gap:24px;border-bottom:3px solid #B12625;padding-bottom:20px}.statement-logo{max-width:140px;max-height:52px;object-fit:contain;object-position:left;margin-bottom:8px}.statement-head h1{font-size:19px;margin:0 0 4px}.statement-head h2{font-size:22px;margin:4px 0}.statement-head p{margin:3px 0;color:#596170}.statement-address{max-width:340px}.statement-period{text-align:right}.statement-period>span{letter-spacing:1.3px;color:#B12625;font-size:10px;font-weight:bold}.statement-draft{display:block;color:#B12625}.statement-employee{display:grid;grid-template-columns:1fr 1fr;gap:14px 24px;margin:20px 0;padding:16px;background:#f6f7f9}.salary-statement dt{font-size:10px;color:#596170;margin-bottom:3px}.salary-statement dd{margin:0;font-weight:bold;overflow-wrap:anywhere}.statement-columns{display:grid;grid-template-columns:1fr 1fr;gap:20px}.salary-statement h3{font-size:13px;margin:0 0 8px}.salary-statement table{margin:0 0 16px;font-size:11px}.salary-statement th,.salary-statement td{padding:9px 7px;border:0;border-bottom:1px solid #e1e4e8;vertical-align:top}.salary-statement thead{background:#f6f7f9;color:#596170}.salary-statement tfoot{font-weight:bold;background:#faf0f0}.salary-statement .statement-amount{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.statement-net{display:grid;grid-template-columns:1fr 1.2fr;align-items:center;gap:16px;background:#faf0f0;border-left:4px solid #B12625;padding:16px 18px;margin:8px 0 20px;break-inside:avoid}.statement-net span{text-transform:uppercase;font-size:10px;letter-spacing:1px;font-weight:bold;color:#8b1d1c}.statement-net h2{font-size:25px;margin:2px 0;color:#8b1d1c}.statement-net p{margin:0;text-align:right}.statement-attendance{margin-top:20px;break-inside:avoid}.statement-attendance dl{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:12px 0}.statement-attendance dl>div{border:1px solid #e1e4e8;padding:8px}.statement-attendance dd{font-size:15px}.statement-muted{color:#687180;font-size:10px}.statement-footer{border-top:1px solid #e1e4e8;margin-top:22px;padding-top:14px;display:flex;justify-content:space-between;gap:25px;color:#596170;font-size:10px;break-inside:avoid}.statement-footer p{margin:4px 0}.statement-footer>div:last-child{text-align:right}.statement-footer>div:first-child{text-align:left}.statement-footer>div{max-width:75%}@media screen and (max-width:540px){.statement-head,.statement-employee,.statement-columns,.statement-net{grid-template-columns:1fr}.statement-period,.statement-net p{text-align:left}.statement-attendance dl{grid-template-columns:repeat(2,1fr)}.statement-footer{display:block}.statement-footer>div{max-width:none}.statement-footer>div:last-child{text-align:left}}@media print{.salary-statement{-webkit-print-color-adjust:exact;print-color-adjust:exact}.statement-head{break-inside:avoid}.statement-columns>section{break-inside:avoid}thead{display:table-header-group}}';
}
