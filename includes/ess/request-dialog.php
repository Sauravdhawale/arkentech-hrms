<?php $isLeave=$dialogKind==='leave';$dialogId=$isLeave?'leave-dialog':'correction-dialog';$target=$isLeave?'leaves':'regularisation';$posted=$_SERVER['REQUEST_METHOD']==='POST'&&$page===$target&&($_POST['action']??'')==='request';$input=$posted?$_POST:[];$absenceDate=$isLeave?(string)($_GET['absence_date']??''):'';if($absenceDate&&preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$absenceDate)){$input['start_date']=$input['start_date']??$absenceDate;$input['end_date']=$input['end_date']??$absenceDate;$input['subject']=$input['subject']??'Leave request for absence';}?>
<dialog id="<?=$dialogId?>" class="ess-dialog" aria-labelledby="<?=$dialogId?>-title" <?=($posted&&$error)||$absenceDate?'data-open-on-load':''?>>
<div class="ess-section-heading"><h2 id="<?=$dialogId?>-title"><?=$isLeave?'Apply for leave':'Request attendance correction'?></h2><button type="button" data-dialog-close aria-label="Close dialog">×</button></div>
<?php if($posted&&$error):?><p class="error" role="alert"><?=h($error)?></p><?php endif;?>
<?php if($isLeave):try{$route=ess_route($pdo,(int)$user['id']);?><p>Send to: <?=h(implode($route['mode']==='any'?' or ':' → ',array_column($route['approvers'],'name')))?></p><?php }catch(InvalidArgumentException $e){?><p><?=h($e->getMessage())?></p><?php }endif;?>
<form method="post" action="employee.php?page=<?=$target?>" enctype="multipart/form-data" class="work-form">
<?php token();?><input type="hidden" name="action" value="request">
<label><?=$isLeave?'Leave type':'Correction type'?><select name="category"><?php foreach($isLeave?$coreLeaveTypes:['Missed punch','Incorrect punch','Shift correction'] as $c):?><option <?=($input['category']??'')===$c?'selected':''?>><?=h($c)?></option><?php endforeach;?></select></label>
<label>Subject<input name="subject" maxlength="180" value="<?=h($input['subject']??'')?>" required></label>
<div class="form-columns"><label>From<input type="date" name="start_date" value="<?=h($input['start_date']??'')?>" required></label><label>To<input type="date" name="end_date" value="<?=h($input['end_date']??'')?>" required></label></div>
<?php if($isLeave):?><label>Day period<select name="day_part"><?php foreach(['Full Day','First Half','Second Half'] as $part):?><option <?=($input['day_part']??'')===$part?'selected':''?>><?=$part?></option><?php endforeach;?></select></label><label>Attachment<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label><?php endif;?>
<label>Details<textarea name="details" maxlength="5000" required><?=h($input['details']??'')?></textarea></label>
<p><?=$isLeave?'Assigned weekly offs and published holidays are excluded. Half-day leave counts as 0.5 day.':'Submit one date per correction. HR reviews and applies an audited correction.'?></p>
<div class="ess-actions"><button class="primary">Submit request</button><button type="button" data-dialog-close>Cancel</button></div>
</form></dialog>
