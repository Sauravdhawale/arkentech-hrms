<?php
require __DIR__.'/includes/access.php';$user=require_user();$pdo=db();require __DIR__.'/includes/foundation/core.php';
$owner=(int)($_GET['employee']??0);$purpose=$owner?'profile':'company_logo';if($owner&&(int)$user['id']!==$owner&&!can($pdo,$user,'employees.view')){http_response_code(404);exit;}
$q=$pdo->prepare('SELECT mime,content FROM hr_media WHERE purpose=? AND user_id <=> ? ORDER BY id DESC LIMIT 1');$q->execute([$purpose,$owner?:null]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r){http_response_code(404);exit;}
header('Content-Type: '.$r['mime']);header('Content-Length: '.strlen($r['content']));header('Content-Security-Policy: default-src \'none\'');echo $r['content'];
