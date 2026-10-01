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
$manual=['source'=>'AdminManual','check_in'=>'2026-10-01 18:00:00','check_out'=>'2026-10-02 00:45:00'];
$raw=event_at('2026-10-02 02:27:54')+['status'=>'Manual protected'];
$s=ess_presence_sequence($manual,[$raw]);check_status($s['state']==='in'&&$s['last']===$raw['punched_at']&&$s['inferred'],'New protected biometric punch must supersede manual OUT for presence');
$s=ess_presence_sequence($manual,[$raw,event_at('2026-10-02 02:30:00')+['status'=>'Manual protected']]);check_status($s['state']==='out','Next biometric punch must toggle back OUT');
$s=ess_presence_sequence($manual,[event_at('2026-10-01 19:00:00')+['status'=>'Pending']]);check_status($s['state']==='out'&&!$s['inferred'],'Old raw events must not override manual baseline');
$s=ess_presence_sequence($manual,[]);check_status($s['state']==='out','Manual status must work without biometric events');
$s=ess_presence_sequence(null,[$raw]);check_status($s['state']==='in','Protected punch can establish presence without attendance');
$s=ess_presence_sequence($manual,[event_at('2026-10-02 02:30:00')+['status'=>'Failed']]);check_status($s['state']==='review','Failed events must not claim a confirmed presence');
$s=ess_presence_sequence(['source'=>'AdminManual','check_in'=>null,'check_out'=>null],[]);check_status($s['state']==='none','Empty manual attendance must not imply IN');
echo "Employee punch status checks passed\n";
