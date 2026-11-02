<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/core-hr/controller.php';
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
function tl_check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function tl_blocked(callable $fn,string $why):void{$caught=false;try{$fn();}catch(InvalidArgumentException $e){$caught=true;}tl_check($caught,$why);}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];$viewer=['id'=>3,'role'=>'employee'];$fixture=json_decode(file_get_contents('/tmp/core-hr-fixture.json'),true);$employee=(int)$fixture['employee'];$day='2026-03-04';$ui=in_array('--ui',$argv,true);
if(in_array('--cleanup',$argv,true)){
 $f=json_decode(file_get_contents('/tmp/timeline-fixture.json'),true);$db->beginTransaction();
 $db->prepare("DELETE FROM hr_records WHERE employee_id=? AND module IN ('attendance_timeline','attendance_changes','attendance_decisions') AND id>?")->execute([$employee,$f['last_record']]);
 $db->prepare('DELETE FROM hr_punch_processing WHERE employee_id=? AND attendance_date=?')->execute([$employee,$day]);
 $db->exec("DELETE FROM hr_punches WHERE event_key LIKE 'ci-timeline-%'");
 $db->prepare('DELETE FROM hr_manual_punch_events WHERE employee_id=? AND attendance_date=?')->execute([$employee,$day]);
 $db->prepare('DELETE FROM hr_requests WHERE id=?')->execute([$f['request']]);$db->prepare('DELETE FROM hr_attendance WHERE id=?')->execute([$f['attendance']]);$db->commit();exit;
}
$db->beginTransaction();try{
 $q=$db->prepare('SELECT id FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$employee,$day]);tl_check(!$q->fetchColumn(),'Fixture date must be unused');
 $last_record=(int)$db->query('SELECT COALESCE(MAX(id),0) FROM hr_records')->fetchColumn();
 $attendance=chr_save_attendance($db,$admin,['employee_id'=>$employee,'attendance_date'=>$day,'check_in'=>$day.' 18:00:00','check_out'=>'2026-03-05 03:00:00','source'=>'Admin Manual','notes'=>'CI timeline fixture']);
 $shift=['start'=>'18:00','end'=>'03:00','required_hours'=>8,'half_day_hours'=>4,'break_minutes'=>60,'grace'=>0,'early_grace'=>0,'overtime_rule'=>'After shift end','week_off'=>[]];
 $db->prepare("UPDATE hr_attendance SET source='Biometric',shift_snapshot=? WHERE id=?")->execute([json_encode($shift),$attendance]);
 $device=(int)$db->query("SELECT id FROM hr_records WHERE module='devices' ORDER BY id LIMIT 1")->fetchColumn();tl_check($device>0,'Missing test device');
 $events=[['at'=>strtotime($day.' 18:00:00'),'state'=>'in'],['at'=>strtotime($day.' 21:00:00'),'state'=>'out'],['at'=>strtotime($day.' 22:00:00'),'state'=>'in'],['at'=>strtotime('2026-03-05 03:00:00'),'state'=>'out']];
 foreach($events as $i=>$e){$key='ci-timeline-'.$i;$db->prepare('INSERT INTO hr_punches(device_code,biometric_id,event_key,punched_at,direction) VALUES(?,?,?,?,?)')->execute(['CI-SERIAL','CI-1',$key,date('Y-m-d H:i:s',$e['at']),$e['state']]);$punch=(int)$db->lastInsertId();$db->prepare('INSERT INTO hr_punch_processing(punch_id,device_id,employee_id,attendance_date,status,raw_payload,fingerprint) VALUES(?,?,?,?,?,?,?)')->execute([$punch,$device,$employee,$day,'Processed','{}',hash('sha256',$key)]);}
 $db->prepare("INSERT INTO hr_requests(user_id,kind,category,subject,details,start_date,end_date,status) VALUES(?,'regularisation','Correction','CI office duty','Client visit counted as break',?,?,'Pending')")->execute([$employee,$day,$day]);$request=(int)$db->lastInsertId();
 if($ui){$db->commit();file_put_contents('/tmp/timeline-fixture.json',json_encode(compact('employee','day','attendance','request','last_record')));echo "Timeline UI fixture ready.\n";exit;}
 $rawBefore=$db->query('SELECT * FROM hr_punches ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
 $c=att_timeline_context($db,$employee,$day);tl_check(count($c['sequence']['events'])===4,'Four original punches');$payload=att_timeline_payload($c,'2026-11-02');tl_check(!$payload['live']&&$payload['day']===$day,'Historical date remains explicit');tl_check($payload['revision']===$c['revision']&&$payload['events']===$events,'Polling snapshot retains events and concurrency revision');
 $changed=$events;$changed[2]['at']-=1800;
 $input=['employee_id'=>$employee,'attendance_date'=>$day,'timeline_events'=>json_encode($changed),'timeline_cutoff'=>time(),'timeline_revision'=>$c['revision'],'notes'=>'Approved client visit, shorten break by 30 minutes','request_id'=>$request];
 tl_blocked(fn()=>att_save_timeline($db,$viewer,$input),'Viewer must not save');
 tl_blocked(fn()=>att_save_timeline($db,$admin,array_replace($input,['notes'=>''])),'Reason required');
 tl_blocked(fn()=>att_save_timeline($db,$admin,array_replace($input,['timeline_revision'=>'wrong'])),'Stale revision rejected');
 tl_blocked(fn()=>att_save_timeline($db,$admin,array_replace($input,['request_id'=>PHP_INT_MAX])),'Unrelated request rejected');
 $reverse=$changed;$reverse[2]['at']=$reverse[1]['at'];tl_blocked(fn()=>att_timeline_validate($reverse,time(),time()),'Overlapping boundaries rejected');
 $future=$changed;$future[3]['at']=time()+60;tl_blocked(fn()=>att_timeline_validate($future,time(),time()),'Future punch rejected');
 tl_blocked(fn()=>att_timeline_validate([['at'=>time()-172800,'state'=>'in']],time(),time()),'Historical open shifts need explicit checkout');
 tl_blocked(fn()=>att_timeline_validate($changed,time()-172800,time()),'Expired editor rejected');
 att_save_timeline($db,$admin,$input);
 $saved=att_timeline_saved($db,$employee,$day);tl_check($saved['values']['reason']===$input['notes']&&count($saved['values']['before_events'])===4,'Reason and original timeline audited');
 $q=$db->query('SELECT * FROM hr_attendance WHERE id='.$attendance);$record=$q->fetch(PDO::FETCH_ASSOC);tl_check((int)$record['working_minutes']===510,'Shortening a break credits exactly 30 minutes');
 $report=chr_report_context($db,$day,$day);tl_check((int)$report['attendance'][$employee][$day]['working_minutes']===510,'Reports use corrected intervals, not fixed breaks');
 tl_check($rawBefore===$db->query('SELECT * FROM hr_punches ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Raw device punches immutable');
 tl_check($db->query('SELECT status FROM hr_requests WHERE id='.$request)->fetchColumn()==='Approved','Linked request approved');
 tl_blocked(fn()=>att_save_timeline($db,$admin,$input),'Repeat submission rejected');
 // An approved office-duty break becomes working time, with no double counting.
 $c=att_timeline_context($db,$employee,$day);$allWork=$changed;$allWork[1]['state']='in';
 $input=array_replace($input,['timeline_revision'=>$c['revision'],'timeline_events'=>json_encode($allWork),'request_id'=>0]);att_save_timeline($db,$admin,$input);
 $saved=att_timeline_saved($db,$employee,$day);$sequence=att_timeline_sequence($saved,$c['raw'],[]);$record=att_timeline_record($record,$sequence,time());tl_check($record['working_minutes']===540,'Office duty credited without overlapping intervals');
 $q=$db->prepare("SELECT COUNT(*) FROM hr_records WHERE module='attendance_timeline' AND employee_id=? AND status='Superseded'");$q->execute([$employee]);tl_check((int)$q->fetchColumn()===1,'Previous correction remains in history');
 // Later genuine events resume from the corrected state; repeats before cutoff do not toggle it.
 $after=['values'=>['events'=>[['at'=>time()-600,'state'=>'in']],'cutoff'=>time()-300]];
 $seq=att_timeline_sequence($after,[['id'=>1,'punched_at'=>date('Y-m-d H:i:s',time()-500),'direction'=>'unknown','status'=>'Processed'],['id'=>2,'punched_at'=>date('Y-m-d H:i:s',time()-100),'direction'=>'unknown','status'=>'Manual protected']],[]);
 tl_check($seq['state']==='out'&&count($seq['events'])===2,'Later unknown punch toggles once; old retransmission ignored');
 $seq=att_timeline_sequence($after,[],[['action'=>'Check Out','punched_at'=>date('Y-m-d H:i:s',time()-100)]]);tl_check($seq['state']==='out','Later admin checkout respected');
 // A fresh event in the same second as the editor snapshot must not disappear.
 $same=['values'=>['events'=>[['at'=>time()-600,'state'=>'in']],'cutoff'=>time(),'last_raw_id'=>5,'last_manual_id'=>10]];
 $seq=att_timeline_sequence($same,[['id'=>6,'punched_at'=>date('Y-m-d H:i:s'),'direction'=>'unknown','status'=>'Manual protected']],[]);tl_check($seq['state']==='out','Same-second new raw punch must count');
 $seq=att_timeline_sequence($same,[],[['id'=>11,'action'=>'Check Out','punched_at'=>date('Y-m-d H:i:s')]]);tl_check($seq['state']==='out','Same-second new administrator punch must count');
 // Existing attendance editor is an intentional replacement of the interval correction.
 $fresh=$db->query('SELECT * FROM hr_attendance WHERE id='.$attendance)->fetch(PDO::FETCH_ASSOC);
 chr_save_attendance($db,$admin,['id'=>$attendance,'version'=>$fresh['version'],'employee_id'=>$employee,'attendance_date'=>$day,'check_in'=>$day.' 18:00:00','check_out'=>'2026-03-05 03:00:00','source'=>'Admin Manual','notes'=>'Full record correction']);
 tl_check(att_timeline_saved($db,$employee,$day)===null,'Full-record edits supersede timeline override');
}finally{if($db->inTransaction())$db->rollBack();}
echo "PASS: timeline permissions, validation, stale guards, overnight intervals, office-duty credit, audit preservation, reports, request approval and later punches.\n";

