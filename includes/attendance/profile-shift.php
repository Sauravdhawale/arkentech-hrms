<?php
try{$effective=chr_assignment($pdo,$id,date('Y-m-d'));}catch(InvalidArgumentException $e){$effective=null;echo '<div class="alert error">'.h($e->getMessage()).'</div>';}
$profileRoster=att_employee_roster($pdo,$id);$scheduleToken=att_schedule_token($profileRoster);
$shiftOptions=[];foreach(chr_rows($pdo,'shifts') as $shift)if($shift['status']==='Active')$shiftOptions[]=$shift;
?>
<section class="card employee-schedule">
 <div class="schedule-heading"><div><span class="schedule-eyebrow">WORK SCHEDULE</span><h2>Attendance & Shift</h2><p class="hint">A regular shift, with simple date-based exceptions.</p></div><span class="schedule-badge">Today · <?=h(date('d M Y'))?></span></div>
 <div class="schedule-current"><small>Current effective shift</small><strong><?=h($effective?att_shift_label($effective):'No shift assigned')?></strong><span>Source: <?=h($effective['assignment_source']??'None')?></span></div>
 <?php if(can($pdo,$user,'shifts.manage')):?>
 <div class="schedule-actions">
  <div><h3>Default shift</h3><p>The employee’s usual schedule. Change it from a selected date onward.</p><button class="button primary" type="button" data-open-dialog="employee-default-shift">Change default shift</button></div>
  <div><h3>Temporary shift</h3><p>Use another shift for one day or a date range. The previous schedule resumes afterward.</p><button class="button" type="button" data-open-dialog="employee-temporary-shift">Add temporary shift</button></div>
 </div>
 <?php foreach(['default'=>'Change default shift','temporary'=>'Add temporary shift'] as $mode=>$title):
 $reopen=$error&&($_POST['action']??'')==='att_employee_schedule'&&($_POST['schedule_mode']??'')===$mode;
 $shiftForm=$reopen?$_POST:['from'=>date('Y-m-d'),'to'=>date('Y-m-d')];?>
 <dialog class="attendance-dialog" id="employee-<?=h($mode)?>-shift" <?=$reopen?'data-auto-open':''?>>
 <h2><?=h($title)?></h2><p class="hint"><?=$mode==='temporary'?'For one day, select the same start and end date. Dates refer to the day the shift starts, including overnight shifts.':'This shift continues until the next change. Earlier dates retain the previous shift.'?></p>
 <form method="post" class="form-grid core-form">
 <?php ftoken();chr_hidden('action','att_employee_schedule');chr_hidden('employee_id',$id);chr_hidden('schedule_mode',$mode);chr_hidden('schedule_token',$reopen?($_POST['schedule_token']??''):$scheduleToken);?>
 <label class="full">Shift and working hours<select name="shift_id" required><option value="">Select shift</option><?php foreach($shiftOptions as $s):?><option value="<?=(int)$s['id']?>" <?=($shiftForm['shift_id']??'')==$s['id']?'selected':''?>><?=h(att_shift_label($s))?></option><?php endforeach;?></select></label>
 <?php field('from',$mode==='temporary'?'Start date':'Effective from',$shiftForm,'date',true);if($mode==='temporary')field('to','End date (inclusive)',$shiftForm,'date',true);?>
 <label class="full">Reason<textarea name="notes" required maxlength="2000" rows="3" placeholder="Why is this shift changing?"><?=h($shiftForm['notes']??'')?></textarea></label>
 <p class="hint full"><?=$mode==='temporary'?'Only the selected dates are replaced. Existing employee shifts before and after this period are retained.':'The current employee assignment ends the day before this date.'?> Dates with recorded attendance cannot be changed here.</p>
 <div class="form-actions full"><button class="button primary">Save <?=$mode==='temporary'?'temporary':'default'?> shift</button><button type="button" class="button" data-close-dialog>Cancel</button></div>
 </form></dialog>
 <?php endforeach;endif;?>
<?php $history=[];$ctx=att_resolution_context($pdo);$profileEmployee=chr_employee($pdo,$id);$departments=[(int)($profileEmployee['department_id']??0)];foreach($ctx['department_history'][$id]??[] as $change){$departments[]=(int)$change['values']['before'];$departments[]=(int)$change['values']['after'];}foreach(chr_rows($pdo,'roster') as $r)if((int)$r['employee_id']===$id){$r['assignment_type']='Employee';$history[]=$r;}foreach($ctx['defaults'] as $r)if(($r['values']['scope']??'')==='Company'||in_array((int)($r['values']['department_id']??0),$departments,true)){$r['assignment_type']=$r['values']['scope'];$history[]=$r;}usort($history,fn($a,$b)=>strcmp($b['values']['from'],$a['values']['from']));?><h3>Schedule history</h3><p class="hint">Employee dates take priority over department and company defaults. Superseded records are retained for reference.</p><div class="table-scroll"><table class="directory"><thead><tr><th>Shift</th><th>Type</th><th>From</th><th>To</th><th>Assigned by</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($history as $r):$d=$r['values'];$s=chr_record($pdo,'shifts',(int)$d['shift_id']);$q=$pdo->prepare('SELECT name FROM users WHERE id=?');$q->execute([$r['created_by']]);?><tr><td><?=h($s?att_shift_label(array_replace($s,['values'=>$d['shift_snapshot']??$s['values']])):'Unavailable')?></td><td><?=h($d['schedule_kind']??$r['assignment_type'])?></td><td><?=h($d['from'])?></td><td><?=h(($d['to']??'')?:'Ongoing')?></td><td><?=h($q->fetchColumn())?><small><?=h($r['created_at'])?></small></td><td><?=h($d['reason']??$d['notes']??'')?></td><td><?=h($r['status'])?></td><td><?php if($r['assignment_type']==='Employee'&&can($pdo,$user,'shifts.manage')):?><a class="button small" href="?page=roster&amp;edit=<?=(int)$r['id']?>">Edit assignment</a><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>


