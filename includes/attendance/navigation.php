<?php
$attendanceLinks=['attendance_dashboard'=>'Dashboard','attendance'=>'Daily Attendance','manual_attendance'=>'Manual Attendance','attendance_requests'=>'Attendance Requests','daily_work_status'=>'Attendance Sheet','attendance_exceptions'=>'Late Arrival & Early Departure','monthly'=>'Monthly Summary'];
$biometricLinks=att_biometric_enabled($pdo)?['devices'=>'Devices','mapping'=>'Employee Mapping','punch_log'=>'Check-In / Check-Out Log','sync'=>'Sync Logs']:[];
$allowed=fn($label,$key)=>isset($pages[$key])&&can($pdo,$user,$pages[$key][1]);
$visible=array_filter($attendanceLinks,$allowed,ARRAY_FILTER_USE_BOTH);
$biometricVisible=array_filter($biometricLinks,$allowed,ARRAY_FILTER_USE_BOTH);
$biometricOpen=in_array($page,['devices','mapping','punch_log','sync','raw_logs'],true);
if($visible||$biometricVisible):?>
<details class="attendance-navigation" <?=isset($attendanceLinks[$page])||$page==='attendance_history'||$biometricOpen?'open':''?>>
<summary>◷ <span>Attendance</span></summary><div>
<?php foreach($visible as $key=>$label)fnav($key,$label);if($biometricVisible):?>
<a href="?page=<?=h(array_key_first($biometricVisible))?>" <?=$biometricOpen?'class="selected" aria-current="page"':''?>>Biometric Device</a><?php endif;?></div></details><?php endif;
foreach(['leaves','leave_history','leave_policy','balances'] as $key)if(can($pdo,$user,$pages[$key][1])){echo '<a href="?page='.h($key).'" '.(in_array($page,['leaves','leave_history','leave_policy','balances'],true)?'class="selected"':'').'><span>▦</span>Leave</a>';break;}
