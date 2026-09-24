<?php
// Monetary values at rest are integer minor units (paise for INR).
function pay_money($value):int {
 $s=trim((string)$value);
 if(!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D',$s))throw new InvalidArgumentException('Enter a non-negative amount with at most two decimals.');
 [$whole,$part]=array_pad(explode('.',$s,2),2,'');return (int)$whole*100+(int)str_pad($part,2,'0');
}
function pay_amount(int $cents):string{return number_format($cents/100,2,'.','');}
function pay_number($v,float $max=1000000):float {
 if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>$max)throw new InvalidArgumentException('Enter a valid non-negative value.');return (float)$v;
}
class PayrollDependency extends InvalidArgumentException {}
/** Restricted arithmetic parser: identifiers, decimal numbers, + - * / and parentheses; no PHP execution. */
function pay_expression(string $expression,array $variables):float {
 if(strlen($expression)>500)throw new InvalidArgumentException('Formula is too long.');
 preg_match_all('/\d+(?:\.\d+)?|[A-Z][A-Z0-9_]*|[()+*\/-]/',$expression,$m);
 $tokens=$m[0];if(!$tokens||count($tokens)>128||implode('',$tokens)!==preg_replace('/\s+/','',$expression))throw new InvalidArgumentException('Use arithmetic, component codes and GROSS only.');
 $i=0;$sum=null;$product=null;$atom=null;
 $atom=function()use(&$atom,&$sum,&$i,$tokens,$variables):float{
  $t=$tokens[$i++]??'';if($t==='-'||$t==='+')return ($t==='-'?-1:1)*$atom();
  if($t==='('){$v=$sum();if(($tokens[$i++]??'')!==')')throw new InvalidArgumentException('Unbalanced formula.');return $v;}
  if(is_numeric($t))return (float)$t;
  if(preg_match('/^[A-Z][A-Z0-9_]*$/D',$t)){if(!array_key_exists($t,$variables))throw new PayrollDependency('Unresolved component '.$t);return (float)$variables[$t];}
  throw new InvalidArgumentException('Invalid formula.');
 };
 $product=function()use(&$atom,&$i,$tokens):float{$v=$atom();while(in_array($tokens[$i]??'', ['*','/'],true)){$op=$tokens[$i++];$b=$atom();if($op==='/'&&abs($b)<0.00000001)throw new InvalidArgumentException('Formula divides by zero.');$v=$op==='*'?$v*$b:$v/$b;}return $v;};
 $sum=function()use(&$product,&$i,$tokens):float{$v=$product();while(in_array($tokens[$i]??'',['+','-'],true)){$op=$tokens[$i++];$b=$product();$v=$op==='+'?$v+$b:$v-$b;}return $v;};
 $value=$sum();if($i!==count($tokens)||!is_finite($value)||abs($value)>1000000000)throw new InvalidArgumentException('Invalid or excessive formula result.');return $value;
}
function pay_breakup(array $structure,int $gross,array $manual=[],bool $reconcile=true):array {
 $components=$structure['components']??[];if(!$components)throw new InvalidArgumentException('Salary structure has no components.');
 $values=['GROSS'=>$gross/100];$result=[];$pending=$components;$balances=0;$codes=[];
 foreach($components as $c){
  if(isset($codes[$c['code']])||$c['code']==='GROSS')throw new InvalidArgumentException('Component codes must be unique; GROSS is reserved.');$codes[$c['code']]=true;
  if($c['calculation']==='Balance'){++$balances;if($c['category']!=='Earning'||empty($c['include_gross']))throw new InvalidArgumentException('Balancing component must be a gross earning.');}
 }
 if($balances>1)throw new InvalidArgumentException('Only one balancing component is allowed.');
 for($pass=0;$pending&&$pass<=count($components);$pass++){
  $progress=false;
  foreach($pending as $key=>$c){
   try{
    if(($c['section']??'Fixed')==='Variable')$amount=0;
    elseif($c['calculation']==='Fixed')$amount=pay_money($c['value']);
    elseif($c['calculation']==='Manual'){if(!array_key_exists($c['code'],$manual))throw new InvalidArgumentException('Enter manual amount for '.$c['code']);$amount=pay_money($manual[$c['code']]);}
    elseif($c['calculation']==='Percentage')$amount=(int)round(pay_expression($c['basis'],$values)*pay_number($c['value'],1000));
    elseif($c['calculation']==='Formula')$amount=(int)round(pay_expression($c['formula'],$values)*100);
    elseif($c['calculation']==='Balance'){
     $used=0;foreach($components as $other)if($other['code']!==$c['code']&&$other['category']==='Earning'&&!empty($other['include_gross'])&&($other['section']??'Fixed')!=='Variable'){
      if(!isset($result[$other['code']]))throw new PayrollDependency('Balancing waits for earnings.');$used+=$result[$other['code']]['amount'];
     }$amount=$gross-$used;
    }else throw new InvalidArgumentException('Unknown calculation type.');
   }catch(PayrollDependency $e){continue;}
   if($amount<0||$amount>100000000000)throw new InvalidArgumentException('Negative or excessive amount for '.$c['code']);
   $result[$c['code']]=array_merge($c,['amount'=>$amount]);$values[$c['code']]=$amount/100;unset($pending[$key]);$progress=true;
  }
  if(!$progress&&$pending)throw new InvalidArgumentException('Circular or missing component dependency: '.implode(', ',array_column($pending,'code')));
 }
 foreach(($structure['statutory']??[]) as $rule){
  if(isset($result[$rule['code']]))throw new InvalidArgumentException('Statutory rule duplicates salary component '.$rule['code']);
  if(!isset($values[$rule['basis']]))throw new InvalidArgumentException('Statutory basis is missing: '.$rule['basis']);
  if(!empty($rule['eligibility_limit'])&&$gross>(int)$rule['eligibility_limit'])continue;
  $basis=(int)round($values[$rule['basis']]*100);if(!empty($rule['wage_cap']))$basis=min($basis,(int)$rule['wage_cap']);
  foreach(['employee_value'=>'Deduction','employer_value'=>'Employer'] as $key=>$category){
   $amount=$rule['method']==='Fixed'?(int)round($rule[$key]*100):(int)round($basis*$rule[$key]/100);
   $code='STAT_'.$rule['code'].($category==='Deduction'?'_EE':'_ER');
   if(isset($result[$code]))throw new InvalidArgumentException('Reserved statutory component code.');
   $result[$code]=['code'=>$code,'name'=>$rule['name'].($category==='Employer'?' (employer)':''),'category'=>$category,'section'=>'Fixed','amount'=>$amount,'visible'=>true,'include_gross'=>false,'include_ctc'=>$category==='Employer','statutory'=>true,'rule'=>$rule];
  }
 }
 $earnings=0;$deductions=0;$employer=0;$ctc=0;$grossTotal=0;
 foreach($result as $c){
  if($c['category']==='Earning'){$earnings+=$c['amount'];if(!empty($c['include_gross']))$grossTotal+=$c['amount'];}
  elseif($c['category']==='Deduction')$deductions+=$c['amount'];else $employer+=$c['amount'];
  if($c['category']!=='Deduction'&&!empty($c['include_ctc']))$ctc+=$c['amount'];
 }
 if($reconcile&&abs($grossTotal-$gross)>1)throw new InvalidArgumentException('Gross components do not match Monthly Gross. Add a balancing component or correct the values.');
 if($deductions>$earnings)throw new InvalidArgumentException('Deductions exceed earnings.');
 return ['components'=>array_values($result),'gross'=>$grossTotal,'earnings'=>$earnings,'deductions'=>$deductions,'net'=>$earnings-$deductions,'employer'=>$employer,'annual_ctc'=>$ctc*12];
}
function pay_from_ctc(array $structure,int $annual,array $manual=[]):array {
 $low=0;$high=max($annual,100);$best=null;
 for($i=0;$i<60&&$low<=$high;$i++){
  $mid=intdiv($low+$high,2);
  try{$r=pay_breakup($structure,$mid,$manual,false);}catch(InvalidArgumentException $e){
   if(str_starts_with($e->getMessage(),'Negative or excessive amount')){$low=$mid+1;continue;}throw $e;
  }
  $diff=$r['annual_ctc']-$annual;
  if($best===null||abs($diff)<abs($best['annual_ctc']-$annual))$best=$r;
  if($diff<0)$low=$mid+1;elseif($diff>0)$high=$mid-1;else break;
 }
 if(!$best||abs($best['annual_ctc']-$annual)>12)throw new InvalidArgumentException('This structure cannot reconcile the entered annual CTC. Enter Monthly Gross or revise the structure.');
 return pay_breakup($structure,$best['gross'],$manual);
}
function pay_lop(array $days,array $settings,array $dailyBasis,int $denominator):int {
 if($denominator<=0)throw new InvalidArgumentException('Payroll has no denominator days. Review the schedule.');
 $loss=0.0;
 foreach($days as $d){$units=(float)$d['unpaid_leave'];if(($settings['lop_days']??'Absent + unpaid leave')==='Absent + unpaid leave')$units+=(float)$d['absent'];$loss+=($dailyBasis[$d['date']]??0)*min(1,$units)/$denominator;}
 return (int)round($loss);
}
function pay_words(int $amount):string {
 $small=['zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen'];
 $tens=['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
 $words=function(int $n)use(&$words,$small,$tens):string{
  if($n<20)return $small[$n];if($n<100)return $tens[intdiv($n,10)].($n%10?' '.$small[$n%10]:'');
  foreach([1000000000=>'billion',1000000=>'million',1000=>'thousand',100=>'hundred'] as $unit=>$label)if($n>=$unit)return $words(intdiv($n,$unit)).' '.$label.($n%$unit?' '.$words($n%$unit):'');
  return '';
 };return ucfirst($words(intdiv($amount,100))).' and '.sprintf('%02d',$amount%100).'/100';
}
