<?php
/** Readable summaries of saved exceptions. No payroll values are changed. */
function pay_issue_groups(string $exceptions):array {
 $groups=[];
 foreach(preg_split('/\R/u',trim($exceptions))?:[] as $line){
  $line=trim($line);if($line==='')continue;$date=null;$reason=$line;
  if(preg_match('/^(\d{4}-\d{2}-\d{2}):\s*(.+)$/u',$line,$m)){$date=$m[1];$reason=$m[2];}
  if(!isset($groups[$reason]))$groups[$reason]=['reason'=>$reason,'dates'=>[]];
  if($date!==null)$groups[$reason]['dates'][$date]=$date;
 }
 foreach($groups as &$group){sort($group['dates']);$n=count($group['dates']);$group['period']=$n===0?'':($n===1?date('d M Y',strtotime($group['dates'][0])):$n.' days · '.date('d M',strtotime($group['dates'][0])).' – '.date('d M Y',strtotime($group['dates'][$n-1])));
  $reason=$group['reason'];
  $group['title']=$reason;$group['help']='Review the saved calculation details and correct the source information.';
  if($reason==='Missing Salary Configuration'){$group['title']='Salary does not cover these dates';$group['help']='Check the salary amount, effective dates and salary structure in the employee profile. After saving, click Update draft payslips.';}
  elseif($reason==='Pending'){$group['title']='Attendance is not complete yet';$group['help']='Today or future dates may still be open. Complete attendance after the shift/month closes, then update the draft.';}
  elseif(str_starts_with($reason,'Check-out missing')||$reason==='Open'){$group['title']='Check-out needs review';$group['help']='Check the attendance sheet and correct any missing check-out. Then update the draft.';}
  elseif(str_starts_with($reason,'Unscheduled')){$group['title']='No shift is assigned';$group['help']='Check the employee’s effective shift for these dates, then update the draft.';}
  elseif(str_starts_with($reason,'Leave Pending')||str_starts_with($reason,'Half Day Leave Pending')){$group['title']='A leave decision is pending';$group['help']='Review the leave request before updating this draft.';}
 }
 unset($group);return array_values($groups);
}
