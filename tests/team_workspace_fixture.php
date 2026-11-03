<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/includes/team/service.php';
function h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$teamAccess=true;$teamSearch='';$teamMonth='2031-02';$teamMemberId=7;
$teamMembers=[['user_id'=>7,'name'=>'Example Team Member','employee_code'=>'AT00341','employment_status'=>'Active','department'=>'Web Development','designation'=>'Research Analyst · Night Shift','joining_date'=>'2026-01-01']];
$page=getenv('TEAM_UI_PAGE')?:'team_people';$teamSelected=null;$teamRecords=[];
$teamLive=[$teamMembers[0]+['state'=>'in','label'=>'Checked In','shift'=>'Night shift · 02 Nov, 07:00 PM – 03 Nov, 04:00 AM','last_punch'=>'2026-11-03 01:35:09','worked'=>17940,'break'=>1800,'overtime'=>0]];
if($page==='month'){$page='team_attendance';$teamSelected=$teamMembers[0];$teamRecords=[['date'=>'2031-02-03','shift'=>'Night shift','status'=>'Present','check_in'=>'2031-02-03 19:00:00','check_out'=>'2031-02-04 04:00:00','working_minutes'=>465,'late_minutes'=>0,'overtime_minutes'=>0]];}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="shrms-ui employee-ui"><main style="padding:20px;max-width:1200px;margin:auto;min-width:0"><?php require dirname(__DIR__).'/includes/team/view.php';?></main></body></html>
