<?php
// Presentation only: all monetary values come from the run's stored calculation.
function pay_template_fields():array {
 return ['logo','fname','company_name','company_address','company_logo','employee_name','employee_id','department','designation','joining_date','pay_month','payslip_number','gross_salary','total_deductions','net_salary','net_salary_words','working_days','present_days','absent_days','paid_leave','unpaid_leave','overtime_hours','bank_account','pan','earnings_table','deductions_table','earnings_annual_table','deductions_annual_table','attendance_table','authorized_signatory','footer_note'];
}
function pay_template_attributes(array $rows):array {
 if(count($rows)>40)throw new InvalidArgumentException('Use at most 40 custom attributes.');
 $out=[];foreach($rows as $row){
  $name=trim((string)($row['name']??''));$value=trim((string)($row['value']??''));if($name===''&&$value==='')continue;
  if(!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$name)||in_array($name,pay_template_fields(),true)||isset($out[$name]))throw new InvalidArgumentException('Use unique lowercase attribute names; built-in names are reserved.');
  $source=(string)($row['source']??'text');if(!in_array($source,['text','field','component'],true)||strlen($value)>500)throw new InvalidArgumentException('Invalid attribute source or value.');
  if($source==='field'&&(!in_array($value,pay_template_fields(),true)||in_array($value,['logo','company_logo','earnings_table','deductions_table','earnings_annual_table','deductions_annual_table','attendance_table'],true)))throw new InvalidArgumentException('Choose a scalar field from the available attributes.');
  if($source==='component'&&!preg_match('/^[A-Z][A-Z0-9_]{0,39}$/D',$value))throw new InvalidArgumentException('Enter the exact salary component code, for example BASIC.');
  $out[$name]=['name'=>$name,'source'=>$source,'value'=>$value];
 }return array_values($out);
}
function pay_template_clean(string $html):string {
 if(strlen($html)>100000)throw new InvalidArgumentException('HTML template must be smaller than 100 KB.');
 if(trim($html)==='')throw new InvalidArgumentException('Paste an HTML template or choose a starter layout.');
 $dom=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
 try{$dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
 $tags=explode(' ','div section header footer main article p span strong b em i u small h1 h2 h3 h4 br hr table thead tbody tfoot tr th td ul ol li');
 $props=explode(' ','color background-color font-family font-size font-weight font-style text-align text-transform text-decoration line-height letter-spacing padding padding-top padding-bottom padding-left padding-right margin margin-top margin-bottom margin-left margin-right border border-top border-bottom border-left border-right border-color border-width border-style border-radius border-collapse width max-width min-width height vertical-align display grid-template-columns gap');
 $walk=function(DOMNode $parent)use(&$walk,$tags,$props){foreach(iterator_to_array($parent->childNodes) as $node){
  if($node instanceof DOMComment){$parent->removeChild($node);continue;}
  if(!($node instanceof DOMElement))continue;
  if(!in_array(strtolower($node->tagName),$tags,true))throw new InvalidArgumentException('Unsupported HTML tag: '.$node->tagName.'. Use a body fragment with inline styles; scripts, links, images and style blocks are not allowed. Use [company_logo] for the logo.');
  foreach(iterator_to_array($node->attributes) as $attr){
   if($attr->name==='style'){
    $safe=[];foreach(explode(';',$attr->value) as $rule){if(trim($rule)==='')continue;[$key,$value]=array_pad(explode(':',$rule,2),2,'');$key=strtolower(trim($key));$value=trim($value);
     if(!in_array($key,$props,true)||!preg_match('/^[a-zA-Z0-9#.,%\s\-]+$/D',$value))throw new InvalidArgumentException('Unsupported inline style: '.$key.'. Use simple colors, sizes and spacing without URLs or functions.');
     $safe[]=$key.':'.$value;
    }$node->setAttribute('style',implode(';',$safe));
   }elseif(in_array($attr->name,['colspan','rowspan'],true)&&preg_match('/^[1-9][0-9]?$/D',$attr->value)){}else throw new InvalidArgumentException('Unsupported HTML attribute: '.$attr->name.'. Use inline style for design and tokens in text.');
  }$walk($node);
 }};
 $body=$dom->getElementsByTagName('body')->item(0);$walk($body);$out='';foreach($body->childNodes as $node)$out.=$dom->saveHTML($node);return $out;
}
function pay_template_config(array $in):array {
 $mode=(string)($in['template_mode']??'standard');if(!in_array($mode,['standard','html'],true))throw new InvalidArgumentException('Choose a valid payslip layout.');
 $attributes=pay_template_attributes((array)($in['attributes']??[]));$html=trim((string)($in['html_template']??''));
 if($html!==''||$mode==='html'){$html=pay_template_clean($html);preg_match_all('/\[([a-z][a-z0-9_]*)\]/',$html,$matches);$unknown=array_diff($matches[1],array_merge(pay_template_fields(),array_column($attributes,'name')));if($unknown)throw new InvalidArgumentException('Unknown attribute: '.implode(', ',array_unique($unknown)));}
 return ['template_mode'=>$mode,'html_template'=>$html,'attributes'=>$attributes];
}
function pay_template_starters():array {
 $body='<p>[company_logo]</p><h1>[company_name]</h1><p>[company_address]</p><h2>Payslip · [pay_month]</h2><p>[payslip_number]</p><table><tr><th>Employee</th><td>[employee_name]</td><th>Employee ID</th><td>[employee_id]</td></tr><tr><th>Department</th><td>[department]</td><th>Designation</th><td>[designation]</td></tr></table><h3>Earnings</h3>[earnings_table]<h3>Deductions</h3>[deductions_table]<h2>Net pay: [net_salary]</h2><p>[net_salary_words]</p><h3>Attendance</h3>[attendance_table]<p>[footer_note]</p><p>Authorized signatory: [authorized_signatory]</p>';
 return ['Earnings Statement'=>'<section style="border:1px solid #ddd;padding:24px"><header style="border-bottom:3px solid #B12625"><h1>[company_name]</h1><p>PAYSLIP · [pay_month]</p></header><h2>[employee_name]</h2><p>[employee_id] · [department] · [designation]</p><h3>Earnings including House Rent Allowance</h3>[earnings_table]<h3>Approved deductions and recoveries</h3>[deductions_table]<h2>Net payable: [net_salary]</h2><p>[net_salary_words]</p><p>[authorized_signatory]</p><small>[footer_note]</small></section>','Annexure A'=>'<section style="border:1px solid #222;padding:16px"><h2 style="text-align:center">ANNEXURE A</h2><table><tr><th>Employee name</th><td>[employee_name]</td></tr><tr><th>Department</th><td>[department]</td></tr><tr><th>Designation</th><td>[designation]</td></tr></table><h3>A · Earnings</h3>[earnings_annual_table]<h3>B · Deductions</h3>[deductions_annual_table]<table><tr><th>Gross earnings</th><td>[gross_salary]</td></tr><tr><th>Total deductions</th><td>[total_deductions]</td></tr><tr><th>Net pay</th><td>[net_salary]</td></tr></table><p>[net_salary_words]</p><small>Period: [pay_month]. Annualized figures are this payslip amount multiplied by 12, for reference only; they are not contracted annual salary.</small><p>[footer_note]</p></section>','Classic'=>'<section style="border-top:4px solid #B12625;padding:24px">'.$body.'</section>','Compact'=>'<section style="font-size:12px;padding:16px">'.$body.'</section>','Statement'=>'<header style="background-color:#f5eeee;padding:24px"><h1>[company_name]</h1><p>Salary statement · [pay_month]</p></header><section style="padding:24px"><h2>[employee_name]</h2><p>[employee_id] · [department] · [designation]</p>[earnings_table][deductions_table]<h2 style="color:#B12625">Net salary [net_salary]</h2><p>[net_salary_words]</p><p>[payslip_number] · [authorized_signatory]</p><p>[footer_note]</p></section>'];
}
function pay_template_render(array $entry,array $settings,array $snapshot,array $components):string {
 $o=$settings['payslip'];$company=$settings['company']??[];$e=$snapshot['employee']??[];$a=$snapshot['attendance']??[];
 $esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $money=function($n)use($settings){$p=$settings['payroll'];$v=pay_amount((int)$n);return ($p['currency_position']??'Before')==='After'?$v.' '.$p['currency']:$p['currency'].' '.$v;};
 $v=['company_name'=>$company['name']??'','company_address'=>!empty($o['show_address'])?($company['address']??''):'','employee_name'=>$e['name']??'','employee_id'=>$e['employee_code']??'','department'=>$e['department_name']??'','designation'=>$e['designation_name']??'','joining_date'=>$e['joining_date']??'','pay_month'=>$entry['month'],'payslip_number'=>$entry['payslip_number'],'gross_salary'=>$money($entry['gross']),'total_deductions'=>$money($entry['deductions']),'net_salary'=>$money($entry['net']),'net_salary_words'=>pay_words((int)$entry['net']).' '.$settings['payroll']['currency'],'bank_account'=>!empty($o['show_bank'])?'****'.($snapshot['bank_last4']??''):'','pan'=>!empty($o['show_pan'])?'****'.($snapshot['pan_last4']??''):'','authorized_signatory'=>$o['signatory']??'','footer_note'=>$o['footer']??''];
 foreach(['working_days'=>'working_days','present_days'=>'present','absent_days'=>'absent','paid_leave'=>'paid_leave','unpaid_leave'=>'unpaid_leave','overtime_hours'=>'approved_overtime_hours'] as $key=>$source)$v[$key]=!empty($o['show_attendance'])?($a[$source]??0):'';
 $v['fname']=$e['name']??'';
 foreach(pay_template_attributes($o['attributes']??[]) as $attr){$value=$attr['value'];if($attr['source']==='field')$value=$v[$value]??'';elseif($attr['source']==='component'){$sum=0;foreach($components as $c)if(($c['code']??'')===$value)$sum+=(int)$c['amount'];$value=$money($sum);}$v[$attr['name']]=$value;}
 $v=array_map($esc,$v);$v['company_logo']='';$logo=$company['logo']??'';if(!empty($o['show_logo'])&&preg_match('~^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$~D',$logo))$v['company_logo']='<img alt="Company logo" style="max-width:150px;max-height:75px" src="'.$esc($logo).'">';
 foreach(['earnings_table'=>'Earning','deductions_table'=>'Deduction'] as $key=>$category){$rows='';foreach($components as $c)if($c['category']===$category)$rows.='<tr><td>'.$esc($c['name']??$c['code']).'</td><td>'.$esc($money($c['amount'])).'</td></tr>';$v[$key]='<table><thead><tr><th>'.($category==='Earning'?'Earnings':'Deductions').'</th><th>Amount</th></tr></thead><tbody>'.$rows.'</tbody></table>';}
 foreach(['earnings_annual_table'=>'Earning','deductions_annual_table'=>'Deduction'] as $key=>$category){$rows='';$total=0;foreach($components as $c)if($c['category']===$category){$amount=(int)$c['amount'];$total+=$amount;$rows.='<tr><td>'.$esc($c['name']??$c['code']).'</td><td>'.$esc($money($amount)).'</td><td>'.$esc($money($amount*12)).'</td></tr>';}$v[$key]='<table><thead><tr><th>Component</th><th>This month</th><th>Annualized reference</th></tr></thead><tbody>'.$rows.'</tbody><tfoot><tr><th>Total</th><td>'.$esc($money($total)).'</td><td>'.$esc($money($total*12)).'</td></tr></tfoot></table>';}
 $v['attendance_table']='';if(!empty($o['show_attendance'])){$rows='';foreach(['working_days','present_days','absent_days','paid_leave','unpaid_leave','overtime_hours'] as $key)$rows.='<tr><th>'.$esc(ucwords(str_replace('_',' ',$key))).'</th><td>'.$v[$key].'</td></tr>';$v['attendance_table']='<table>'.$rows.'</table>';}
 $v['logo']=$v['company_logo'];
 // Single pass prevents employee/custom text being interpreted as another token.
 return preg_replace_callback('/\[([a-z][a-z0-9_]*)\]/',fn($m)=>$v[$m[1]]??'',pay_template_clean($o['html_template']));
}
function pay_template_document(string $body,string $title='Payslip',string $extraCss=''):string {
 return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'; base-uri \'none\'; form-action \'none\'"><title>'.htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</title><style>body{font:14px Arial,sans-serif;color:#20252b;background:white;margin:0;padding:20px;overflow-wrap:anywhere}main{max-width:850px;margin:auto}table{width:100%;border-collapse:collapse;margin:16px 0}th,td{border:1px solid #ddd;padding:9px;text-align:left}h1{font-size:24px}h2{font-size:19px}h3{font-size:15px}tr{break-inside:avoid}@page{size:A4;margin:12mm}@media print{body{padding:0}}'.$extraCss.'</style></head><body><main>'.$body.'</main></body></html>';
}



