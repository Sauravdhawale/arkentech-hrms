<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';
$db=db();$device=(int)$db->query("SELECT id FROM hr_records WHERE module='devices' LIMIT 1")->fetchColumn();
if(!$device)throw new RuntimeException('Attendance device fixture missing');
foreach(['applicant','lead'] as $name){
 $q=$db->prepare('SELECT id FROM users WHERE username=?');$q->execute(['ess.'.$name]);$owner=(int)$q->fetchColumn();if(!$owner)throw new RuntimeException('ESS fixture missing');
 $key='my-punch-history-'.$name;
 $db->prepare('INSERT INTO hr_punches(device_code,biometric_id,event_key,punched_at,direction) VALUES(?,?,?,?,?)')->execute(['CI-HISTORY',$name,$key,'2026-01-03 01:02:03','in']);$id=(int)$db->lastInsertId();
 $db->prepare('INSERT INTO hr_punch_processing(punch_id,device_id,employee_id,attendance_date,status,raw_payload,fingerprint) VALUES(?,?,?,?,?,?,?)')->execute([$id,$device,$owner,'2026-01-02','Processed','{}',hash('sha256',$key)]);
 $db->prepare('INSERT INTO hr_manual_punch_events(employee_id,attendance_date,action,punched_at,source,actor_id,notes,request_key) VALUES(?,?,?,?,?,?,?,?)')->execute([$owner,'2026-01-02','Check Out','2026-01-03 02:03:04','Admin Manual',1,'CI history fixture',hash('sha256',$key.'manual')]);
 if($name==='lead')$db->prepare('INSERT INTO hr_manual_punch_events(employee_id,attendance_date,action,punched_at,source,actor_id,notes,request_key) VALUES(?,?,?,?,?,?,?,?)')->execute([$owner,'2026-01-02','Check In','2026-01-03 02:04:05','Admin Manual',1,'Other employee only',hash('sha256',$key.'extra')]);
}
echo "Punch history fixtures ready.\n";
