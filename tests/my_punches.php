<?php
require __DIR__.'/../includes/ess/punch-history.php';
date_default_timezone_set('Asia/Kolkata');
function punch_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$raw=[['id'=>1,'punched_at'=>'2026-01-02 18:00:01','direction'=>'unknown','status'=>'Processed'],['id'=>2,'punched_at'=>'2026-01-02 18:00:05','direction'=>'unknown','status'=>'Processed'],['id'=>3,'punched_at'=>'2026-01-03 01:00:09','direction'=>'in','status'=>'Processed']];
$r=ess_punch_history_rows(null,array_reverse($raw),[]);
punch_check($r['counts']===['total'=>3,'in'=>2,'out'=>1,'unknown'=>0],'Sorted overnight punches, including scans seconds apart');
punch_check($r['punches'][0]['time_label']==='06:00:01 PM'&&$r['punches'][2]['date_label']==='03 Jan 2026'&&str_ends_with($r['punches'][0]['at'],'+05:30'),'Exact seconds, date and timezone');
$r=ess_punch_history_rows(null,[$raw[0],$raw[0]],[]);punch_check($r['counts']['total']===1,'Retries do not double count');
$manual=[['id'=>1,'punched_at'=>'2026-01-03 02:00:01','action'=>'Check Out','source'=>'Admin Manual']];
$r=ess_punch_history_rows(null,$raw,$manual);punch_check($r['counts']['total']===4&&$r['counts']['out']===2&&$r['punches'][3]['source']==='Admin manual','Actual manual punches counted once');
$pending=$raw;$pending[0]['status']='Pending';$r=ess_punch_history_rows(null,$pending,[]);
punch_check($r['counts']['unknown']===2&&$r['counts']['in']===1,'Pending sequences do not invent directions; explicit IN retained');
$attendance=['source'=>'Admin Manual','check_in'=>'2026-01-02 17:00:00','check_out'=>null];
$r=ess_punch_history_rows($attendance,[],[]);punch_check($r['counts']['total']===0,'Synthetic attendance baseline is not a punch');
$saved=['values'=>['events'=>[['at'=>strtotime('2026-01-02 18:00:00'),'state'=>'in']],'cutoff'=>strtotime('2026-01-03 02:00:00'),'last_raw_id'=>3,'last_manual_id'=>0]];
$r=ess_punch_history_rows(null,$raw,[],$saved);punch_check($r['corrected']&&$r['counts']['total']===3&&$r['counts']['unknown']===2,'Corrections do not fabricate scans or retroactively infer old unknown directions');
echo "PASS: punch counts, seconds, overnight dates, retries, manual sources and unresolved/corrected sequences.\n";
