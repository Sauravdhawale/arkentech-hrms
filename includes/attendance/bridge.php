<?php
require_once dirname(__DIR__).'/core-hr/service.php';
function att_device(PDO $db,int $id):array{$r=chr_record($db,'devices',$id);if(!$r)throw new InvalidArgumentException('Device not found.');return $r;}
function att_save_device(PDO $db,array $actor,array $in):void {
 att_require_biometric($db);if(!can($db,$actor,'biometric.manage'))throw new InvalidArgumentException('Device management is not permitted.');$code=ftext($in,'device_code',80,true);if(!preg_match('/^[A-Za-z0-9._-]+$/D',$code))throw new InvalidArgumentException('Use letters, numbers, dots, underscores or hyphens for the serial.');
 $db->beginTransaction();try{chr_lock($db);$id=(int)($in['id']??0);$old=$id?att_device($db,$id):null;if($old&&($old['values']['device_code']??'')!==$code)throw new InvalidArgumentException('Device serial is immutable. Register a new device.');foreach(chr_rows($db,'devices') as $r)if((int)$r['id']!==$id&&($r['values']['device_code']??'')===$code)throw new InvalidArgumentException('Device serial already exists.');$v=$old['values']??[];foreach(['model','host','location','notes'] as $key)$v[$key]=ftext($in,$key,$key==='notes'?2000:190);$v['device_code']=$code;att_record_write($db,$actor,'devices',$in,$v,null,!empty($in['active'])?'Enabled':'Disabled');$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function att_save_mapping(PDO $db,array $actor,array $in):void {
 att_require_biometric($db);if(!can($db,$actor,'biometric.manage'))throw new InvalidArgumentException('Mapping management is not permitted.');$device=att_device($db,(int)($in['device_id']??0));$employee=(int)($in['employee_id']??0);$e=foundation_employee($db,$employee);if(!$e||!empty($e['deleted_at']))throw new InvalidArgumentException('Employee not found.');$bio=ftext($in,'biometric_id',80,true);if(!preg_match('/^[A-Za-z0-9._-]+$/D',$bio))throw new InvalidArgumentException('Invalid biometric ID.');
 $db->beginTransaction();try{chr_lock($db);$id=(int)($in['id']??0);foreach(chr_rows($db,'mapping') as $r)if((int)$r['id']!==$id&&($r['values']['device_code']??'')===$device['values']['device_code']&&($r['values']['biometric_id']??'')===$bio)throw new InvalidArgumentException('Mapping exists. Edit the existing record.');att_record_write($db,$actor,'mapping',$in,['device_code'=>$device['values']['device_code'],'biometric_id'=>$bio],$employee,!empty($in['active'])?'Active':'Inactive');$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function att_device_action(PDO $db,array $actor,array $in):void {
 att_require_biometric($db);$action=$in['device_action']??'';$permission=in_array($action,['rotate','revoke'],true)?'biometric.manage':'biometric.sync';if(!can($db,$actor,$permission))throw new InvalidArgumentException('Device action is not permitted.');$device=att_device($db,(int)($in['device_id']??0));
 $db->beginTransaction();try{chr_lock($db);if(in_array($action,['rotate','revoke'],true)){$db->prepare('UPDATE hr_bridge_tokens SET active=0 WHERE device_id=?')->execute([$device['id']]);if($action==='rotate'){$token=bin2hex(random_bytes(32));$db->prepare('INSERT INTO hr_bridge_tokens(device_id,token_hash,created_by) VALUES(?,?,?)')->execute([$device['id'],hash('sha256',$token),$actor['id']]);}}
 elseif(in_array($action,['sync','test'],true)){$column=$action==='sync'?'sync_requested_at':'test_requested_at';$db->prepare('INSERT INTO hr_bridge_health(device_id,'.$column.') VALUES(?,NOW()) ON DUPLICATE KEY UPDATE '.$column.'=NOW()')->execute([$device['id']]);}else throw new InvalidArgumentException('Unknown device action.');faudit($db,$actor,'biometric.'.$action,(int)$device['id']);$db->commit();if(isset($token))$_SESSION['bridge_token_once']=['serial'=>$device['values']['device_code'],'token'=>$token];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function att_bridge_auth(PDO $db,string $authorization):array {
 att_require_biometric($db);if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m))throw new UnexpectedValueException('Unauthorized');$q=$db->prepare('SELECT * FROM hr_bridge_tokens WHERE token_hash=? AND active=1 AND (expires_at IS NULL OR expires_at>NOW())');$q->execute([hash('sha256',$m[1])]);$token=$q->fetch(PDO::FETCH_ASSOC);if(!$token)throw new UnexpectedValueException('Unauthorized');$device=att_device($db,(int)$token['device_id']);if($device['status']!=='Enabled')throw new UnexpectedValueException('Device disabled');$db->prepare('UPDATE hr_bridge_tokens SET last_used_at=NOW() WHERE id=?')->execute([$token['id']]);$device['token_id']=$token['id'];$device['actor_id']=$token['created_by'];return $device;
}
function att_process_punch(PDO $db,array $device,int $punchId,array $event):void {
 $maps=array_values(array_filter(chr_rows($db,'mapping'),fn($r)=>$r['status']==='Active'&&($r['values']['device_code']??'')===$device['values']['device_code']&&($r['values']['biometric_id']??'')===$event['biometric_id']));
 $state='Needs mapping';$error='No unique active employee mapping';$employee=null;$day=null;
 if(count($maps)===1){$employee=(int)$maps[0]['employee_id'];$e=foundation_employee($db,$employee);if(!$e||!empty($e['deleted_at'])||empty($e['active'])){$state='Inactive employee';$error='Mapped employee is archived or unavailable';}else{
 $day=substr($event['punched_at'],0,10);$previous=(new DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d');$prior=chr_assignment($db,$employee,$previous);if($prior){[$start,$end]=chr_shift_bounds($previous,$prior['values']);if($end->format('Y-m-d')===$day&&new DateTimeImmutable($event['punched_at'])<=$end->modify('+6 hours'))$day=$previous;}
 $q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=? FOR UPDATE');$q->execute([$employee,$day]);$existing=$q->fetch(PDO::FETCH_ASSOC);
 $manualAbsence=false;foreach(chr_rows($db,'attendance_status') as $mark)if($mark['status']==='Active'&&(int)$mark['employee_id']===$employee&&($mark['values']['date']??'')===$day)$manualAbsence=true;
 if($manualAbsence||($existing&&$existing['source']!=='Biometric')){$state='Manual protected';$error='Raw punch retained; manual attendance was not overwritten';}else{
 $db->prepare('UPDATE hr_punch_processing SET employee_id=?,attendance_date=? WHERE punch_id=?')->execute([$employee,$day,$punchId]);$q=$db->prepare('SELECT p.punched_at,p.direction FROM hr_punches p JOIN hr_punch_processing x ON x.punch_id=p.id WHERE x.employee_id=? AND x.attendance_date=? ORDER BY p.punched_at,p.id');$q->execute([$employee,$day]);$all=$q->fetchAll(PDO::FETCH_ASSOC);$in=null;$out=null;foreach($all as $p){if($in===null&&$p['direction']!=='out')$in=$p['punched_at'];if($in!==null&&$p['punched_at']>$in&&$p['direction']!=='in')$out=$p['punched_at'];}
 if(!$in||substr($in,0,10)!==$day){$state='Review needed';$error='No check-in on the resolved shift date';}else{$shift=chr_assignment($db,$employee,$day);$snapshot=$existing?json_decode($existing['shift_snapshot'],true):($shift['values']??null);$metrics=chr_calculate($day,$in,$out,$snapshot);$v=[$in,$out,$existing?$existing['shift_id']:($shift['id']??null),json_encode($snapshot),$metrics['working_minutes'],$metrics['late_minutes'],$metrics['early_minutes'],$metrics['overtime_minutes'],$metrics['status']];
 if($existing)$db->prepare('UPDATE hr_attendance SET check_in=?,check_out=?,shift_id=?,shift_snapshot=?,working_minutes=?,late_minutes=?,early_minutes=?,overtime_minutes=?,status=?,version=version+1 WHERE id=?')->execute([...$v,$existing['id']]);else $db->prepare("INSERT INTO hr_attendance(check_in,check_out,shift_id,shift_snapshot,working_minutes,late_minutes,early_minutes,overtime_minutes,status,source,notes,updated_by,employee_id,attendance_date) VALUES(?,?,?,?,?,?,?,?,?,'Biometric','Bridge calculation',?,?,?)")->execute([...$v,$device['actor_id'],$employee,$day]);$state='Processed';$error='';}
 }}}
 $db->prepare('UPDATE hr_punch_processing SET employee_id=?,attendance_date=?,status=?,error_message=?,processed_at=NOW() WHERE punch_id=?')->execute([$employee,$day,$state,$error,$punchId]);
}
function att_bridge_batch(PDO $db,array $device,array $events):array {
 if(count($events)<1||count($events)>500)throw new InvalidArgumentException('Batch must contain 1–500 punches.');$normalized=[];foreach($events as $event){if(!is_array($event))throw new InvalidArgumentException('Invalid punch.');$bio=(string)($event['biometric_id']??'');$key=(string)($event['event_key']??'');$direction=$event['direction']??'unknown';if(!preg_match('/^[A-Za-z0-9._-]{1,80}$/D',$bio)||!preg_match('/^[A-Za-z0-9._:-]{1,128}$/D',$key)||!in_array($direction,['in','out','unknown'],true))throw new InvalidArgumentException('Invalid punch fields.');$at=chr_datetime((string)($event['punched_at']??''));if($at>date('Y-m-d H:i:s'))throw new InvalidArgumentException('Future punch timestamp.');$normalized[]=['biometric_id'=>$bio,'event_key'=>$key,'direction'=>$direction,'punched_at'=>$at];}
 $db->beginTransaction();try{chr_lock($db);att_require_biometric($db);$current=att_device($db,(int)$device['id']);$q=$db->prepare('SELECT active FROM hr_bridge_tokens WHERE id=?');$q->execute([$device['token_id']]);if($current['status']!=='Enabled'||!$q->fetchColumn())throw new UnexpectedValueException('Device/token disabled');$accepted=[];$duplicates=[];
 foreach($normalized as $e){$fingerprint=hash('sha256',$device['id'].'|'.$e['biometric_id'].'|'.$e['punched_at'].'|'.$e['direction']);$q=$db->prepare('SELECT punch_id FROM hr_punch_processing WHERE fingerprint=?');$q->execute([$fingerprint]);if($q->fetchColumn()){$duplicates[]=$e['event_key'];continue;}
 $q=$db->prepare('SELECT id,biometric_id,punched_at,direction FROM hr_punches WHERE device_code=? AND event_key=?');$q->execute([$device['values']['device_code'],$e['event_key']]);$old=$q->fetch(PDO::FETCH_ASSOC);if($old){foreach(['biometric_id','punched_at','direction'] as $k)if($old[$k]!==$e[$k])throw new InvalidArgumentException('An event key was reused with different punch data.');$duplicates[]=$e['event_key'];continue;}
 $db->prepare('INSERT INTO hr_punches(device_code,biometric_id,event_key,punched_at,direction) VALUES(?,?,?,?,?)')->execute([$device['values']['device_code'],$e['biometric_id'],$e['event_key'],$e['punched_at'],$e['direction']]);$id=(int)$db->lastInsertId();$db->prepare('INSERT INTO hr_punch_processing(punch_id,device_id,raw_payload,fingerprint) VALUES(?,?,?,?)')->execute([$id,$device['id'],json_encode($e),$fingerprint]);try{att_process_punch($db,$device,$id,$e);}catch(InvalidArgumentException $err){$db->prepare("UPDATE hr_punch_processing SET status='Review needed',error_message=? WHERE punch_id=?")->execute([substr($err->getMessage(),0,500),$id]);}$accepted[]=$e['event_key'];}
 $db->prepare("INSERT INTO hr_bridge_sync(device_id,started_at,completed_at,received,imported,duplicates,status) VALUES(?,NOW(),NOW(),?,?,?,'Saved')")->execute([$device['id'],count($events),count($accepted),count($duplicates)]);$db->prepare('INSERT INTO hr_bridge_health(device_id,last_seen,last_success) VALUES(?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_seen=NOW(),last_success=NOW()')->execute([$device['id']]);$db->commit();return ['accepted'=>$accepted,'duplicates'=>$duplicates,'persisted'=>true];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/** Retry a bounded, device-scoped batch on every authenticated bridge heartbeat. */
function att_retry_device_punches(PDO $db,array $device):int {
 att_require_biometric($db);
 $db->beginTransaction();
 try {
  chr_lock($db);
  $current=att_device($db,(int)$device['id']);
  if($current['status']!=='Enabled')throw new UnexpectedValueException('Device disabled');
  if(isset($device['token_id'])){
   $q=$db->prepare('SELECT id FROM hr_bridge_tokens WHERE id=? AND device_id=? AND active=1 AND (expires_at IS NULL OR expires_at>NOW())');
   $q->execute([$device['token_id'],$device['id']]);
   if(!$q->fetchColumn())throw new UnexpectedValueException('Device/token disabled');
  }
  // Oldest attempt first: unresolved mappings must not starve later records.
  $q=$db->prepare("SELECT p.* FROM hr_punches p JOIN hr_punch_processing x ON x.punch_id=p.id WHERE x.device_id=? AND x.status IN ('Needs mapping','Review needed','Inactive employee','Pending') ORDER BY x.processed_at,p.punched_at,p.id LIMIT 100 FOR UPDATE");
  $q->execute([$device['id']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as $r){
   try{att_process_punch($db,$device,(int)$r['id'],$r);}
   catch(InvalidArgumentException $e){$db->prepare("UPDATE hr_punch_processing SET status='Review needed',error_message=?,processed_at=NOW() WHERE punch_id=?")->execute([substr($e->getMessage(),0,500),$r['id']]);}
  }
  $db->commit();return count($rows);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function att_retry_punches(PDO $db,array $actor,array $in):int {
 att_require_biometric($db);if(!can($db,$actor,'biometric.sync'))throw new InvalidArgumentException('Biometric sync is not permitted.');
 $device=att_device($db,(int)($in['device_id']??0));$device['actor_id']=$actor['id'];
 $count=att_retry_device_punches($db,$device);faudit($db,$actor,'biometric.raw.retry',(int)$device['id']);return $count;
}
