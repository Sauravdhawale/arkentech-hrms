<?php
/** Salary form date/structure selection is deterministic and must never rewrite rule dates. */
require dirname(__DIR__).'/includes/payroll/profile-salary.php';
function salary_simple_check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function salary_simple_row(int $id,string $code,string $from,?string $until=null,string $status='Active'):array {
 return ['id'=>$id,'title'=>$code.' salary rules','status'=>$status,'values'=>['code'=>$code,'effective_from'=>$from,'effective_to'=>$until]];
}
salary_simple_check(pay_profile_default_date('2024-09-29',null,'2026-10-20')==='2026-10-01','Initial salary default used historical joining date.');
salary_simple_check(pay_profile_default_date('2026-10-15',null,'2026-10-20')==='2026-10-15','Initial salary default preceded joining.');
salary_simple_check(pay_profile_default_date('2026-11-01',null,'2026-10-20')==='2026-11-01','Future joining date was ignored.');
salary_simple_check(pay_profile_default_date('', '2026-10-01','2026-10-20')==='2026-10-20','Salary increment did not default to today.');
salary_simple_check(pay_profile_default_date('', '2026-11-01','2026-10-20')==='2026-11-02','Salary increment overlapped a future assignment.');
$rows=[salary_simple_row(1,'COMMON','2026-10-01','2026-10-31'),salary_simple_row(2,'COMMON','2026-11-01')];$original=$rows;
salary_simple_check(pay_profile_structure_choices($rows,'2026-10-20')['selected']['id']===1,'Single common effective structure not selected.');
salary_simple_check(pay_profile_structure_choices($rows,'2026-11-10',1)['selected']['id']===2,'New date did not choose the effective revision within the same family.');
salary_simple_check(pay_profile_structure_choices($rows,'2026-10-20',2)['selected']['id']===1,'Past date did not retain the historically applicable revision.');
$old=pay_profile_structure_choices($rows,'2024-09-29');
salary_simple_check($old['selected']===null&&str_contains($old['issue'],'2026-10-01'),'Invalid historical date did not explain earliest rule date.');
salary_simple_check(pay_profile_structure_choices($rows,'2026-10-20',999)['selected']===null,'Unknown explicit structure silently selected another.');
$multiple=array_merge($rows,[salary_simple_row(3,'EXECUTIVE','2026-10-01')]);
salary_simple_check(pay_profile_structure_choices($multiple,'2026-10-20')['selected']===null,'Ambiguous salary rules automatically selected.');
salary_simple_check(pay_profile_structure_choices($multiple,'2026-10-20',3)['selected']['id']===3,'Explicit rule family was overridden.');
$expired=[salary_simple_row(4,'OLD','2025-01-01','2025-12-31'),salary_simple_row(5,'NEW','2026-01-01')];
salary_simple_check(pay_profile_structure_choices($expired,'2026-10-20',4)['selected']===null,'Expired selected rules silently changed to another family.');
$inactive=[salary_simple_row(6,'INACTIVE','2026-01-01',null,'Inactive')];
salary_simple_check(pay_profile_structure_choices($inactive,'2026-10-20')['selected']===null,'Inactive rules were automatically selected.');
salary_simple_check($rows===$original,'Rule selection changed stored definitions.');
echo "PASS: salary defaults, effective rules, family preservation, ambiguity and historical date guidance.\n";
