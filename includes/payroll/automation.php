<?php
require_once __DIR__.'/service.php';
/** Creates a reviewable draft once. Existing runs are never recalculated automatically. */
function pay_generate_monthly(PDO $db,array $actor,string $month):array {
 pay_allow($db,$actor,'payroll.process.manage');
 [$from,$to]=pay_month($month);
 if($to>=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d'))throw new InvalidArgumentException('Automatic payroll requires a completed month.');
 if(!pay_ready($db))throw new InvalidArgumentException('Enable payroll first.');
 $lock='shrms_monthly_'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,24);
 $q=$db->prepare('SELECT GET_LOCK(?,10)');$q->execute([$lock]);if(!$q->fetchColumn())throw new RuntimeException('Monthly payroll is already running.');
 try{
  $q=$db->prepare('SELECT id,status FROM hr_payroll_runs WHERE month=?');$q->execute([$month]);
  if($existing=$q->fetch(PDO::FETCH_ASSOC))return ['run_id'=>(int)$existing['id'],'created'=>false,'message'=>'Existing run preserved; review or recalculate it in Payroll.'];
  $id=pay_create_run($db,$actor,$month);
  $run=pay_run($db,$id);
  pay_calculate_run($db,$actor,['run_id'=>$id,'version'=>$run['version']]);
  return ['run_id'=>$id,'created'=>true,'message'=>'Calculated from attendance. Review exceptions, then approve and publish.'];
 }finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
}
