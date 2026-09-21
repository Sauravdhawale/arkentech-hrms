<?php
require __DIR__.'/includes/access.php';$user=require_user();$pdo=db();require __DIR__.'/includes/foundation/core.php';
$q=$pdo->prepare('SELECT f.*,r.employee_id FROM hr_files f JOIN hr_records r ON r.id=f.record_id WHERE f.id=?');$q->execute([(int)($_GET['id']??0)]);$f=$q->fetch(PDO::FETCH_ASSOC);
if(!$f||(!can($pdo,$user,'documents.view')&&(int)$f['employee_id']!==(int)$user['id'])){http_response_code(404);exit('Document unavailable.');}
header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9._ -]/','_',$f['filename']).'"');header('Content-Length: '.strlen($f['content']));echo $f['content'];
