<?php
require_once __DIR__.'/config.php';

function pay_profile_input(PDO $db,array $actor,array $raw,string $joining):array {
 pay_allow($db,$actor,'payroll.salary.manage');
 if(!pay_ready($db))throw new InvalidArgumentException('Enable Payroll before assigning a salary.');
 $in=array_intersect_key($raw,array_flip(['structure_id','salary_basis','salary_amount','effective_from','effective_to','manual','employee_ot_rate','bank_last4','pan_last4','reason','notes']));
 $in['effective_from']=($in['effective_from']??'')?:$joining;
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

