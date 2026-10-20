<?php
require __DIR__.'/core_hr_calculations.php';
require dirname(__DIR__).'/includes/payroll/issue-summary.php';
$epoch=(new DateTimeImmutable('2026-10-20 15:51:58',new DateTimeZone('UTC')))->getTimestamp();
expect(att_server_time_label($epoch)==='20 Oct 2026 · 09:21:58 PM IST','SQL event epoch must match local bridge health time');
expect(att_server_time_label(null)==='—','Missing server time');
date_default_timezone_set('UTC');expect(att_server_time_label($epoch)==='20 Oct 2026 · 03:51:58 PM UTC','Company timezone respected');date_default_timezone_set('Asia/Kolkata');
expect(att_shift_duration(['start'=>'09:00 AM','end'=>'06:00 PM'])===540,'Day shift duration');
expect(att_shift_duration(['start'=>'06:00 PM','end'=>'03:00 AM'])===540,'Night shift duration');
expect(att_shift_duration(['start'=>'09:00 AM','end'=>'06:00 AM'])===1260,'Accidental long shift remains visible, not silently changed');
expect(att_shift_duration(['start'=>'invalid','end'=>'06:00 AM'])===null,'Invalid shift preview');
$errors="2026-10-01: Missing Salary Configuration\n2026-10-02: Missing Salary Configuration\n2026-10-02: Missing Salary Configuration\n2026-10-20: Pending\nOther reason <script>";
$groups=pay_issue_groups($errors);
expect(count($groups)===3,'Repeated exceptions grouped');expect(count($groups[0]['dates'])===2,'Dates counted once');expect($groups[0]['title']==='Salary does not cover these dates','Readable salary issue');expect(str_contains($groups[0]['help'],'Update draft payslips'),'Next step after correction');expect($groups[2]['reason']==='Other reason <script>','Unknown details preserved for escaping at output');expect(pay_issue_groups('')===[],'No false errors');
echo "PASS: local sync timestamps, day/night shift duration and readable payroll issue groups.\n";
