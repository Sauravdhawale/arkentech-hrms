<?php
require_once __DIR__.'/access.php';
require_once dirname(__DIR__).'/core-hr/service.php';
require_once dirname(__DIR__).'/ess/punch-status.php';

function team_month_range(string $month):array {
 if(!preg_match('/^(19|20|21)\d{2}-(0[1-9]|1[0-2])$/D',$month))throw new InvalidArgumentException('Choose a valid month.');
 $start=new DateTimeImmutable($month.'-01');return [$start->format('Y-m-d'),$start->format('Y-m-t')];
}

function team_attendance(PDO $db,array $actor,int $employee,string $month):array {
 team_member($db,$actor,$employee);
 [$from,$to]=team_month_range($month);
 // Never pass client filters to the global report builder or allow employee_id=0.
 $rows=chr_report($db,$from,$to,['employee_id'=>$employee]);
 $keys=array_flip(['employee_id','date','name','employee_code','department','shift','status','scheduled_in','scheduled_out','check_in','check_out','working_minutes','late_minutes','early_minutes','overtime_minutes']);
 return array_map(fn($r)=>array_intersect_key($r,$keys),$rows);
}

function team_live(PDO $db,array $actor,array $members):array {
 team_require_leader($db,$actor);$rows=[];
 foreach($members as $member){
  $member=team_member($db,$actor,(int)$member['user_id']);
  $s=ess_punch_status($db,(int)$member['user_id']);$t=$s['timing']??[];
  $rows[]=$member+['state'=>$s['state'],'label'=>$s['label'],'shift'=>$t['shift_label']??'No current shift','last_punch'=>$s['last_punch_at']??null,
   'worked'=>$t['worked']??null,'break'=>$t['break']??null,'overtime'=>$t['net_overtime']??null];
 }
 return $rows;
}

function team_duration($seconds):string {
 if($seconds===null)return '—';$minutes=(int)floor(max(0,(float)$seconds)/60);return intdiv($minutes,60).'h '.str_pad((string)($minutes%60),2,'0',STR_PAD_LEFT).'m';
}

/** Use identical columns for the visible monthly table and its export. */
function team_attendance_cells(array $r):array {
 $open=!empty($r['check_in'])&&empty($r['check_out']);
 $pending=$open||$r['date']>date('Y-m-d')||$r['status']==='Review needed';
 return [$r['date'],$r['shift'],$r['status']==='Open'?'Checked in':$r['status'],
  $r['check_in']?:'—',$open?'Awaiting checkout':($r['check_out']?:'—'),
  $pending?'—':team_duration((float)$r['working_minutes']*60),team_duration((float)$r['late_minutes']*60),
  $pending?'—':team_duration((float)$r['overtime_minutes']*60)];
}

function team_csv_cell($value):string {
 $value=(string)$value;
 return preg_match('/^[\s]*[=+@\-]/u',$value)?"'".$value:$value;
}
