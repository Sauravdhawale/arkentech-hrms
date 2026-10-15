<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/payroll/automation.php';
date_default_timezone_set('Asia/Kolkata');
$options=getopt('',['actor:','month:','apply','scheduled']);
try{
 $db=db();$q=$db->prepare("SELECT * FROM users WHERE id=? AND active=1 AND role='super_admin'");$q->execute([(int)($options['actor']??0)]);$actor=$q->fetch(PDO::FETCH_ASSOC);
 if(!$actor)throw new InvalidArgumentException('Supply --actor with an active Super Admin user ID.');
 $month=$options['month']??(new DateTimeImmutable('first day of last month'))->format('Y-m');[, $to]=pay_month($month);
 if($to>=date('Y-m-d'))throw new InvalidArgumentException('Choose a completed month.');
 if(!isset($options['apply'])){echo 'DRY RUN: would prepare '.$month." from saved salaries and attendance. Add --apply to create and calculate; nothing will be published.\n";exit;}
 echo json_encode(isset($options['scheduled'])?pay_scheduled_monthly($db,$actor,$month):pay_generate_monthly($db,$actor,$month),JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}

