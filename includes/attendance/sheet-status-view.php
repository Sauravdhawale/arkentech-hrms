<?php
if(!can($pdo,$user,'attendance.manage'))return;
$sheetError=$error&&($_POST['action']??'')==='att_sheet_status';
$selectedEmployee=(int)($sheetError?($_POST['employee_id']??0):($_GET['entry_employee']??0));
$selectedDay=(string)($sheetError?($_POST['attendance_date']??''):($_GET['entry_date']??''));
if(!$selectedEmployee||!isset($employeeOptions[$selectedEmployee]))return;
try{chr_date($selectedDay);}catch(InvalidArgumentException $e){return;}
$selectedRow=null;foreach($rows as $reportRow)if((int)$reportRow['employee_id']===$selectedEmployee&&$reportRow['date']===$selectedDay){$selectedRow=$reportRow;break;}
if(!$selectedRow)return;
$override=att_sheet_record($pdo,$selectedEmployee,$selectedDay);$existingLeaves=att_sheet_leaves($pdo,$selectedEmployee,$selectedDay);
$selectedStatus=$sheetError?($_POST['sheet_status']??'Automatic'):($existingLeaves?'leave:'.$existingLeaves[0]['category']:(($override['status']??'')==='Active'?$override['values']['status']:'Automatic'));
$timesQuery=['page'=>$page,'month'=>$month,'entry_employee'=>$selectedEmployee,'entry_date'=>$selectedDay];if($selectedRow['record_id'])$timesQuery['edit']=$selectedRow['record_id'];
?>
<dialog id="sheet-status" class="attendance-dialog att-status-dialog" aria-labelledby="sheet-status-title" <?=((isset($_GET['status_cell'])&&($_SERVER['REQUEST_METHOD']??'GET')==='GET')||$sheetError)?'data-auto-open':''?>>
<div class="att-status-heading"><div><p class="eyebrow">ATTENDANCE CORRECTION</p><h2 id="sheet-status-title">Change day status</h2></div><button type="button" class="button small" data-close-dialog aria-label="Close status editor">✕</button></div>
<div class="att-status-person"><strong><?=h($selectedRow['name'])?></strong><span><?=h($selectedRow['employee_code'])?> · <?=h(date('D, d M Y',strtotime($selectedDay)))?></span><small>Current: <?=h($selectedRow['leave_codes']?:($selectedRow['pending_leave_codes']?($selectedRow['pending_leave_codes'].' · Pending'):$selectedRow['status']))?></small></div>
<?php if($sheetError):?><p class="alert error" role="alert"><?=h($error)?></p><?php endif;?>
<form method="post" class="att-status-form">
<?php ftoken();chr_hidden('action','att_sheet_status');chr_hidden('employee_id',$selectedEmployee);chr_hidden('attendance_date',$selectedDay);chr_hidden('fingerprint',att_sheet_fingerprint($pdo,$selectedEmployee,$selectedDay));?>
<label>Status<select name="sheet_status" required><optgroup label="Attendance"><?php foreach(['Automatic'=>'Automatic · use punches and schedule','Present'=>'P · Present','Absent'=>'A · Absent','Half Day'=>'HD · Half day','Holiday'=>'H · Holiday','Week Off'=>'WO · Week off'] as $key=>$label):?><option value="<?=h($key)?>" <?=$selectedStatus===$key?'selected':''?>><?=h($label)?></option><?php endforeach;?></optgroup><?php if(can($pdo,$user,'leave.create')):?><optgroup label="Leave types"><?php foreach(chr_rows($pdo,'leave_policy') as $policy):if($policy['status']!=='Published')continue;$code=$policy['values']['type']??'';?><option value="<?=h('leave:'.$code)?>" <?=$selectedStatus==='leave:'.$code?'selected':''?>><?=h($code.' · '.$policy['title'])?></option><?php endforeach;?></optgroup><?php endif;?></select></label>
<label>Reason for change<textarea name="notes" rows="2" maxlength="3000" required placeholder="Add a short reason for this correction"><?=h($sheetError?($_POST['notes']??''):'')?></textarea></label>
<?php if($existingLeaves):?><label class="check"><input type="checkbox" name="replace_leave" value="1" <?=!empty($_POST['replace_leave'])?'checked':''?>> Replace the existing leave for this day and adjust its balance.</label><?php endif;?>
<p class="att-caption">Leave types use their balance and approval rules. Attendance status changes keep the original punches; working hours still require check-in and check-out times.</p>
<div class="att-status-actions"><a class="button" href="?<?=h(http_build_query($timesQuery))?>">Edit punch times</a><button class="button primary">Save status</button></div>
</form></dialog>
