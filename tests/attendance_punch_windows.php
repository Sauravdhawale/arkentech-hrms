<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/core-hr/controller.php';
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function ensure(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];
$original=chr_rows($db,'roster');att_setup_company_shifts($db,$admin);$ids=array_column(chr_rows($db,'shifts'),'id');att_setup_company_shifts($db,$admin);ensure($ids===array_column(chr_rows($db,'shifts'),'id'),'Setup duplicated shifts');ensure($original===chr_rows($db,'roster'),'Setup rewrote assignment history');
$shifts=[];foreach(chr_rows($db,'shifts') as $s)$shifts[$s['title']]=$s;
$source=foundation_employee($db,(int)$db->query("SELECT id FROM users WHERE username='phase.tester'")->fetchColumn());
$employees=[];
foreach(['Day','Night'] as $kind){$input=array_merge($source,['id'=>0,'first_name'=>$kind,'last_name'=>'PunchTest','username'=>strtolower($kind).'.punchtest','email'=>strtolower($kind).'@punch.test','employee_code'=>'PUNCH-'.$kind,'employment_status'=>'Active','joining_date'=>'2026-01-01','assigned_shift_id'=>$shifts[$kind.' Shift']['id'],'shift_from'=>'2026-01-01']);$employees[$kind]=save_employee($db,$admin,$input,[]);ensure(chr_assignment($db,$employees[$kind],'2026-03-02')['assignment_source']==='Employee','Create did not assign shift');}
att_save_device($db,$admin,['title'=>'Window Test','device_code'=>'WINDOW-TEST','active'=>1]);$device=chr_rows($db,'devices')[0];att_device_action($db,$admin,['device_id'=>$device['id'],'device_action'=>'rotate']);$device=att_bridge_auth($db,'Bearer '.$_SESSION['bridge_token_once']['token']);
foreach($employees as $kind=>$employee)att_save_mapping($db,$admin,['title'=>$kind,'device_id'=>$device['id'],'employee_id'=>$employee,'biometric_id'=>$kind,'active'=>1]);
function sendPunch(string $kind,string $at,string $direction='unknown'):array{global $db,$device;return att_bridge_batch($db,$device,[['biometric_id'=>$kind,'event_key'=>$kind.'-'.str_replace([' ',':'],'-',$at), 'punched_at'=>$at,'direction'=>$direction]]);}
function rowFor(string $kind,string $day):array{global $db,$employees;$q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$employees[$kind],$day]);return $q->fetch(PDO::FETCH_ASSOC)?:[];}
// Login access is distinct from current employment.
$db->exec('UPDATE users SET active=0 WHERE id='.$employees['Day']);
sendPunch('Day','2026-03-02 09:02:00');$first=rowFor('Day','2026-03-02');ensure($first['check_out']===null,'Single punch should stay open');
sendPunch('Day','2026-03-02 18:05:00');$r=rowFor('Day','2026-03-02');ensure($r['id']===$first['id']&&$r['check_out']==='2026-03-02 18:05:00'&&(int)$r['working_minutes']===453,'Same attendance not updated with break deduction');
$before=(int)$db->query('SELECT COUNT(*) FROM hr_punches')->fetchColumn();$version=$r['version'];sendPunch('Day','2026-03-02 18:05:00');ensure((int)$db->query('SELECT COUNT(*) FROM hr_punches')->fetchColumn()===$before&&rowFor('Day','2026-03-02')['version']===$version,'Duplicate mutated records');
foreach(['09:01:00','13:04:00','14:01:00','18:11:00'] as $time)sendPunch('Day','2026-03-03 '.$time);
$r=rowFor('Day','2026-03-03');ensure($r['check_in']==='2026-03-03 09:01:00'&&$r['check_out']==='2026-03-03 18:11:00','Day first/last');
// Overnight checkout arrives first; later sync supplies preceding check-in and middle punches.
sendPunch('Night','2026-03-04 09:08:00','out');ensure(!rowFor('Night','2026-03-03'),'Out-only must remain pending');
foreach(['2026-03-03 18:05:00','2026-03-03 22:10:00','2026-03-04 01:20:00'] as $at)sendPunch('Night',$at);
$r=rowFor('Night','2026-03-03');ensure($r['check_in']==='2026-03-03 18:05:00'&&$r['check_out']==='2026-03-04 09:08:00'&&(int)$r['working_minutes']===813,'Overnight first/last');ensure(!rowFor('Night','2026-03-04'),'Overnight split into calendar days');
$q=$db->prepare("SELECT COUNT(*) FROM hr_punch_processing WHERE employee_id=? AND attendance_date=? AND status<>'Processed'");$q->execute([$employees['Night'],'2026-03-03']);ensure((int)$q->fetchColumn()===0,'Out-only status not resolved after check-in');
sendPunch('Night','2026-03-06 00:15:00');sendPunch('Night','2026-03-06 08:30:00');$r=rowFor('Night','2026-03-05');ensure($r['check_in']==='2026-03-06 00:15:00'&&(int)$r['early_minutes']===30,'After-midnight entry rejected');
sendPunch('Day','2026-03-04 09:15:00');sendPunch('Day','2026-03-04 17:30:00');$r=rowFor('Day','2026-03-04');ensure((int)$r['late_minutes']===15&&(int)$r['early_minutes']===30,'Day late/early rules');
$db->exec("UPDATE employees SET employment_status='Inactive' WHERE user_id=".$employees['Day']);sendPunch('Day','2026-03-05 09:00:00');ensure(!rowFor('Day','2026-03-05'),'Inactive employee processed');$db->exec("UPDATE employees SET employment_status='Active' WHERE user_id=".$employees['Day']);att_retry_punches($db,$admin,['device_id'=>$device['id']]);ensure((bool)rowFor('Day','2026-03-05'),'Eligible failed punch did not recover');
sendPunch('Day','2026-03-06 03:00:00');$q=$db->query("SELECT error_message FROM hr_punch_processing WHERE device_id=".$device['id']." ORDER BY punch_id DESC LIMIT 1");ensure(str_contains($q->fetchColumn(),'No valid shift window'),'No valid shift not explained');
$settings=chr_rows($db,'attendance_settings')[0];att_save_settings($db,$admin,['version'=>$settings['version'],'mode'=>'Manual + Biometric','biometric_enabled'=>1,'punch_before_minutes'=>180,'punch_after_minutes'=>30]);sendPunch('Day','2026-03-09 06:15:00');ensure((bool)rowFor('Day','2026-03-09'),'Configured pre-shift buffer ignored');
ensure(att_time_label('2026-03-03 20:06:07',true)==='08:06:07 PM','Punch AM/PM');ensure(chr_time(['start'=>'06:00 PM'],'start')==='18:00','AM/PM input canonicalization');ensure(chr_weekoff($shifts['Day Shift'],'2026-03-07')&&chr_weekoff($shifts['Night Shift'],'2026-03-08'),'Weekend setup');
// Restore config for downstream fixtures, without deleting test records.
$settings=chr_rows($db,'attendance_settings')[0];att_save_settings($db,$admin,['version'=>$settings['version'],'mode'=>'Manual + Biometric','biometric_enabled'=>1,'punch_before_minutes'=>120,'punch_after_minutes'=>120]);
echo "PASS: day/night windows, create assignment, setup idempotency, login separation, middle punches, late/out-of-order updates, midnight check-in, retry, deduplication, buffers and AM/PM.\n";
