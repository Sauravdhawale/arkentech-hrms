<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/foundation/organization.php';
$options=getopt('',['apply','fingerprint:','actor:']);
try{
 $db=db();if(!foundation_ready($db))throw new InvalidArgumentException('Existing foundation database required.');
 if(isset($options['apply'])){
  $q=$db->prepare("SELECT id,role FROM users WHERE id=? AND role='super_admin' AND active=1");$q->execute([(int)($options['actor']??0)]);$actor=$q->fetch(PDO::FETCH_ASSOC);if(!$actor)throw new InvalidArgumentException('Supply --actor=<active Super Admin ID> for the audit trail.');
  $result=org_apply($db,$actor,(string)($options['fingerprint']??''));
 }else $result=org_plan(org_snapshot($db));
 echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
