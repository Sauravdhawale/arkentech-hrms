<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/core-hr/controller.php';
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
function live_check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function live_blocked(callable $fn,string $why):void{$bad=false;try{$fn();}catch(InvalidArgumentException $e){$bad=true;}live_check($bad,$why);}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];$viewer=['id'=>3,'role'=>'employee'];$fixture=json_decode(file_get_contents('/tmp/core-hr-fixture.json'),true);$employee=(int)$fixture['employee'];
$db->beginTransaction();try{
 $rawCount=(int)$db->query('SELECT COUNT(*) FROM hr_punches')->fetchColumn();
 $now=new DateTimeImmutable();$start=$now->modify('-2 hours');$end=$now->modify('+6 hours');
 $roster=chr_record($db,'roster',$fixture['roster']);$values=$roster['values'];$values['from']='2020-01-01';$values['to']=null;
 $values['shift_snapshot']=['start'=>$start->format('H:i'),'end'=>$end->format('H:i'),'required_hours'=>7,'half_day_hours'=>3,'grace'=>0,'early_grace'=>0,'break_minutes'=>60,'week_off'=>[],'overtime_rule'=>'After shift end'];
 $db->prepare("UPDATE hr_records SET data=?,status='Active' WHERE id=?")->execute([json_encode($values),$fixture['roster']]);
 $day=att_punch_shift($db,$employee,$now->format('Y-m-d H:i:s'))['day'];
 $db->prepare('DELETE FROM hr_manual_punch_events WHERE employee_id=? AND attendance_date=?')->execute([$employee,$day]);
 $db->prepare('DELETE FROM hr_attendance WHERE employee_id=? AND attendance_date=?')->execute([$employee,$day]);
 $status=ess_punch_status($db,$employee);live_check($status['state']==='none'&&$status['attendance_date']===$day,'Empty current shift must be visible');
 $input=['employee_id'=>$employee,'punch_action'=>'Check In','notes'=>'CI administrator punch','request_key'=>str_repeat('c',64),'presence_revision'=>att_live_revision($status)];
 live_blocked(fn()=>att_live_punch($db,$viewer,$input),'Read-only actor must not punch');
 live_blocked(fn()=>att_live_punch($db,$admin,array_replace($input,['notes'=>''])),'Reason is required');
 $id=att_live_punch($db,$admin,$input);live_blocked(fn()=>att_live_punch($db,$admin,$input),'Double submission must be blocked');
 live_check(ess_punch_status($db,$employee)['state']==='in','Manual IN must update presence');
 // Move only this disposable test punch back one minute; no sleeping or production records.
 $earlier=date('Y-m-d H:i:s',time()-60);$db->prepare('UPDATE hr_attendance SET check_in=? WHERE id=?')->execute([$earlier,$id]);$db->prepare('UPDATE hr_manual_punch_events SET punched_at=? WHERE employee_id=? AND attendance_date=?')->execute([$earlier,$employee,$day]);
 $status=ess_punch_status($db,$employee);$out=array_replace($input,['punch_action'=>'Check Out','request_key'=>str_repeat('d',64),'presence_revision'=>att_live_revision($status)]);
 att_live_punch($db,$admin,$out);live_check(ess_punch_status($db,$employee)['state']==='out','Manual OUT must update presence');
 live_blocked(fn()=>att_live_punch($db,$admin,array_replace($out,['request_key'=>str_repeat('e',64)])),'Stale status must not overwrite a newer punch');
 $status=ess_punch_status($db,$employee);att_live_punch($db,$admin,array_replace($input,['request_key'=>str_repeat('f',64),'presence_revision'=>att_live_revision($status)]));
 live_check(ess_punch_status($db,$employee)['state']==='in','Re-entry must be visible even immediately after explicit OUT');
 live_check($db->query('SELECT check_in FROM hr_attendance WHERE id='.$id)->fetchColumn()===$earlier,'Re-entry must preserve first check-in');
 live_check((int)$db->query('SELECT COUNT(*) FROM hr_punches')->fetchColumn()===$rawCount,'Admin action must not delete or invent device punches');
 $q=$db->prepare('SELECT COUNT(*) FROM hr_manual_punch_events WHERE employee_id=? AND attendance_date=?');$q->execute([$employee,$day]);live_check((int)$q->fetchColumn()===3,'All manual punches must have audit events');
 // Move a disposable shift into overtime, beyond the configured intake buffer.
 $start=$now->modify('-10 hours');$end=$now->modify('-4 hours');$overtimeDay=$start->format('Y-m-d');
 $values['shift_snapshot']['start']=$start->format('H:i');$values['shift_snapshot']['end']=$end->format('H:i');
 $db->prepare('UPDATE hr_records SET data=? WHERE id=?')->execute([json_encode($values),$fixture['roster']]);
 $db->prepare('DELETE FROM hr_manual_punch_events WHERE employee_id=? AND attendance_date IN (?,?)')->execute([$employee,$overtimeDay,$day]);
 $db->prepare('DELETE FROM hr_attendance WHERE employee_id=? AND attendance_date IN (?,?)')->execute([$employee,$overtimeDay,$day]);
 $overtimeId=chr_save_attendance($db,$admin,['employee_id'=>$employee,'attendance_date'=>$overtimeDay,'check_in'=>$start->format('Y-m-d H:i:s'),'source'=>'Admin Manual','notes'=>'CI open overtime shift']);
 $status=ess_punch_status($db,$employee);
 live_check($status['state']==='in'&&!$status['timing']['expired'],'Elapsed buffer cannot replace actual IN with review/OUT');
 live_check($status['attendance_date']===$overtimeDay&&$status['timing']['after_end']>=4*3600,'Open overtime retains shift date and counts beyond intake buffer');
 $live=att_live_people($db,['employee_id'=>$employee]);live_check($live['people'][0]['presence']['state']==='in','Admin list and employee status agree after shift end');
 $out=array_replace($input,['punch_action'=>'Check Out','request_key'=>hash('sha256','live-overtime-checkout-regression'),'presence_revision'=>att_live_revision($status)]);
 att_live_punch($db,$admin,$out);$status=ess_punch_status($db,$employee);
 live_check($status['state']==='out','Admin live checkout works after the old buffer expires');
 live_check((bool)$db->query('SELECT check_out FROM hr_attendance WHERE id='.$overtimeId)->fetchColumn(),'Checkout only persisted following explicit admin action');
 // A fixed historical night shift: first punch after midnight still belongs to the prior day.
 $values['shift_snapshot']['start']='22:00';$values['shift_snapshot']['end']='06:00';$db->prepare('UPDATE hr_records SET data=? WHERE id=?')->execute([json_encode($values),$fixture['roster']]);
 $nightDay='2026-02-20';$db->prepare('DELETE FROM hr_attendance WHERE employee_id=? AND attendance_date=?')->execute([$employee,$nightDay]);
 $night=chr_save_attendance($db,$admin,['employee_id'=>$employee,'attendance_date'=>$nightDay,'check_in'=>'2026-02-21 00:30:00','source'=>'Admin Manual','notes'=>'CI night-shift correction']);
 live_check($db->query('SELECT attendance_date FROM hr_attendance WHERE id='.$night)->fetchColumn()===$nightDay,'After-midnight first IN uses the shift start date');
 live_blocked(fn()=>chr_save_attendance($db,$admin,['employee_id'=>$employee,'attendance_date'=>'2026-02-18','check_in'=>'2026-02-21 00:30:00','source'=>'Admin Manual','notes'=>'Invalid date']),'Unrelated dates must still be rejected');
}finally{if($db->inTransaction())$db->rollBack();}
echo "PASS: live admin permission, reason, duplicate/concurrent guards, IN/OUT/re-entry, audit preservation and overnight assignment.\n";
