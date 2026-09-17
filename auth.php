<?php
declare(strict_types=1);
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']);
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (isset($_SESSION['last_active']) && time()-$_SESSION['last_active'] > 1800) { $_SESSION=[]; session_regenerate_id(true); }
$_SESSION['last_active']=time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function db(): PDO {
 return new PDO('mysql:host='.(getenv('DB_HOST') ?: 'localhost').';dbname='.getenv('DB_NAME').';charset=utf8mb4',getenv('DB_USER') ?: '',getenv('DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function csrf(): void { if (!hash_equals($_SESSION['csrf'],$_POST['csrf'] ?? '')) { http_response_code(403); exit('Invalid request. Reload the page.'); } }
