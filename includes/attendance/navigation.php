<?php
$attendanceLinks=['attendance_dashboard'=>'Dashboard','attendance'=>'Manual Attendance','attendance_requests'=>'Attendance Requests','daily_work_status'=>'Daily Work Status','punch_log'=>'Check-In / Check-Out Log','attendance_exceptions'=>'Late Arrival & Early Departure','monthly'=>'Monthly Summary','time_policies'=>'Time Policies'];
if(att_biometric_enabled($pdo))$attendanceLinks+=['devices'=>'Biometric Devices','mapping'=>'Biometric Mapping','sync'=>'Biometric Sync Logs','raw_logs'=>'Raw Punch Logs'];
$visible=array_filter($attendanceLinks,fn($label,$key)=>can($pdo,$user,$pages[$key][1]),ARRAY_FILTER_USE_BOTH);
if($visible):?><details class="attendance-navigation" <?=array_key_exists($page,$attendanceLinks)?'open':''?>><summary>◷ <span>Attendance</span></summary><div><?php foreach($visible as $key=>$label)fnav($key,$label);?></div></details><?php endif;
foreach(['leaves','leave_history','leave_policy','balances'] as $key)if(can($pdo,$user,$pages[$key][1])){echo '<a href="?page='.h($key).'" '.(in_array($page,['leaves','leave_history','leave_policy','balances'],true)?'class="selected"':'').'><span>▦</span>Leave</a>';break;}
if(can($pdo,$user,'calendar.view'))fnav('company_calendar','Company Calendar');
