<?php
require_once __DIR__.'/config.php';

/** Profile defaults never use a historical joining date as a new salary's start. */
function pay_profile_default_date(string $joining='',?string $latestFrom=null,?string $today=null):string {
 $today=$today??(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
 if($latestFrom)return max($today,(new DateTimeImmutable($latestFrom))->modify('+1 day')->format('Y-m-d'));
 return max(substr($today,0,7).'-01',$joining);
}

/** Select an effective revision, preserving an explicitly chosen rules family. No writes. */
function pay_profile_structure_choices(array $rows,string $day,int $selectedId=0):array {
 $groups=[];$selectedKey=null;
 foreach($rows as $row){
  $key=strtoupper(trim((string)($row['values']['code']??$row['title'])));
  if((int)$row['id']===$selectedId)$selectedKey=$key;
  if($row['status']==='Active')$groups[$key][]=$row;
 }
 $choices=[];$effective=[];
 foreach($groups as $key=>$revisions){
  usort($revisions,fn($a,$b)=>strcmp($b['values']['effective_from'],$a['values']['effective_from'])?:((int)$b['id']<=>(int)$a['id']));
  $choice=null;
  foreach($revisions as $revision){$v=$revision['values'];if($v['effective_from']<=$day&&(!$v['effective_to']||$v['effective_to']>=$day)){$choice=$revision;break;}}
  $choices[$key]=$choice??$revisions[0];if($choice)$effective[$key]=$choice;
 }
 $selected=$selectedKey!==null?($effective[$selectedKey]??null):(!$selectedId&&count($effective)===1?array_values($effective)[0]:null);
 $issue='';
 if($selectedId&&$selectedKey===null)$issue='The saved salary rules are unavailable. Choose the applicable rules in More salary options.';
 elseif($selectedKey!==null&&!$selected){
  $dates=array_map(fn($r)=>$r['values']['effective_from'],$groups[$selectedKey]??[]);
  $issue=$dates&&$day<min($dates)?'These salary rules start on '.min($dates).'. Enter a salary start date on or after that date, or ask Payroll to configure rules for the earlier period.':'The selected salary rules do not cover '.$day.'. Choose rules that cover this date in More salary options, or update their effective period in Configuration.';
 }elseif(!$effective){
  $dates=[];foreach($groups as $revisions)foreach($revisions as $r)$dates[]=$r['values']['effective_from'];
  $issue=$dates&&$day<min($dates)?'Salary rules are available from '.min($dates).'. Enter a salary start date on or after that date, or configure rules for the earlier period.':'No salary rules cover '.$day.'. Add or review their effective dates in Payroll → Configuration.';
 }elseif(!$selected)$issue='More than one salary rule applies. Choose one in More salary options.';
 return ['choices'=>$choices,'effective'=>$effective,'selected'=>$selected,'issue'=>$issue];
}

function pay_profile_input(PDO $db,array $actor,array $raw,string $joining):array {
 pay_allow($db,$actor,'payroll.salary.manage');
 if(!pay_ready($db))throw new InvalidArgumentException('Enable Payroll before assigning a salary.');
 $in=array_intersect_key($raw,array_flip(['structure_id','salary_basis','salary_amount','effective_from','effective_to','manual','employee_ot_rate','bank_last4','pan_last4','reason','notes']));
 $in['effective_from']=($in['effective_from']??'')?:pay_profile_default_date($joining);
 $in['effective_from']=chr_date((string)$in['effective_from']);
 if($joining&&$in['effective_from']<$joining)throw new InvalidArgumentException('Salary cannot start before the joining date ('.$joining.').');
 $rules=pay_profile_structure_choices(chr_rows($db,'pay_structure'),$in['effective_from'],(int)($in['structure_id']??0));
 if(!$rules['selected'])throw new InvalidArgumentException($rules['issue']);
 $in['structure_id']=(int)$rules['selected']['id'];
 $in['salary_basis']=$in['salary_basis']??'Monthly Gross';
 ftext($in,'reason',1000,true);
 $p=pay_assignment_terms($db,$in);
 if($joining&&$p['effective_from']<$joining)throw new InvalidArgumentException('Salary cannot start before the joining date.');
 return $in;
}
function pay_profile_preview(PDO $db,array $actor,int $employeeId,array $raw):array {
 pay_allow($db,$actor,'payroll.salary.manage');
 if(!can($db,$actor,'employees.edit'))throw new InvalidArgumentException('Employee edit permission required.');
 $employee=foundation_employee($db,$employeeId);
 if(!$employee)throw new InvalidArgumentException('Employee not found.');
 $in=pay_profile_input($db,$actor,$raw,(string)$employee['joining_date']);$in['employee_id']=$employeeId;
 $p=pay_assignment_preview($db,$in);
 $q=$db->prepare('SELECT id,effective_from FROM hr_salary_assignments WHERE employee_id=? ORDER BY effective_from DESC LIMIT 1');$q->execute([$employeeId]);$old=$q->fetch(PDO::FETCH_ASSOC);
 if($old&&$p['effective_from']<=$old['effective_from'])throw new InvalidArgumentException('A revision must start after the latest salary assignment ('.$old['effective_from'].'). Earlier salary history is preserved.');
 $in['previous_id']=(int)($old['id']??0);$in['preview_hash']=hash('sha256',pay_json($p));
 return ['actor'=>(int)$actor['id'],'employee_id'=>$employeeId,'token'=>bin2hex(random_bytes(24)),'input'=>$in,'preview'=>$p];
}
function pay_profile_confirm(PDO $db,array $actor,int $employeeId,array $pending,array $in):int {
 pay_allow($db,$actor,'payroll.salary.manage');
 if(!can($db,$actor,'employees.edit'))throw new InvalidArgumentException('Employee edit permission required.');
 if((int)($pending['actor']??0)!==(int)$actor['id']||(int)($pending['employee_id']??0)!==$employeeId||empty($pending['token'])||!hash_equals($pending['token'],(string)($in['salary_preview_token']??'')))throw new InvalidArgumentException('This salary preview expired. Preview the salary again.');
 if(empty($in['confirm']))throw new InvalidArgumentException('Review and confirm the salary preview.');
 return pay_assign($db,$actor,$pending['input']+['confirm'=>1]);
}


