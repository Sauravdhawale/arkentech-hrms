<?php
// Pure calculation regression tests. No database connection or production writes.
require dirname(__DIR__).'/includes/core-hr/service.php';
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function expect(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function invalid(callable $fn,string $message):void{$bad=false;try{$fn();}catch(InvalidArgumentException $e){$bad=true;}expect($bad,$message);}
date_default_timezone_set('Asia/Kolkata');
$shift=['start'=>'22:00','end'=>'06:00','required_hours'=>7.5,'half_day_hours'=>4,'break_minutes'=>30,'grace'=>10,'early_grace'=>5,'overtime_rule'=>'After both'];
$r=chr_calculate('2026-01-05','2026-01-05 22:20:00','2026-01-06 07:00:00',$shift);expect($r===['working_minutes'=>490,'late_minutes'=>20,'early_minutes'=>0,'overtime_minutes'=>40,'status'=>'Late'],'Overnight calculations');
$r=chr_calculate('2026-01-05','2026-01-05 22:05:00','2026-01-06 05:30:00',$shift);expect($r['late_minutes']===0&&$r['early_minutes']===30&&$r['overtime_minutes']===0,'Grace and early leaving');
$r=chr_calculate('2026-01-05','2026-01-05 22:00:00',null,$shift);expect($r['status']==='Open'&&$r['working_minutes']===0,'Open check-in');
$r=chr_calculate('2026-01-05','2026-01-05 22:00:00','2026-01-06 01:00:00',$shift);expect($r['status']==='Half Day'&&$r['working_minutes']===150,'Half-day working threshold');
$r=chr_calculate('2026-01-05','2026-01-05 10:00:00','2026-01-05 18:00:00',null);expect($r['working_minutes']===480&&$r['late_minutes']===0&&$r['overtime_minutes']===0,'Unassigned shift');
foreach(['After shift end'=>60,'After required hours'=>40,'After both'=>40] as $rule=>$expected){$r=chr_calculate('2026-01-05','2026-01-05 22:20:00','2026-01-06 07:00:00',array_merge($shift,['overtime_rule'=>$rule]));expect($r['overtime_minutes']===$expected,'Overtime rule '.$rule);}
invalid(fn()=>chr_datetime('2026-02-30T09:00'),'Invalid datetime');invalid(fn()=>chr_dates('2026-02-01','2026-01-01'),'Reverse dates');invalid(fn()=>chr_dates('2025-01-01','2026-12-31'),'Unbounded dates');invalid(fn()=>chr_calculate('2026-01-05','2026-01-05 22:00:00','2026-01-05 21:00:00',$shift),'Negative hours');expect(count(chr_dates('2024-01-01','2024-12-31'))===366,'Leap year');
$employee=['user_id'=>1,'name'=>'Test','joining_date'=>'2026-01-01'];$ctx=['assignments'=>[1=>[['values'=>['from'=>'2026-01-01','to'=>null,'shift_id'=>10,'shift_snapshot'=>$shift+['week_off'=>[7]]]]]],'shifts'=>[10=>['id'=>10,'title'=>'Night','values'=>$shift]],'holidays'=>['2026-01-07'=>true],'attendance'=>[],'leave'=>[1=>['2026-01-08'=>1,'2026-01-09'=>0.5]],'leave_parts'=>[1=>['2026-01-09'=>'First Half']]];
expect(chr_day($ctx,$employee,'2026-01-07')['status']==='Holiday','Holiday report');expect(chr_day($ctx,$employee,'2026-01-11')['status']==='Week Off','Week-off report');expect(chr_day($ctx,$employee,'2026-01-08')['status']==='Leave','Approved leave report');$half=chr_day($ctx,$employee,'2026-01-09');expect($half['leave']===0.5&&$half['absent']===0.5,'Half-day leave absent remainder');expect(chr_day($ctx,$employee,'2025-12-31')['status']==='Not started','Joining date');expect(chr_day($ctx,$employee,'2090-01-02')['absent']===0,'Future absence');expect(chr_day($ctx,$employee,'2026-01-06')['status']==='Absent','Missing scheduled punch');
$rows=[chr_day($ctx,$employee,'2026-01-07'),chr_day($ctx,$employee,'2026-01-08'),$half];$m=chr_monthly($rows)[0];expect($m['leave']===1.5&&$m['absent']===0.5&&$m['holidays']===1,'Monthly fractional totals');
echo "PASS: overnight shifts, breaks, grace, early leaving, overtime rules, open punches, half days, date validation and integrated daily/monthly status calculations.\n";
