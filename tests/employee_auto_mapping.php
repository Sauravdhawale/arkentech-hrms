<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/includes/core-hr/controller.php';
require_once dirname(__DIR__).'/includes/foundation/employees.php';
require_once dirname(__DIR__).'/includes/attendance/employee-mapping.php';
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
function map_check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function map_blocked(PDO $db,callable $fn,string $why):void{
 $before=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();$db->exec('SAVEPOINT map_attempt');$blocked=false;
 try{$fn();}catch(InvalidArgumentException $e){$blocked=true;}finally{$db->exec('ROLLBACK TO SAVEPOINT map_attempt');}
 map_check($blocked,$why);map_check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===$before,'Failed employee/mapping save must roll back together');
}
map_check(att_employee_biometric_id('AT00341')==='341','AT00341 numeric suffix');
map_check(att_employee_biometric_id('at00177')==='177','Case-insensitive company prefix');
foreach(['AT00000','EMP00341','AT34x1','AT-341',''] as $bad)map_check(att_employee_biometric_id($bad)===null,'Invalid code must not guess a mapping');
$db=db();$admin=['id'=>1,'role'=>'super_admin'];$viewer=['id'=>2,'role'=>'employee'];
$db->beginTransaction();try{
 $beforeMappings=chr_rows($db,'mapping');
 $department=(int)$db->query("SELECT id FROM departments WHERE name='Engineering'")->fetchColumn();
 $designation=(int)$db->query("SELECT id FROM designations WHERE name='Engineer'")->fetchColumn();
 $role=(int)$db->query("SELECT id FROM roles WHERE slug='employee'")->fetchColumn();
 $deviceId=att_record_write($db,$admin,'devices',['title'=>'CI Auto Mapping Device'],['device_code'=>'CI-AUTO-MAP'],null,'Enabled');
 $device=att_device($db,$deviceId);$device['actor_id']=1;
 $base=['first_name'=>'Auto','last_name'=>'Mapped','username'=>'ci.auto.mapped','employee_code'=>'AT00990341','joining_date'=>'2026-02-01','employment_type'=>'Full Time','employment_status'=>'Active','department_id'=>$department,'designation_id'=>$designation,'role_id'=>$role,'biometric_auto_map'=>1,'biometric_device_id'=>$deviceId];
 $id=save_employee($db,$admin,$base,[]);
 $mine=array_values(array_filter(chr_rows($db,'mapping'),fn($m)=>(int)$m['employee_id']===$id));
 map_check(count($mine)===1&&$mine[0]['values']['biometric_id']==='990341'&&$mine[0]['values']['device_code']==='CI-AUTO-MAP','Employee creation must save a device-scoped numeric mapping');
 map_check(foundation_employee($db,$id)['employee_code']==='AT00990341','Company ID must stay intact');
 att_map_new_employee($db,$admin,$id,$deviceId);
 map_check(count(chr_rows($db,'mapping'))===count($beforeMappings)+1,'Repeat mapping must reuse existing record');
 $other=array_replace($base,['username'=>'ci.auto.conflict','employee_code'=>'AT000990341']);
 map_blocked($db,fn()=>save_employee($db,$admin,$other,[]),'Same numeric ID must not move another employee mapping');
 map_blocked($db,fn()=>save_employee($db,$viewer,array_replace($base,['username'=>'ci.auto.denied','employee_code'=>'AT00990342']),[]),'Actor without biometric permission must not create mapping');
 map_blocked($db,fn()=>save_employee($db,$admin,array_replace($base,['username'=>'ci.auto.invalid','employee_code'=>'OTHER343']),[]),'Unsupported prefix must not guess');
 $disabled=att_record_write($db,$admin,'devices',['title'=>'CI Disabled'],['device_code'=>'CI-AUTO-OFF'],null,'Disabled');
 map_blocked($db,fn()=>save_employee($db,$admin,array_replace($base,['username'=>'ci.auto.disabled','employee_code'=>'AT00990343','biometric_device_id'=>$disabled]),[]),'Disabled device must not be selected');
 $manual=save_employee($db,$admin,array_replace($base,['username'=>'ci.auto.optout','employee_code'=>'AT00990344','biometric_auto_map'=>0]),[]);
 map_check(!array_filter(chr_rows($db,'mapping'),fn($m)=>(int)$m['employee_id']===$manual),'Opt-out must retain manual-only creation');
 $edit=array_replace($base,['id'=>$id,'version'=>foundation_employee($db,$id)['version'],'biometric_auto_map'=>0,'employee_code'=>'AT00990345']);save_employee($db,$admin,$edit,[]);
 map_check(chr_record($db,'mapping',(int)$mine[0]['id'])['values']['biometric_id']==='990341','Profile editing must not silently remap device identity');
 // A reused code must not claim raw punches before this employee joined.
 $event=['biometric_id'=>'990341','event_key'=>'CI-AUTO-OLD','punched_at'=>'2026-01-01 18:00:00','direction'=>'unknown'];
 $db->prepare('INSERT INTO hr_punches(device_code,biometric_id,event_key,punched_at,direction) VALUES(?,?,?,?,?)')->execute(['CI-AUTO-MAP',...array_values($event)]);
 $punch=(int)$db->lastInsertId();$db->prepare('INSERT INTO hr_punch_processing(punch_id,device_id,raw_payload,fingerprint) VALUES(?,?,?,?)')->execute([$punch,$deviceId,json_encode($event),hash('sha256','CI-AUTO-OLD')]);
 att_process_punch($db,$device,$punch,$event);
 $q=$db->query('SELECT status,employee_id FROM hr_punch_processing WHERE punch_id='.$punch);$old=$q->fetch(PDO::FETCH_ASSOC);
 map_check($old['status']==='Before joining date'&&$old['employee_id']===null,'Pre-joining punches must remain unassigned');
 foreach($beforeMappings as $old)map_check(chr_record($db,'mapping',(int)$old['id'])===$old,'Existing mappings must remain unchanged');
}finally{if($db->inTransaction())$db->rollBack();}
echo "PASS: automatic employee-code mapping, exact ID preservation, idempotency, conflict and permission guards, opt-out, edit preservation and pre-joining isolation.\n";
