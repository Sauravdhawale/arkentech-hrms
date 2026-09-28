<?php
require __DIR__.'/includes/access.php';$user=require_user();$pdo=db();require __DIR__.'/includes/foundation/core.php';
$q=$pdo->prepare('SELECT f.*,r.employee_id,r.module,r.data FROM hr_files f JOIN hr_records r ON r.id=f.record_id WHERE f.id=?');$q->execute([(int)($_GET['id']??0)]);$f=$q->fetch(PDO::FETCH_ASSOC);
if(!$f||(!(($f['module']==='leave_attachment'&&can($pdo,$user,'leave.approve'))||can($pdo,$user,$f['module']==='leave_attachment'?'leave.view':'documents.view'))&&(int)$f['employee_id']!==(int)$user['id'])){http_response_code(404);exit('Document unavailable.');}
if($f['module']==='leave_attachment'&&$user['role']!=='super_admin'&&(int)$f['employee_id']!==(int)$user['id']){require_once __DIR__.'/includes/ess/workflow.php';$data=json_decode($f['data'],true);$q=$pdo->prepare("SELECT * FROM hr_requests WHERE id=? AND kind='leave'");$q->execute([(int)($data['request_id']??0)]);$request=$q->fetch(PDO::FETCH_ASSOC);if(!$request||!ess_route_member($pdo,$user,$request)){http_response_code(404);exit('Document unavailable.');}}
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9._ -]/','_',$f['filename']).'"');header('Content-Length: '.strlen($f['content']));echo $f['content'];
