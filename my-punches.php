<?php
require __DIR__.'/includes/access.php';
$user=require_user();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo json_encode(['error'=>'Use GET.']);exit;}
require_once __DIR__.'/includes/core-hr/service.php';
require_once __DIR__.'/includes/ess/punch-history.php';
try{
 $pdo=db();$company=$pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);date_default_timezone_set($company['timezone']??'Asia/Kolkata');
 $date=$_GET['date']??'';if(!is_string($date))throw new InvalidArgumentException('Choose a valid shift date.');
 // Ownership is always the authenticated account, never a URL-supplied employee ID.
 echo json_encode(ess_punch_history($pdo,(int)$user['id'],$date),JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Punch history is temporarily unavailable. Please try again.']);}
