<?php
if(!can($pdo,$user,'attendance.manage'))return;
$edit=null;
if(isset($_GET['edit'])){
 $q=$pdo->prepare('SELECT * FROM hr_attendance WHERE id=?');$q->execute([(int)$_GET['edit']]);$edit=$q->fetch(PDO::FETCH_ASSOC)?:null;
}
$isSheet=!empty($attendanceSheet);
$entryError=$error&&($_POST['action']??'')==='core_attendance';
$entryDefaultDate=$isSheet?($_GET['entry_date']??((substr($from??'',0,7)===date('Y-m'))?date('Y-m-d'):($from??date('Y-m-d')))):date('Y-m-d');
$v=$entryError?$_POST:($edit?:['attendance_date'=>$entryDefaultDate,'employee_id'=>$isSheet?($_GET['entry_employee']??''):'']);
$autoOpen=(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&($edit||isset($_GET['entry_date'])))||$entryError;
$clear=['page'=>$page];if($isSheet)$clear['month']=$month;
if($isSheet):?>
<dialog id="sheet-entry" class="attendance-dialog" aria-label="Add or correct attendance" <?=$autoOpen?'data-auto-open':''?>>
<h2><?=$edit?'Correct attendance':'Add attendance'?></h2>
<?php if($entryError):?><p class="alert error" role="alert"><?=h($error)?></p><?php endif;endif;?>
<details class="card core-editor" <?=($isSheet||$edit||$entryError)?'open':''?>>
<summary><?=$edit?'Correct attendance / add check-out':'Add Attendance'?></summary>
<form method="post" class="form-grid core-form">
<?php
ftoken();chr_hidden('action','core_attendance');chr_hidden('id',$edit['id']??0);chr_hidden('version',$edit['version']??0);
chr_select('employee_id','Employee',$v['employee_id']??'', [''=>'Select employee']+$employeeOptions);
field('attendance_date','Attendance date',$v,'date',true);
field('check_in','Check-in',$v,'datetime-local',true);
field('check_out','Check-out (optional)',$v,'datetime-local');
choose('source','Source',$v,['Admin','Manual','Web','eSSL','API','Admin Manual','CSV Import','Biometric','Employee Web']);
field('notes','Correction reason / notes',$v,'text',(bool)$edit,3000);
?>
<p class="full">Times use <?=h($company['timezone']??date_default_timezone_get())?>. For overnight work, enter the next date for check-out. Source labels record provenance; they do not connect a device.</p>
<div class="full form-actions"><button class="button primary">Save attendance</button><a class="button" href="?<?=h(http_build_query($clear))?>">Clear</a><?php if($isSheet):?><button class="button" type="button" data-close-dialog>Cancel</button><?php endif;?></div>
</form></details>
<?php if($isSheet):?></dialog><?php endif;?>
