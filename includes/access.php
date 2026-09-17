<?php
require_once dirname(__DIR__).'/auth.php';
function require_user(?string $role=null): array {
 if (!isset($_SESSION['user']['id'])) { header('Location: login.php'); exit; }
 try { $q=db()->prepare('SELECT id,name,email,role FROM users WHERE id=? AND active=1'); $q->execute([$_SESSION['user']['id']]); $u=$q->fetch(PDO::FETCH_ASSOC); }
 catch(Throwable $e) { http_response_code(503); exit('Workspace unavailable. Please contact your administrator.'); }
 if (!$u || !in_array($u['role'],['super_admin','employee'],true)) { $_SESSION=[]; header('Location: login.php'); exit; }
 if ($role && $u['role']!==$role) { http_response_code(403); exit('You do not have access to this workspace.'); }
 $_SESSION['user']=$u; return $u;
}
function h($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
