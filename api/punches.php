<?php
// This endpoint accepts normalized events from an authenticated bridge, not eSSL SDK traffic.
require dirname(__DIR__).'/auth.php';session_write_close();header('Content-Type: application/json');
function answer(int $code,array $body):void {http_response_code($code);echo json_encode($body);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')answer(405,['error'=>'POST required']);
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')answer(400,['error'=>'HTTPS required']);
$configPath=dirname(__DIR__).'/config/biometric.local.php';$c=is_file($configPath)?require $configPath:[];
$key=(string)($c['api_key']??'');$provided=(string)($_SERVER['HTTP_AUTHORIZATION']??'');
if(strlen($key)<32||!hash_equals('Bearer '.$key,$provided))answer(401,['error'=>'Unauthorized']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>1000000)answer(413,['error'=>'Batch too large']);
$raw=file_get_contents('php://input',false,null,0,1000001);if(strlen($raw)>1000000)answer(413,['error'=>'Batch too large']);
try{
 $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$device=(string)($body['device_code']??'');$events=$body['events']??null;
 if(!preg_match('/^[A-Za-z0-9._-]{1,80}$/',$device)||!is_array($events)||count($events)<1||count($events)>500)answer(422,['error'=>'Invalid device or batch size (1–500)']);
 $pdo=db();require_once dirname(__DIR__).'/includes/core-hr/service.php';if(!att_biometric_enabled($pdo))answer(403,['error'=>'Biometric attendance is disabled']);$q=$pdo->query("SELECT data FROM hr_records WHERE module='devices' AND status='Enabled'");$enabled=false;foreach($q as $r)if((json_decode($r['data'],true)['device_code']??'')===$device)$enabled=true;if(!$enabled)answer(403,['error'=>'Device is not enabled']);
 foreach($events as $e){$date=(string)($e['punched_at']??'');$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date);if(!$d||$d->format('Y-m-d H:i:s')!==$date||!preg_match('/^[A-Za-z0-9._-]{1,80}$/',(string)($e['biometric_id']??''))||!preg_match('/^[A-Za-z0-9._:-]{1,128}$/',(string)($e['event_key']??''))||!in_array($e['direction']??'unknown',['in','out','unknown'],true))answer(422,['error'=>'Invalid event. No events were saved.']);}
 $pdo->beginTransaction();$insert=$pdo->prepare('INSERT INTO hr_punches(device_code,biometric_id,event_key,punched_at,direction) VALUES(?,?,?,?,?)');$accepted=0;$duplicates=0;
 foreach($events as $e){try{$insert->execute([$device,$e['biometric_id'],$e['event_key'],$e['punched_at'],$e['direction']??'unknown']);$accepted++;}catch(PDOException $err){if((int)($err->errorInfo[1]??0)!==1062)throw $err;$duplicates++;}}
 $pdo->prepare('INSERT INTO hr_sync_events(device_code,accepted,duplicates) VALUES(?,?,?)')->execute([$device,$accepted,$duplicates]);$pdo->commit();answer(200,['accepted'=>$accepted,'duplicates'=>$duplicates]);
}catch(JsonException $e){answer(422,['error'=>'Invalid JSON']);}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();answer(503,['error'=>'Sync unavailable. Retry the same event keys later.']);}
