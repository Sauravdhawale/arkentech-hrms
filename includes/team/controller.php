<?php
require_once __DIR__.'/service.php';
header('Cache-Control: no-store, private');
if(!team_is_leader($pdo,$user)||!$coreReady||$passwordSetup){http_response_code(403);exit('Team access is not available.');}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');exit('Team pages are read-only.');}
$teamMemberId=0;$teamRecords=[];$teamSelected=null;$teamLive=[];
try{
 $teamSearch=ftext($_GET,'search',100);
 $teamMonth=(string)($_GET['month']??date('Y-m'));team_month_range($teamMonth);
 $rawId=$_GET['employee_id']??'';
 if($rawId!==''){
  $teamMemberId=filter_var($rawId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if($teamMemberId===false)throw new InvalidArgumentException('Choose an employee in your team.');
  $teamSelected=team_member($pdo,$user,$teamMemberId);
 }
 $teamMembers=team_members($pdo,$user,$teamSearch);
 if($page==='team_attendance'){
  if($teamSelected)$teamRecords=team_attendance($pdo,$user,$teamMemberId,$teamMonth);
  else $teamLive=team_live($pdo,$user,$teamMembers);
 }
 if(isset($_GET['export'])){
  if($page!=='team_attendance'||!$teamSelected)throw new InvalidArgumentException('Choose a team member before exporting their attendance.');
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="team-attendance-'.$teamMemberId.'-'.$teamMonth.'.csv"');
  $out=fopen('php://output','w');
  fputcsv($out,['Employee','Employee ID','Date','Shift','Status','Check in','Check out','Worked','Late','Overtime']);
  foreach($teamRecords as $row)fputcsv($out,array_map('team_csv_cell',array_merge([$teamSelected['name'],$teamSelected['employee_code']],team_attendance_cells($row))));
  fclose($out);exit;
 }
}catch(InvalidArgumentException $e){http_response_code(403);exit(h($e->getMessage()));}
