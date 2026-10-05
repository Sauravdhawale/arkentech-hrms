<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/core-hr/controller.php';
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function blocked(callable $fn,string $why):void{$bad=false;try{$fn();}catch(InvalidArgumentException|UnexpectedValueException $e){$bad=true;}check($bad,$why);}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];$viewer=['id'=>3,'role'=>'employee'];$before=$db->query('SELECT id,password_hash FROM users')->fetchAll(PDO::FETCH_ASSOC);att_install($db);att_install($db);check($before===$db->query('SELECT id,password_hash FROM users')->fetchAll(PDO::FETCH_ASSOC),'Migration changed credentials');check(!att_biometric_enabled($db),'Biometric default must be off');blocked(fn()=>att_bridge_auth($db,'Bearer '.str_repeat('a',64)),'OFF gate bypass');
$fixture=json_decode(file_get_contents('/tmp/core-hr-fixture.json'),true);$employee=$fixture['employee'];
$defaults=['title'=>'CI company default','scope'=>'Company','shift_id'=>$fixture['shift'],'from'=>'2026-02-01','reason'=>'CI coverage','active'=>1];att_save_default($db,$admin,$defaults);check(chr_assignment($db,$employee,'2026-02-02')['assignment_source']==='Employee','Employee override must retain priority');$fallback=att_resolve(att_resolution_context($db),['user_id'=>0,'department_id'=>0],'2026-02-02');check($fallback['assignment_source']==='Company','Company shift fallback');blocked(fn()=>att_save_default($db,$admin,$defaults),'Overlapping defaults');
$punch=['employee_id'=>$employee,'attendance_date'=>'2026-02-02','punched_at'=>'2026-02-02 22:00:00','punch_action'=>'Check In','notes'=>'CI manual in','request_key'=>str_repeat('a',64)];$id=att_manual_punch($db,$admin,$punch);blocked(fn()=>att_manual_punch($db,$admin,$punch),'Repeated manual punch');att_manual_punch($db,$admin,array_merge($punch,['punched_at'=>'2026-02-03 06:00:00','punch_action'=>'Check Out','request_key'=>str_repeat('b',64)]));check($db->query('SELECT source FROM hr_attendance WHERE id='.$id)->fetchColumn()==='Admin Manual','Manual source');
$code=$db->query('SELECT employee_code FROM employees WHERE user_id='.$employee)->fetchColumn();$rows=att_import_rows($db,[['employee_id'=>$code,'date'=>'2026-02-04','check_in'=>'22:00','check_out'=>'06:00']]);check($rows[0]['error']===''&&$rows[0]['data']['check_out']==='2026-02-05 06:00:00','Overnight import normalization');blocked(fn()=>att_manual_punch($db,$viewer,$punch),'Manual permission bypass');
att_save_settings($db,$admin,['mode'=>'Manual + Biometric','biometric_enabled'=>1]);att_save_device($db,$admin,['title'=>'CI Bridge','device_code'=>'CI-SERIAL','active'=>1]);$device=chr_rows($db,'devices')[0];att_save_mapping($db,$admin,['title'=>'CI Mapping','device_id'=>$device['id'],'employee_id'=>$employee,'biometric_id'=>'CI-1','active'=>1]);att_device_action($db,$admin,['device_id'=>$device['id'],'device_action'=>'rotate']);$token=$_SESSION['bridge_token_once']['token'];$authenticated=att_bridge_auth($db,'Bearer '.$token);
$events=[['biometric_id'=>'CI-1','event_key'=>'CI-E1','punched_at'=>'2026-02-06 22:00:00','direction'=>'in'],['biometric_id'=>'CI-1','event_key'=>'CI-E2','punched_at'=>'2026-02-07 06:00:00','direction'=>'out']];$result=att_bridge_batch($db,$authenticated,$events);check(count($result['accepted'])===2,'Bridge save');$result=att_bridge_batch($db,$authenticated,$events);check(count($result['duplicates'])===2,'Bridge retry idempotency');$q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$employee,'2026-02-06']);$r=$q->fetch(PDO::FETCH_ASSOC);check($r&&$r['source']==='Biometric'&&$r['check_out']==='2026-02-07 06:00:00','Overnight bridge calculation');
att_bridge_batch($db,$authenticated,[['biometric_id'=>'CI-1','event_key'=>'CI-MANUAL','punched_at'=>'2026-02-02 22:01:00','direction'=>'in']]);check($db->query('SELECT source FROM hr_attendance WHERE id='.$id)->fetchColumn()==='Admin Manual','Manual attendance overwritten');
att_save_absence($db,$admin,['employee_id'=>$employee,'attendance_date'=>'2026-02-09','notes'=>'CI absence']);$report=chr_report($db,'2026-02-09','2026-02-09',['employee_id'=>$employee]);check($report[0]['status']==='Absent'&&$report[0]['source']==='Admin Manual','Manual absence reporting');
att_bridge_batch($db,$authenticated,[['biometric_id'=>'CI-1','event_key'=>'CI-ABSENT','punched_at'=>'2026-02-09 22:00:00','direction'=>'in']]);check((int)$db->query("SELECT COUNT(*) FROM hr_punch_processing WHERE status='Manual protected'")->fetchColumn()>=2,'Manual absence protection');
att_bridge_batch($db,$authenticated,[['biometric_id'=>'CI-NEW','event_key'=>'CI-UNKNOWN','punched_at'=>'2026-02-10 22:00:00','direction'=>'in']]);check((int)$db->query("SELECT COUNT(*) FROM hr_punch_processing WHERE status='Needs mapping'")->fetchColumn()===1,'Unknown mapping retained');att_save_mapping($db,$admin,['title'=>'CI delayed mapping','device_id'=>$device['id'],'employee_id'=>$employee,'biometric_id'=>'CI-NEW','active'=>1]);check(att_retry_device_punches($db,$authenticated)===1,'Automatic retry count');check((int)$db->query("SELECT COUNT(*) FROM hr_punch_processing WHERE status='Needs mapping'")->fetchColumn()===0,'Retry resolved mapping');
// A backlog larger than one cycle must not starve later mapped punches.
$backlog=[];for($i=0;$i<101;$i++)$backlog[]=['biometric_id'=>'CI-WAIT-'.$i,'event_key'=>'CI-WAIT-'.$i,'punched_at'=>'2026-02-11 22:00:00','direction'=>'in'];
att_bridge_batch($db,$authenticated,$backlog);
$db->exec("UPDATE hr_punch_processing SET processed_at='2000-01-01 00:00:00' WHERE status='Needs mapping'");
att_save_mapping($db,$admin,['title'=>'CI last queued mapping','device_id'=>$device['id'],'employee_id'=>$employee,'biometric_id'=>'CI-WAIT-100','active'=>1]);
check(att_retry_device_punches($db,$authenticated)===100,'Automatic batch bound');
check(att_retry_device_punches($db,$authenticated)===100,'Automatic next batch');
$q=$db->query("SELECT x.status FROM hr_punch_processing x JOIN hr_punches p ON p.id=x.punch_id WHERE p.event_key='CI-WAIT-100'");check($q->fetchColumn()==='Processed','Unmapped backlog starved later mapping');
check($db->query('SELECT source FROM hr_attendance WHERE id='.$id)->fetchColumn()==='Admin Manual','Automatic retry changed manual attendance');
$_SESSION['attendance_import']=['actor'=>1,'expires'=>time()+600,'nonce'=>'ci','rows'=>[['employee_id'=>$code,'date'=>'2026-02-12','check_in'=>'22:00','check_out'=>'06:00'],['employee_id'=>$code,'date'=>'2026-02-02','check_in'=>'22:00','check_out'=>'06:00']]];blocked(fn()=>att_import_commit($db,$admin,['import_nonce'=>'ci']),'Conflicting import accepted');check((int)$db->query("SELECT COUNT(*) FROM hr_attendance WHERE attendance_date='2026-02-12'")->fetchColumn()===0,'Partial import saved');
att_device_action($db,$admin,['device_id'=>$device['id'],'device_action'=>'revoke']);blocked(fn()=>att_bridge_auth($db,'Bearer '.$token),'Revoked token accepted');blocked(fn()=>att_retry_device_punches($db,$authenticated),'Automatic retry accepted revoked token');
att_save_event($db,$admin,['title'=>'CI Event','type'=>'Training','start'=>'2026-02-01','end'=>'2026-02-02','all_day'=>1,'audience'=>'Everyone','active'=>1]);check(count(chr_rows($db,'company_events'))===1,'Calendar persisted');blocked(fn()=>att_save_event($db,$viewer,[]),'Calendar permission bypass');
echo "PASS: additive migration, default-OFF, shifts, manual punches, import normalization, bridge dedupe, overnight processing, manual protection, token revocation, calendar and permission guards.\n";


// Profile scheduling is transactional, date-scoped, and resumes the old snapshot.
$db->beginTransaction();
try {
 $day=(new DateTimeImmutable('today'))->modify('+5 years')->format('Y-m-d');
 $next=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
 $after=(new DateTimeImmutable($day))->modify('+2 days')->format('Y-m-d');
 $prior=(new DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d');
 $shift=chr_record($db,'shifts',(int)$fixture['shift']);
 $other=att_record_write($db,$admin,'shifts',['title'=>'CI temporary day shift'],array_replace($shift['values'],['code'=>'CI-TEMP','start'=>'09:00','end'=>'18:00','overnight'=>false]));
 $baseInput=['employee_id'=>$employee,'schedule_mode'=>'default','from'=>$day,'shift_id'=>$fixture['shift'],'notes'=>'CI default','schedule_token'=>att_schedule_token(att_employee_roster($db,$employee))];
 blocked(fn()=>att_save_employee_schedule($db,$viewer,$baseInput),'Unauthorized schedule update');
 $defaultId=att_save_employee_schedule($db,$admin,$baseInput);
 $default=chr_record($db,'roster',$defaultId);
 $temporary=['employee_id'=>$employee,'schedule_mode'=>'temporary','from'=>$next,'to'=>$next,'shift_id'=>$other,'notes'=>'CI one-day change','schedule_token'=>att_schedule_token(att_employee_roster($db,$employee))];
 att_save_employee_schedule($db,$admin,$temporary);
 check((int)chr_assignment($db,$employee,$day)['id']===(int)$fixture['shift'],'Default applies before exception');
 check((int)chr_assignment($db,$employee,$next)['id']===$other,'Temporary shift applies for one day');
 check((int)chr_assignment($db,$employee,$after)['id']===(int)$fixture['shift'],'Original shift resumes after temporary end');
 check(chr_record($db,'roster',$defaultId)['values']['from']===$day,'Original default ID and start retained');
 check(chr_assignment($db,$employee,$after)['values']['start']===$default['values']['shift_snapshot']['start'],'Resumed shift preserves old rules');
 $beforeRows=att_employee_roster($db,$employee);
 blocked(fn()=>att_save_employee_schedule($db,$admin,$temporary),'Stale schedule token accepted');
 check($beforeRows===att_employee_roster($db,$employee),'Failed save changed schedule');
 $past=array_replace($temporary,['from'=>$prior,'to'=>$next,'schedule_token'=>att_schedule_token($beforeRows)]);
 $past['from']=date('Y-m-d',strtotime('-1 day'));blocked(fn()=>att_save_employee_schedule($db,$admin,$past),'Historical change accepted');
 $defaultChange=array_replace($baseInput,['from'=>$after,'shift_id'=>$other,'schedule_token'=>att_schedule_token(att_employee_roster($db,$employee))]);
 att_save_employee_schedule($db,$admin,$defaultChange);
 check((int)chr_assignment($db,$employee,$after)['id']===$other,'Ongoing default can be changed');
 check((int)chr_assignment($db,$employee,$day)['id']===(int)$fixture['shift'],'Changing default retained earlier dates');
 $q=$db->prepare("INSERT INTO hr_attendance(employee_id,attendance_date,check_in,shift_snapshot,source,status,notes,updated_by) VALUES(?,?,?,?,'Admin Manual','Open','CI protected',1)");$q->execute([$employee,$after,$after.' 09:00:00',json_encode($shift['values'])]);
 $locked=array_replace($temporary,['from'=>$after,'to'=>$after,'schedule_token'=>att_schedule_token(att_employee_roster($db,$employee))]);
 blocked(fn()=>att_save_employee_schedule($db,$admin,$locked),'Recorded attendance schedule replaced');
} finally {if($db->inTransaction())$db->rollBack();}
echo "Employee default and temporary shift checks passed\n";
