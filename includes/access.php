<?php
require_once dirname(__DIR__).'/auth.php';
function require_user(?string $role=null): array {
 if (!isset($_SESSION['user']['id'])) { header('Location: login.php'); exit; }
 try { $q=db()->prepare('SELECT * FROM users WHERE id=? AND active=1'); $q->execute([$_SESSION['user']['id']]); $u=$q->fetch(PDO::FETCH_ASSOC); }
 catch(Throwable $e) { http_response_code(503); exit('Workspace unavailable. Please contact your administrator.'); }
 if ($u && isset($u['session_version']) && (int)($u['session_version'])!==(int)($_SESSION['user']['session_version']??0)) {$_SESSION=[];session_regenerate_id(true);header('Location: login.php');exit;}
 if (!$u || !in_array($u['role'],['super_admin','employee'],true)) { $_SESSION=[]; header('Location: login.php'); exit; }
 if ($role && $u['role']!==$role) { http_response_code(403); exit('You do not have access to this workspace.'); }
 unset($u['password_hash']);
 if(!empty($u['must_change_password']) && !(basename($_SERVER['SCRIPT_NAME'])===($u['role']==='super_admin'?'super-admin.php':'employee.php') && ($_GET['page']??'')===($u['role']==='super_admin'?'system':'security'))) {header('Location: '.($u['role']==='super_admin'?'super-admin.php?page=system':'employee.php?page=security'));exit;}
 $_SESSION['user']=$u; return $u;
}
function h($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
