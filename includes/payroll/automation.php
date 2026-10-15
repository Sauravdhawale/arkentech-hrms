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

/** Scheduler entry point. Disabled unless explicitly configured; existing runs are preserved. */
function pay_scheduled_monthly(PDO $db,array $actor,string $month,?DateTimeImmutable $now=null):array {
 pay_allow($db,$actor,'payroll.process.manage');
 $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Kolkata'));
 [$from,$to]=pay_month($month);$settings=pay_settings($db,$from);
 if(empty($settings['auto_payroll']))return ['created'=>false,'message'=>'Automatic payroll is off for this period.'];
 $due=new DateTimeImmutable((new DateTimeImmutable($to))->modify('+'.(int)$settings['closing_day'].' days')->format('Y-m-d').' '.($settings['schedule_time']??'12:00'),new DateTimeZone('Asia/Kolkata'));
 if($now<$due)return ['created'=>false,'message'=>'Scheduled processing is due '.$due->format('Y-m-d H:i').' IST.'];
 $ctx=chr_report_context($db,$to,$to);$buffer=max(0,(int)($ctx['punch_after_minutes']??120));
 $q=$db->query("SELECT e.user_id FROM employees e JOIN users u ON u.id=e.user_id WHERE e.deleted_at IS NULL AND e.employment_status='Active' AND u.active=1");
 foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){$e=foundation_employee($db,(int)$id);$day=chr_day($ctx,$e,$to);if(!empty($day['scheduled_out'])){$ready=(new DateTimeImmutable($day['scheduled_out'],new DateTimeZone('Asia/Kolkata')))->modify('+'.$buffer.' minutes');if($now<$ready)return ['created'=>false,'message'=>'Waiting for the final shift and attendance collection window to close.'];}}
 $result=pay_generate_monthly($db,$actor,$month);
 if(!$result['created'])return $result;
 $run=pay_run($db,$result['run_id']);
 $q=$db->prepare("SELECT COUNT(*) FROM hr_payroll_entries WHERE run_id=? AND (exceptions<>'' OR status='On Hold')");$q->execute([$run['id']]);
 if($q->fetchColumn()){$result['message']='Payroll calculated with exceptions. Correct attendance/salary and recalculate; incorrect payslips were not generated.';return $result;}
 if(empty($settings['approval_required'])&&!empty($settings['auto_payslip'])){
  foreach(['review','finalize'] as $action){$run=pay_run($db,$result['run_id']);pay_transition($db,$actor,['run_id'=>$run['id'],'version'=>$run['version'],'transition'=>$action,'confirm'=>1,'reason'=>'Configured automatic month-close payroll']);}
  $result['message']='Payroll finalized and payslips published automatically.';
 }else $result['message']='Monthly payroll calculated. Draft payslips are available when automatic generation is enabled; approval is still required before publication.';
 return $result;
}

