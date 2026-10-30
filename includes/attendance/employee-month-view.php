<?php
/** One employee, one full calendar month. Uses the existing report and permissions. */
$personId=(int)($_GET['employee_id']??0);
try{
 [$from,$to]=chr_report_range('attendance',$_GET);
 $records=chr_report($pdo,$from,$to,['employee_id'=>$personId]);
 if(!$records)throw new InvalidArgumentException('Employee record not found.');
}catch(InvalidArgumentException $e){echo '<p class="alert error">'.h($e->getMessage()).'</p>';return;}
$month=substr($from,0,7);$person=$records[0];preg_match('/^./us',$person['name'],$initial);$summary=chr_monthly($records)[0];
$monthDate=new DateTimeImmutable($from);$monthLabel=$monthDate->format('F Y');
$recordLink=fn(string $m)=>'?'.http_build_query(['page'=>'attendance','employee_id'=>$personId,'month'=>$m]);
$exportLink=$recordLink($month).'&export=1';$canCorrect=can($pdo,$user,'attendance.manage')&&isset($employeeOptions[$personId]);
$reviewCount=count(array_filter($records,fn($r)=>in_array($r['status'],['Review needed','Check-out missing'],true)));
?>
<link rel="stylesheet" href="assets/attendance-month.css?v=<?=filemtime(dirname(__DIR__,2).'/assets/attendance-month.css')?>">
<section class="att-month" aria-label="Employee monthly attendance">
 <div class="att-month-top"><a class="button" href="?page=attendance">← Back to live attendance</a><span>ATTENDANCE RECORD</span></div>
 <section class="card att-month-profile">
  <div class="att-month-person"><span class="att-month-avatar" aria-hidden="true"><?=h($initial[0]??'')?></span><div><h2><?=h($person['name'])?></h2><p><?=h($person['employee_code']?:'No employee ID')?> <span>·</span> <?=h($person['department']?:'No department')?></p></div></div>
  <div class="att-month-period"><strong><?=h($monthLabel)?></strong><span><?=count($records)?> calendar days · <?=h(date_default_timezone_get())?></span></div>
 </section>
 <div class="card att-month-controls">
  <nav aria-label="Browse attendance months"><a class="button" aria-label="Previous month" href="<?=h($recordLink($monthDate->modify('-1 month')->format('Y-m')))?>">← Previous</a><a class="button" aria-label="Next month" href="<?=h($recordLink($monthDate->modify('+1 month')->format('Y-m')))?>">Next →</a></nav>
  <form method="get"><?php chr_hidden('page','attendance');chr_hidden('employee_id',$personId);?><label for="attendance-record-month">Select month<input id="attendance-record-month" type="month" name="month" value="<?=h($month)?>" required></label><button class="button primary">View month</button></form>
  <?php if(can($pdo,$user,'attendance.reports')):?><a class="button att-month-export" href="<?=h($exportLink)?>">Download CSV ↓</a><?php endif;?>
 </div>
 <div class="att-month-stats">
  <?php foreach(['present'=>['Present','Day equivalents'],'absent'=>['Absent','Day equivalents'],'leave'=>['Approved leave','Paid and unpaid days'],'working_minutes'=>['Worked','Recorded hours']] as $key=>[$label,$hint]):?>
   <section class="card att-month-stat"><span><?=h($label)?></span><strong><?=h($key==='working_minutes'?chr_hours($summary[$key]):$summary[$key])?></strong><small><?=h($hint)?></small></section>
  <?php endforeach;?>
 </div>
 <div class="att-month-facts"><span><b><?=h($summary['late_count'])?></b> late arrivals</span><span><b><?=h(chr_hours($summary['overtime_minutes']))?></b> overtime</span><span><b><?=h($summary['week_offs'])?></b> week offs</span><span><b><?=h($summary['holidays'])?></b> holidays</span><?php if($reviewCount):?><span class="att-month-review"><b><?=$reviewCount?></b> days need review</span><?php endif;?></div>
 <section class="card att-month-records">
  <div class="att-month-table-heading"><div><h3>Daily records</h3><p>Every day in <?=h($monthLabel)?>. Night shifts stay on their start date.</p></div><span><?=count($records)?> days</span></div>
  <p class="att-month-note">Saved attendance totals are shown here. An open check-in has no final checkout or completed hours. Use Live attendance for the running timer. Present and absent values may include half days.</p>
  <div class="att-month-scroll" tabindex="0" role="region" aria-label="Monthly attendance records"><table><thead><tr><th>Date / shift</th><th>Check In</th><th>Check Out</th><th>Worked</th><th>Late</th><th>Early departure</th><th>Overtime</th><th>Status</th><th>Record</th></tr></thead><tbody>
  <?php foreach($records as $r):
   $tone=in_array($r['status'],['Present','Late'],true)?'present':(in_array($r['status'],['Absent','Review needed','Check-out missing'],true)?'review':(str_contains($r['status'],'Leave')?'leave':'neutral'));
   $open=$r['check_in']&&!$r['check_out'];$future=$r['date']>date('Y-m-d');
  ?><tr data-attendance-date="<?=h($r['date'])?>" <?=$r['date']===date('Y-m-d')?'class="att-month-today"':''?>>
   <th scope="row"><strong><?=h(date('d M · D',strtotime($r['date'])))?></strong><?php if($r['date']===date('Y-m-d')):?><span class="att-month-today-tag">Today</span><?php endif;?><small><?=h($r['shift'])?></small><?php if($r['scheduled_in']):?><small><?=h(date('h:i A',strtotime($r['scheduled_in'])))?> – <?=h(date('h:i A',strtotime($r['scheduled_out'])))?><?=substr($r['scheduled_out'],0,10)>$r['date']?' (+1 day)':''?></small><?php endif;?></th>
   <td><?=h(att_time_label($r['check_in'],false,true))?></td><td><?=$open?'<span class="att-month-open">Awaiting checkout</span>':h(att_time_label($r['check_out'],false,true))?></td>
   <td><strong><?=h($future||$open?'—':chr_hours($r['working_minutes']))?></strong></td><td><?=h($future?'—':chr_hours($r['late_minutes']))?></td><td><?=h($future||$open?'—':chr_hours($r['early_minutes']))?></td><td><?=h($future||$open?'—':chr_hours($r['overtime_minutes']))?></td>
   <td><span class="att-month-status <?=$tone?>"><?=h($r['status']==='Open'?'Check-in open':$r['status'])?></span><?php if($r['leave_codes']):?><small><?=h($r['leave_codes'])?></small><?php endif;?></td>
   <td><details><summary>Details</summary><p><?=h($r['note']?:'No additional notes.')?></p><small>Source: <?=h($r['source']?:'Schedule')?></small><?php if($r['devices']):?><small>Device: <?=h($r['devices'])?></small><?php endif;?></details><?php if($r['record_id']&&$canCorrect):?><a href="?<?=h(http_build_query(['page'=>'attendance','employee_id'=>$personId,'date'=>$r['date'],'edit'=>(int)$r['record_id']]))?>">Correct record</a><?php endif;?></td>
  </tr><?php endforeach;?>
  </tbody></table></div>
 </section>
 <p class="att-month-footnote">Future days remain pending. Days without an assigned schedule are not automatically marked absent. Corrections require the existing attendance permission and a reason.</p>
</section>
