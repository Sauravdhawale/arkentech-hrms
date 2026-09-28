<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/foundation/employees.php';require dirname(__DIR__).'/includes/core-hr/service.php';require dirname(__DIR__).'/includes/ess/service.php';
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function expect(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function denied(callable $fn,string $m):void{$caught=false;try{$fn();}catch(InvalidArgumentException $e){$caught=true;}expect($caught,$m);}
$db=db();$admin=['id'=>1,'name'=>'CI Admin','role'=>'super_admin'];
$db->exec("INSERT INTO roles(name,slug) VALUES('ESS Reviewer','ess-reviewer')");$role=(int)$db->lastInsertId();$db->prepare("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE code='leave.approve'")->execute([$role]);$pair=$db->query('SELECT g.id designation_id,d.id department_id FROM designations g JOIN departments d ON d.id=g.department_id WHERE g.active=1 AND d.active=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);$base=(int)$db->query("SELECT id FROM roles WHERE slug='employee'")->fetchColumn();$actors=[];
foreach(['applicant','lead','hr'] as $name){$id=save_employee($db,$admin,['first_name'=>'ESS','last_name'=>ucfirst($name),'username'=>'ess.'.$name,'department_id'=>$pair['department_id'],'designation_id'=>$pair['designation_id'],'employee_code'=>'ESS-'.strtoupper($name),'role_id'=>$name==='applicant'?$base:$role,'joining_date'=>'2026-01-01','employment_status'=>'Active','employment_type'=>'Full Time'],[]);$db->prepare('UPDATE users SET must_change_password=0 WHERE id=?')->execute([$id]);$actors[$name]=['id'=>$id,'name'=>'ESS '.ucfirst($name),'role'=>'employee'];}
$a=$actors['applicant'];$lead=$actors['lead'];$hr=$actors['hr'];
ess_save_route($db,$admin,['employee_id'=>$a['id'],'mode'=>'sequential','approvers'=>[$lead['id'],$hr['id']]]);
$policy=chr_save_config($db,$admin,'leave_policy',['title'=>'ESS leave','type'=>'ESS','annual_days'=>20,'allow_negative'=>1,'active'=>1]);
$input=['category'=>'ESS','start_date'=>'2030-02-04','end_date'=>'2030-02-04','details'=>'Own request','subject'=>'ESS isolated request'];$id=chr_create_leave($db,$a,$input,[],true);$q=$db->prepare('SELECT * FROM hr_requests WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
expect(ess_can_review($db,$lead,$r),'First approver missing');expect(!ess_can_review($db,$hr,$r),'Later approver can act early');expect(!ess_can_review($db,$a,$r),'Self approval permitted');
denied(fn()=>chr_review_leave($db,$hr,$id,'Approved'),'Out-of-order approval accepted');
chr_review_leave($db,$lead,$id,'Approved');expect($db->query('SELECT status FROM hr_requests WHERE id='.$id)->fetchColumn()==='Pending','Intermediate approval finalized request');
denied(fn()=>chr_review_leave($db,$lead,$id,'Approved'),'Duplicate stage accepted');
$route=ess_own_rows($db,$a['id'],'leave_route')[0];ess_save_route($db,$admin,['employee_id'=>$a['id'],'version'=>$route['version'],'mode'=>'any','approvers'=>[$lead['id']]]);
expect(ess_can_review($db,$hr,$r),'Route edit changed an existing request');
denied(fn()=>chr_review_leave($db,$hr,$id,'Rejected'),'Reasonless rejection accepted');chr_review_leave($db,$hr,$id,'Approved');expect($db->query('SELECT status FROM hr_requests WHERE id='.$id)->fetchColumn()==='Approved','Final approval failed');
denied(fn()=>chr_review_leave($db,$hr,$id,'Approved'),'Duplicate final approval accepted');
$profile=foundation_employee($db,$a['id']);ess_post($db,$a,'profile',['action'=>'ess_profile','version'=>$profile['version'],'mobile'=>'12345678','employee_id'=>$lead['id'],'role'=>'super_admin','employee_code'=>'FORGED']);$after=foundation_employee($db,$a['id']);expect($after['employee_code']===$profile['employee_code']&&$after['mobile']==='12345678','Profile write changed protected fields');expect(foundation_employee($db,$lead['id'])['mobile']!=='12345678','Profile ownership bypass');
denied(fn()=>ess_post($db,$a,'profile',['action'=>'ess_profile','version'=>$profile['version']]),'Stale profile save accepted');
$items=ess_inbox($db,$a);$key=array_values(array_filter($items,fn($i)=>str_starts_with($i['key'],'request:'.$id.':')))[0]['key'];ess_post($db,$a,'inbox',['action'=>'ess_inbox','key'=>$key,'state'=>'Read']);denied(fn()=>ess_post($db,$lead,'inbox',['action'=>'ess_inbox','key'=>$key,'state'=>'Read']),'Cross-user inbox write');
$cfg=chr_rows($db,'attendance_settings')[0]??null;att_save_settings($db,$admin,['version'=>$cfg['version']??0,'mode'=>'Manual Attendance','web_punch_enabled'=>1]);
$nonce=bin2hex(random_bytes(32));ess_web_punch($db,$a,['punch_action'=>'Check In','request_key'=>$nonce,'employee_id'=>$lead['id']]);$q=$db->prepare("SELECT * FROM hr_attendance WHERE employee_id=? AND source='Employee Web'");$q->execute([$a['id']]);expect((bool)$q->fetch(),'Own web punch missing');denied(fn()=>ess_web_punch($db,$a,['punch_action'=>'Check In','request_key'=>$nonce]),'Duplicate punch accepted');
$cfg=chr_rows($db,'attendance_settings')[0];att_save_settings($db,$admin,['version'=>$cfg['version'],'mode'=>'Manual Attendance']);denied(fn()=>ess_web_punch($db,$a,['punch_action'=>'Check Out','request_key'=>bin2hex(random_bytes(32))]),'Disabled web attendance accepted');
file_put_contents('/tmp/ess-fixture.json',json_encode($actors));echo "PASS: ESS ownership, profile field allowlist, approval order, route snapshots, reasons, duplicate decisions, inbox ownership and web punch gates.\n";
