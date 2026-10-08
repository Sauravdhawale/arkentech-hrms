<?php
require __DIR__.'/../includes/attendance/insights.php';
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
function h($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function chr_hours($minutes){return sprintf('%dh %02dm',intdiv((int)$minutes,60),(int)$minutes%60);}
$base=['status'=>'Present','department'=>'Operations','employee_id'=>1,'name'=>'Employee A','employee_code'=>'E001','date'=>'2026-02-01','check_in'=>'2026-02-01 09:00:00','late_minutes'=>0,'present'=>1,'absent'=>0,'leave'=>0,'working_minutes'=>480,'overtime_minutes'=>0];
$rows=[$base,array_replace($base,['date'=>'2026-02-02','late_minutes'=>15]),array_replace($base,['date'=>'2026-02-03','status'=>'Half Day','present'=>0.5,'absent'=>0.5,'working_minutes'=>240]),array_replace($base,['date'=>'2026-02-04','status'=>'Pending','check_in'=>'','present'=>0,'working_minutes'=>0]),array_replace($base,['date'=>'2026-02-05','status'=>'Leave','check_in'=>'','present'=>0,'leave'=>1,'working_minutes'=>0]),array_replace($base,['employee_id'=>2,'department'=>'Future team','status'=>'Not started','check_in'=>'','present'=>0,'working_minutes'=>0])];
$result=att_insights($rows);
verify($result['arrivals']===3&&$result['late']===1&&$result['on_time']===2,'Arrival counts');
verify($result['departments']['Operations']['rate']===83.3,'Half days count proportionately; pending and leave do not lower attendance rate');
verify($result['departments']['Operations']['late_rate']===33.3,'Late rate denominator uses recorded arrivals');
verify($result['worked']===1200,'Recorded hours preserved');verify(!isset($result['departments']['Future team']),'Not-started employees excluded');
verify($result['employees'][1]['late_minutes']===15,'Employee late totals');verify(count($result['days'])===5,'Daily series');
$empty=att_insights([]);verify($empty['arrivals']===0&&$empty['days']===[],'Empty report');
ob_start();att_insight_bars([],'rate','%',100);$html=ob_get_clean();verify(str_contains($html,'No matching data'),'Empty chart state');
ob_start();att_insight_bars([['label'=>'<script>','rate'=>50]],'rate','%',100);$html=ob_get_clean();verify(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>'),'Chart labels escaped');
echo "PASS: attendance insight totals, partial days, pending exclusions, rates and empty states.\n";
