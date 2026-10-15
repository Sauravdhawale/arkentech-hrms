<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/includes/maintenance/payroll-cleanup.php';
$options=getopt('',['apply','database:']);
try{$db=db();$database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
 if(isset($options['apply'])&&($options['database']??'')!==$database)throw new InvalidArgumentException('Supply --database with the exact deployed database name when applying.');
 echo json_encode(['database'=>$database]+payroll_cleanup($db,isset($options['apply'])),JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
