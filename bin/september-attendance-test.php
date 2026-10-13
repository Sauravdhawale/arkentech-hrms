<?php
// Explicit, audited status overrides for the user-requested September 2026 test.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/core-hr/controller.php';require_once dirname(__DIR__).'/includes/payroll/service.php';
date_default_timezone_set('Asia/Kolkata');$options=getopt('',['actor:','apply']);
try{
 $db=db();$q=$db->prepare("SELECT * FROM users WHERE id=? AND active=1 AND role='super_admin'");$q->execute([(int)($options['actor']??0)]);$actor=$q->fetch(PDO::FETCH_ASSOC);if(!$actor)throw new InvalidArgumentException('Supply an active Super Admin --actor ID.');
 $from='2026-09-01';$to='2026-09-30';if(date('Y-m-d')<=$to)throw new InvalidArgumentException('September must be complete.');
 $locked=$db->query("SELECT id FROM hr_payroll_runs WHERE month='2026-09' AND status NOT IN ('Draft','Calculated') LIMIT 1")->fetchColumn();if($locked)throw new InvalidArgumentException('September payroll is already reviewed or locked. Attendance test aborted.');
 $employees=$db->query("SELECT u.id,u.name,e.joining_date FROM users u JOIN employees e ON e.user_id=u.id WHERE u.active=1 AND e.deleted_at IS NULL AND e.employment_status='Active' ORDER BY u.id")->fetchAll(PDO::FETCH_ASSOC);
 $ctx=chr_report_context($db,$from,$to);$plan=[];$blocked=[];
 foreach($employees as $row){$employee=foundation_employee($db,(int)$row['id']);foreach(chr_dates($from,$to) as $day){
  if(!empty($row['joining_date'])&&$day<$row['joining_date'])continue;
  $d=chr_day($ctx,$employee,$day);if(in_array($d['status'],['Present','Late','Holiday','Week Off','Week off'],true)||!empty($d['holidays'])||!empty($d['week_offs']))continue;
  if(att_sheet_leaves($db,(int)$row['id'],$day)){$blocked[]=$row['name'].' '.$day.': leave exists';continue;}
  if($d['status']==='Unscheduled'){$blocked[]=$row['name'].' '.$day.': shift missing';continue;}
  $plan[]=['employee_id'=>(int)$row['id'],'attendance_date'=>$day,'sheet_status'=>'Present','notes'=>'September 2026 full-attendance payroll test requested by Super Admin','fingerprint'=>att_sheet_fingerprint($db,(int)$row['id'],$day)];
 }}
 echo count($plan)." attendance corrections planned. Device punches remain unchanged.\n";
 foreach($blocked as $message)echo 'BLOCKED: '.$message."\n";
 if($blocked)throw new InvalidArgumentException('Resolve the listed conflicts before applying. No attendance changed.');
 if(!isset($options['apply'])){foreach($plan as $p)echo $p['employee_id'].','.$p['attendance_date'].",Present\n";echo "Dry run only. Re-run with --apply to save the listed corrections.\n";exit;}
 $saved=0;foreach($plan as $p){att_sheet_save($db,$actor,$p);$saved++;}echo $saved." corrections saved. Review September attendance and recalculate its draft payroll.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().(isset($saved)?' Saved before stop: '.$saved.'. Review these before retrying.':'')."\n");exit(1);}
