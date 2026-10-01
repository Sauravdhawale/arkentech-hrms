<?php
require __DIR__.'/../includes/ess/punch-status.php';
function check_status(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
function event_at(string $at,string $direction='unknown'):array{return ['punched_at'=>$at,'direction'=>$direction];}
$rows=[];
foreach(['2026-10-01 18:00:00','2026-10-01 20:00:00','2026-10-02 00:01:00','2026-10-02 03:00:00'] as $i=>$at){$rows[]=event_at($at);$s=ess_punch_sequence($rows);check_status($s['state']===($i%2===0?'in':'out'),'Alternation across midnight');check_status($s['inferred'],'Unknown codes must be marked inferred');}
$s=ess_punch_sequence([event_at('2026-10-01 18:00:00'),event_at('2026-10-01 18:00:04'),event_at('2026-10-01 18:00:08')]);check_status($s['count']===1&&$s['state']==='in','Repeated scans must not toggle');
$s=ess_punch_sequence([event_at('2026-10-01 20:00:00'),event_at('2026-10-01 18:00:00')]);check_status($s['state']==='out'&&$s['last']==='2026-10-01 20:00:00','Late batches must be sorted');
$s=ess_punch_sequence([event_at('2026-10-01 18:00:00','out')]);check_status($s['state']==='out'&&!$s['inferred'],'Known direction must not be inverted');
check_status(ess_punch_sequence([])['state']==='none','No punches must not show checked in');
$s=ess_punch_sequence([event_at('2026-10-01 18:00:00','in'),event_at('2026-10-01 20:00:00','in')]);check_status($s['state']==='in','Explicit directions take precedence');
echo "Employee punch status checks passed\n";
