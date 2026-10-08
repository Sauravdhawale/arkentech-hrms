<?php
require __DIR__.'/../includes/ess/attendance-metrics.php';
function verify_metric(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
date_default_timezone_set('Asia/Kolkata');
$base=['status'=>'Present','check_in'=>'2026-10-05 09:30:00','check_out'=>'2026-10-05 19:00:00','scheduled_in'=>'2026-10-05 09:00:00','scheduled_out'=>'2026-10-05 18:00:00'];
$r=ess_attendance_metrics($base);verify_metric($r['arrival_late_minutes']===30&&$r['arrival_early_minutes']===0&&$r['after_shift_minutes']===60&&$r['net_overtime_minutes']===30,'Late time must be deducted once');
$r=ess_attendance_metrics(array_replace($base,['check_in'=>'2026-10-05 08:30:00']));verify_metric($r['arrival_early_minutes']===30&&$r['arrival_late_minutes']===0&&$r['net_overtime_minutes']===60,'Early arrival does not add overtime');
$r=ess_attendance_metrics(array_replace($base,['check_out'=>'2026-10-05 18:15:00']));verify_metric($r['net_overtime_minutes']===0,'Net overtime cannot be negative');
$r=ess_attendance_metrics(array_replace($base,['check_out'=>null]));verify_metric($r['net_overtime_minutes']===null&&$r['arrival_late_minutes']===30,'Open historical record cannot invent overtime');
$r=ess_attendance_metrics(array_replace($base,['status'=>'Review needed']));verify_metric($r['net_overtime_minutes']===null&&$r['arrival_late_minutes']===0,'Invalid records excluded');
$night=['status'=>'Late','check_in'=>'2026-10-05 18:30:00','check_out'=>'2026-10-06 04:00:00','scheduled_in'=>'2026-10-05 18:00:00','scheduled_out'=>'2026-10-06 03:00:00'];
$r=ess_attendance_metrics($night);verify_metric($r['net_overtime_minutes']===30,'Overnight net overtime');
$r=ess_attendance_metrics(array_replace($night,['check_out'=>'2026-10-06 02:00:00']));verify_metric($r['after_shift_minutes']===0&&$r['net_overtime_minutes']===0,'Early departure is not early arrival or overtime');
verify_metric($base['check_in']==='2026-10-05 09:30:00','Input is unchanged');
echo "PASS: employee early/late arrival, after-shift overtime minus lateness, overnight windows, missing punches and no data mutation.\n";
