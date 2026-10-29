<?php
require __DIR__.'/../includes/ess/punch-status.php';
function check_status(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
function event_at(string $at,string $direction='unknown'):array{return ['punched_at'=>$at,'direction'=>$direction];}
$rows=[];
foreach(['2026-10-01 18:00:00','2026-10-01 20:00:00','2026-10-02 00:01:00','2026-10-02 03:00:00'] as $i=>$at){$rows[]=event_at($at);$s=ess_punch_sequence($rows);check_status($s['state']===($i%2===0?'in':'out'),'Alternation across midnight');check_status($s['inferred'],'Unknown codes must be marked inferred');}
$s=ess_punch_sequence([event_at('2026-10-01 18:00:00'),event_at('2026-10-01 18:00:04'),event_at('2026-10-01 18:00:08')]);check_status($s['count']===3&&$s['state']==='in','Every distinct scan must toggle, even seconds apart');
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


// Shift timers use one overnight window, not the current calendar date.
date_default_timezone_set('Asia/Kolkata');
$start=strtotime('2026-10-01 18:00:00');$end=strtotime('2026-10-02 03:00:00');
$shift=['required_hours'=>9,'break_minutes'=>60,'overtime_rule'=>'After shift end'];
$timeline=ess_punch_sequence([event_at('2026-10-01 18:00:00'),event_at('2026-10-01 22:00:00'),event_at('2026-10-01 23:30:00')])['events'];
$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-02 04:00:00'),7200);
check_status($t['worked']===30600&&$t['break']===5400&&$t['excess_break']===1800,'Worked intervals exclude breaks without subtracting scheduled break again');
check_status($t['overtime']===3600&&$t['target']===32400,'After-shift overtime counts checked-in time after overnight end');
$shift['overtime_rule']='After required hours';$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-02 04:00:00'),7200);
check_status($t['overtime']===0,'Required-hours rule must not count overtime before target');
$shift['overtime_rule']='After both';$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-02 05:00:00'),7200);
check_status($t['overtime']===1800,'Both overtime thresholds must be respected');
$timeline=ess_punch_sequence([event_at('2026-10-01 18:00:00'),event_at('2026-10-02 02:30:00')])['events'];
$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-02 04:00:00'),7200);
check_status($t['worked']===30600&&$t['break']===1800,'Checked-out work pauses and break stops at scheduled end');
$t=ess_shift_timer([],$start,$end,$shift,strtotime('2026-10-01 19:00:00'),7200);
check_status($t['worked']===0&&$t['break']===0&&$t['first_in']===null,'No punches cannot start work or break');
$timeline=ess_punch_sequence([event_at('2026-10-01 18:00:00','out'),event_at('2026-10-01 19:00:00','in')])['events'];
$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-01 20:00:00'),7200);
check_status($t['worked']===3600&&$t['break']===0,'Initial OUT is not a break before first IN');
$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-02 12:00:00'),7200);
check_status($t['expired']&&$t['worked']===36000,'Missing checkout must not run forever after shift window');
$manual=['source'=>'AdminManual','check_in'=>'2026-10-01 18:00:00','check_out'=>'2026-10-01 22:00:00'];
$timeline=ess_presence_sequence($manual,[event_at('2026-10-01 23:00:00')+['status'=>'Manual protected']])['events'];
$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-02 00:00:00'),7200);
check_status($t['worked']===18000&&$t['break']===3600,'Manual baseline and later biometric intervals agree');
echo "Live shift timer checks passed\n";


require __DIR__.'/../includes/attendance/punch-types.php';
$rows=[
 ['id'=>31,'punched_at'=>'2026-10-01 18:00:00','direction'=>'unknown','status'=>'Processed'],
 ['id'=>32,'punched_at'=>'2026-10-01 18:00:04','direction'=>'unknown','status'=>'Processed'],
 ['id'=>33,'punched_at'=>'2026-10-02 00:01:00','direction'=>'unknown','status'=>'Processed'],
 ['id'=>34,'punched_at'=>'2026-10-02 00:30:00','direction'=>'in','status'=>'Processed'],
];
$types=att_punch_type_events(null,array_reverse($rows));
check_status($types[31]['direction']==='in'&&$types[33]['direction']==='in','Log labels alternate across midnight in chronological order');
check_status($types[32]['direction']==='out','Distinct scan four seconds later must be labelled OUT');
check_status($types[34]['direction']==='in'&&!$types[34]['inferred'],'Recorded direction is retained');
check_status($types[34]['direction']===ess_presence_sequence(null,$rows)['state'],'Last log type matches employee presence');
$types=att_punch_type_events($manual,[['id'=>35,'punched_at'=>'2026-10-01 23:00:00','direction'=>'unknown','status'=>'Manual protected']]);
check_status($types[35]['direction']==='in'&&$types[35]['inferred'],'Log uses the same manual OUT baseline as dashboard');
$rows[2]['status']='Failed';check_status(att_punch_type_events(null,$rows)===[],'Unresolved sequence must not invent inferred directions');
echo "Punch log display checks passed\n";


// Late arrival is deducted from worked post-shift time, never from clock span on a break.
$shift=['required_hours'=>8,'break_minutes'=>60,'overtime_rule'=>'After required hours'];
$start=strtotime('2026-10-05 09:00:00');$end=strtotime('2026-10-05 18:00:00');
$timeline=ess_punch_sequence([event_at('2026-10-05 09:30:00','in'),event_at('2026-10-05 18:15:00','out'),event_at('2026-10-05 18:45:00','in')])['events'];
$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-05 19:30:00'),7200);
check_status($t['after_end']===3600&&$t['late']===1800&&$t['net_overtime']===1800,'Net overtime excludes post-shift break and late arrival');
$t=ess_shift_timer($timeline,$start,$end,$shift,strtotime('2026-10-05 18:30:00'),7200);
check_status($t['net_overtime']===0&&$t['state']==='out','Checkout pauses overtime; late deduction cannot make it negative');
echo "Net overtime interval checks passed\n";


// Admin overrides carry explicit direction; old punches and breaks remain in the timeline.
$manual=['source'=>'Admin Manual','check_in'=>'2026-10-01 18:00:00','check_out'=>null];
$raw=[event_at('2026-10-01 18:00:00')+['status'=>'Processed'],event_at('2026-10-01 20:00:00')+['status'=>'Processed']];
$adminEvents=[['id'=>1,'punched_at'=>'2026-10-01 21:00:00','action'=>'Check In']];
$s=ess_presence_sequence($manual,$raw,$adminEvents);
check_status($s['state']==='in'&&$s['last']==='2026-10-01 21:00:00','Admin re-entry must not be replaced by older biometric OUT');
$t=ess_shift_timer($s['events'],strtotime('2026-10-01 18:00:00'),strtotime('2026-10-02 03:00:00'),['required_hours'=>8,'break_minutes'=>60],strtotime('2026-10-01 22:00:00'));
check_status($t['worked']===10800&&$t['break']===3600,'Admin re-entry must preserve prior work and break intervals');
$raw[]=event_at('2026-10-01 22:00:00')+['status'=>'Manual protected'];
check_status(ess_presence_sequence($manual,$raw,$adminEvents)['state']==='out','Later biometric event must follow explicit admin IN');
$adminEvents[]=['id'=>2,'punched_at'=>'2026-10-01 22:00:01','action'=>'Check In'];
check_status(ess_presence_sequence($manual,$raw,$adminEvents)['state']==='in','Explicit admin correction is not suppressed as a duplicate scan');
echo "Admin presence timeline checks passed\n";

// Nine-second and one-minute returns must never be swallowed by a time threshold.
foreach([9,60] as $gap){
 $first=['id'=>501,'punched_at'=>'2026-10-30 01:58:08','direction'=>'unknown','status'=>'Processed'];
 $second=['id'=>502,'punched_at'=>date('Y-m-d H:i:s',strtotime($first['punched_at'])+$gap),'direction'=>'unknown','status'=>'Processed'];
 $s=ess_presence_sequence(null,[$second,$first,$first]);
 check_status($s['count']===2&&$s['state']==='out'&&$s['last']===$second['punched_at'],'Distinct quick punch counts once; replay of same ID does not toggle');
 $types=att_punch_type_events(null,[$second,$first]);
 check_status($types[501]['direction']==='in'&&$types[502]['direction']==='out','Log and live status agree for quick punches');
}
$s=ess_punch_sequence([event_at('2026-10-01 18:00:00'),event_at('2026-10-01 18:00:00'),event_at('2026-10-01 18:00:01')]);
check_status($s['count']===2&&$s['state']==='out','Identical event copy is ignored; next second is a new event');
$raw=[['id'=>601,'punched_at'=>'2026-10-01 18:00:00','direction'=>'unknown','status'=>'Processed'],['id'=>602,'punched_at'=>'2026-10-01 20:00:00','direction'=>'unknown','status'=>'Processed'],['id'=>603,'punched_at'=>'2026-10-01 21:00:09','direction'=>'unknown','status'=>'Manual protected']];
$adminEvents=[['id'=>1,'punched_at'=>'2026-10-01 21:00:00','action'=>'Check In']];
$types=att_punch_type_events($manual,$raw,$adminEvents);
$s=ess_presence_sequence($manual,$raw,$adminEvents);
check_status($types[603]['direction']==='out'&&$types[603]['direction']===$s['state'],'Log and live status share admin overrides and later quick device events');
check_status($s['count']===4,'Synthetic attendance baseline must not double-count the first raw punch');
echo "Every distinct punch and manual/log consistency checks passed\n";
