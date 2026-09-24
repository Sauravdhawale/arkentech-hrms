<?php
$attendanceLinks=['attendance_dashboard'=>'Dashboard','daily_work_status'=>'Attendance Sheet','punch_log'=>'Check-in / Check-out Log','attendance_exceptions'=>'Late Arrival & Early Departure','monthly'=>'Monthly Summary'];
if(att_biometric_enabled($pdo))$attendanceLinks['devices']='Biometric Devices';
$visible=array_filter($attendanceLinks,fn($label,$key)=>can($pdo,$user,$pages[$key][1]),ARRAY_FILTER_USE_BOTH);
if($visible):?><details class="attendance-navigation" <?=in_array($page,array_merge(array_keys($attendanceLinks),['attendance','attendance_history','attendance_requests','mapping','sync','raw_logs']),true)?'open':''?>><summary>◷ <span>Attendance</span></summary><div><?php foreach($visible as $key=>$label)fnav($key,$label);?></div></details><?php endif;
foreach(['leaves','leave_history','leave_policy','balances'] as $key)if(can($pdo,$user,$pages[$key][1])){echo '<a href="?page='.h($key).'" '.(in_array($page,['leaves','leave_history','leave_policy','balances'],true)?'class="selected"':'').'><span>▦</span>Leave</a>';break;}

