<?php
require dirname(__DIR__,3).'/auth.php';session_write_close();require dirname(__DIR__,3).'/includes/attendance/bridge.php';
header('Content-Type: application/json');
function bridge_answer(int $status,array $data):never{http_response_code($status);echo json_encode($data);exit;}
function bridge_body():array{if((int)($_SERVER['CONTENT_LENGTH']??0)>1000000)bridge_answer(413,['error'=>'Batch too large']);$raw=file_get_contents('php://input',false,null,0,1000001);if(strlen($raw)>1000000)bridge_answer(413,['error'=>'Batch too large']);$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($body))throw new InvalidArgumentException('JSON object required');return $body;}
function bridge_run(string $method,callable $callback):never{
 if($_SERVER['REQUEST_METHOD']!==$method)bridge_answer(405,['error'=>$method.' required']);if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')bridge_answer(400,['error'=>'HTTPS required']);
 try{$db=db();$timezone=$db->query('SELECT timezone FROM company_settings WHERE id=1')->fetchColumn();if($timezone)date_default_timezone_set($timezone);if(!att_biometric_enabled($db))bridge_answer(403,['error'=>'Biometric attendance disabled']);$device=att_bridge_auth($db,(string)($_SERVER['HTTP_AUTHORIZATION']??''));$result=$callback($db,$device);bridge_answer(200,$result);}
 catch(UnexpectedValueException $e){bridge_answer(401,['error'=>'Unauthorized or disabled device']);}catch(JsonException|InvalidArgumentException $e){bridge_answer(422,['error'=>$e->getMessage()]);}catch(Throwable $e){error_log('HRMS bridge request failed: '.get_class($e));bridge_answer(503,['error'=>'Sync unavailable. Retry the same event keys.']);}
}
