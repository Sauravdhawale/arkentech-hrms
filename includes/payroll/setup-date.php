<?php
require_once __DIR__.'/config.php';

/** Correct an initial setup date; normal forward-only revisions remain unchanged. */
function pay_setup_date_plan(PDO $db,array $actor,int $employeeId,string $from,string $reason):array {
 if(($actor['role']??'')!=='super_admin')throw new InvalidArgumentException('Super Admin required to correct initial payroll dates.');
 $from=chr_date($from);$reason=ftext(['reason'=>$reason],'reason',1000,true);
 if(substr($from,8)!=='01'||$from>date('Y-m-d'))throw new InvalidArgumentException('Choose the first day of a past or current month.');
 $employee=foundation_employee($db,$employeeId);
 if(!$employee||!$employee['active']||$employee['employment_status']!=='Active')throw new InvalidArgumentException('Choose an active employee.');
 $q=$db->prepare('SELECT * FROM hr_salary_assignments WHERE employee_id=? ORDER BY effective_from,id');$q->execute([$employeeId]);$salaries=$q->fetchAll(PDO::FETCH_ASSOC);
 if(!$salaries)throw new InvalidArgumentException('Save the employee’s initial salary first.');
 $salary=$salaries[0];$salaryFrom=max($from,(string)$employee['joining_date']);
 if($salaryFrom>=$salary['effective_from'])throw new InvalidArgumentException('This employee already has salary coverage from that date. Use normal salary revisions for later changes.');
 $snapshot=json_decode($salary['snapshot'],true,512,JSON_THROW_ON_ERROR);
 $records=[];$checks=[];
 $include=function(string $module,int $id)use($db,$from,&$records,&$checks):array {
  $r=chr_record($db,$module,$id);
  if(!$r||$r['status']!=='Active')throw new InvalidArgumentException('A required payroll configuration is missing or inactive.');
  $code=$r['values']['code']??'DEFAULT';
  $family=array_values(array_filter(chr_rows($db,$module),fn($x)=>($x['values']['code']??'DEFAULT')===$code));
  usort($family,fn($a,$b)=>strcmp($a['values']['effective_from'],$b['values']['effective_from'])?:((int)$a['id']<=>(int)$b['id']));
  $checks[$module.':'.$code]=$family;
  $v=$r['values'];
  if(!empty($v['effective_to'])&&$v['effective_to']<$from)throw new InvalidArgumentException('A required configuration ended before the requested month.');
  if($v['effective_from']>$from){
   if((int)$family[0]['id']!==(int)$r['id'])throw new InvalidArgumentException('Only the initial version can start earlier. This setup has earlier revisions and needs a historical payroll review.');
   $next=$v;$next['effective_from']=$from;
   $records[$id]=['id'=>$id,'module'=>$module,'title'=>$r['title'],'version'=>(int)$r['version'],'before'=>$v,'after'=>$next];
  }
  return $r;
 };
 $structure=$include('pay_structure',(int)$salary['structure_id']);
 foreach($structure['values']['components']??[] as $part)$include('pay_component',(int)$part['record_id']);
 foreach($snapshot['structure']['statutory']??[] as $rule)$include('pay_statutory',(int)$rule['record_id']);
 foreach(['pay_settings','pay_payslip'] as $module){
  $rows=array_values(array_filter(chr_rows($db,$module),fn($r)=>($r['values']['code']??'DEFAULT')==='DEFAULT'));
  usort($rows,fn($a,$b)=>strcmp($a['values']['effective_from'],$b['values']['effective_from'])?:((int)$a['id']<=>(int)$b['id']));
  if(!$rows)throw new InvalidArgumentException('Save the monthly payroll settings and payslip design first.');
  $include($module,(int)$rows[0]['id']);
 }
 // Reject any already-created payroll in newly covered dates; never change its basis.
 $until=$salary['effective_from'];foreach($records as $r)$until=max($until,$r['before']['effective_from']);
 $q=$db->prepare('SELECT id,month,status FROM hr_payroll_runs WHERE month>=? AND month<? ORDER BY month');
 $q->execute([substr($from,0,7),substr($until,0,7)]);
 if($q->fetch())throw new InvalidArgumentException('Payroll already exists in the period being added. Review that month through the existing payroll correction workflow first.');
 foreach(chr_rows($db,'payroll') as $r){$month=$r['values']['month']??'';if($month>=substr($from,0,7)&&$month<substr($until,0,7))throw new InvalidArgumentException('Legacy payroll exists in the period being added. Review it before correcting initial dates.');}
 // Refresh date/version metadata in the selected salary definition only, not payslips.
 $adjustPart=function(array $part)use(&$records):array {$id=(int)($part['record_id']??0);if(isset($records[$id])){$part['effective_from']=$records[$id]['after']['effective_from'];$part['record_version']=$records[$id]['version']+1;}return $part;};
 $sid=(int)$structure['id'];
 if(isset($records[$sid]))$records[$sid]['after']['components']=array_map($adjustPart,$records[$sid]['after']['components']);
 $next=$snapshot;$next['effective_from']=$salaryFrom;
 if(isset($records[$sid])){$next['structure']['effective_from']=$records[$sid]['after']['effective_from'];$next['structure_version']=$records[$sid]['version']+1;}
 $next['structure']['components']=array_map($adjustPart,$next['structure']['components']);
 if(isset($next['structure']['statutory']))$next['structure']['statutory']=array_map($adjustPart,$next['structure']['statutory']);
 $moneyOnly=function(array $result):array {$result['components']=array_column($result['components'],'amount','code');return $result;};
 if($moneyOnly(pay_breakup($snapshot['structure'],(int)$salary['monthly_gross'],$snapshot['manual']??[]))!==$moneyOnly(pay_breakup($next['structure'],(int)$salary['monthly_gross'],$next['manual']??[])))throw new InvalidArgumentException('Salary amounts changed. Review the salary rules first.');
 ksort($records);ksort($checks);
 $plan=['employee'=>['id'=>$employeeId,'name'=>$employee['name'],'code'=>$employee['employee_code'],'joining_date'=>$employee['joining_date']],'from'=>$from,'salary_from'=>$salaryFrom,'reason'=>$reason,'salary'=>$salary,'salary_after'=>$next,'records'=>array_values($records),'checks'=>$checks,'salary_history'=>$salaries];
 $plan['fingerprint']=hash('sha256',pay_json($plan));return $plan;
}

function pay_setup_date_apply(PDO $db,array $actor,array $in):int {
 if(empty($in['confirm']))throw new InvalidArgumentException('Review the date correction before saving.');
 $db->beginTransaction();try{
  chr_lock($db);
  $p=pay_setup_date_plan($db,$actor,(int)($in['employee_id']??0),(string)($in['effective_from']??''),(string)($in['reason']??''));
  if(!hash_equals($p['fingerprint'],(string)($in['fingerprint']??'')))throw new InvalidArgumentException('Payroll or salary changed. Preview the date correction again.');
  foreach($p['records'] as $r)$db->prepare('UPDATE hr_records SET data=?,version=version+1 WHERE id=? AND version=?')->execute([pay_json($r['after']),$r['id'],$r['version']]);
  $db->prepare('UPDATE hr_salary_assignments SET effective_from=?,snapshot=? WHERE id=?')->execute([$p['salary_from'],pay_json($p['salary_after']),$p['salary']['id']]);
  // Keep the full before/after correction trail without deleting salary history.
  unset($p['checks'],$p['salary_history'],$p['fingerprint']);
  $db->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('payroll_setup_date',?,'Initial payroll date correction','Active',?,?)")->execute([$p['employee']['id'],pay_json($p),$actor['id']]);
  $id=(int)$db->lastInsertId();faudit($db,$actor,'payroll.setup_date.corrected',$id);$db->commit();return $id;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
