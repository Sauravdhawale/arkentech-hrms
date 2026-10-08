<?php
/** Employee-facing time breakdown. Raw punches and payroll snapshots are unchanged. */
function ess_attendance_metrics(array $row):array {
 $result=['arrival_late_minutes'=>0,'arrival_early_minutes'=>0,'after_shift_minutes'=>null,'net_overtime_minutes'=>null];
 if(($row['status']??'')==='Review needed')return $result;
 $in=empty($row['check_in'])?false:strtotime($row['check_in']);
 $start=empty($row['scheduled_in'])?false:strtotime($row['scheduled_in']);
 $end=empty($row['scheduled_out'])?false:strtotime($row['scheduled_out']);
 $out=empty($row['check_out'])?false:strtotime($row['check_out']);
 if($in!==false&&$start!==false){$result['arrival_late_minutes']=max(0,(int)floor(($in-$start)/60));$result['arrival_early_minutes']=max(0,(int)floor(($start-$in)/60));}
 if($out!==false&&$in!==false&&$end!==false&&$start!==false&&$out>$in&&$out>$start&&$in<$end){
  $result['after_shift_minutes']=max(0,(int)floor(($out-$end)/60));
  $result['net_overtime_minutes']=max(0,$result['after_shift_minutes']-$result['arrival_late_minutes']);
 }
 return $result;
}
