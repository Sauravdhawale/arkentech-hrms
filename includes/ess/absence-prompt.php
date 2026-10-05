<?php
if(!chr_leave_management_ready($pdo))return;
$absencePrompts=chr_leave_absence_prompt_refresh($pdo,$owner);
$openPrompt=null;foreach($absencePrompts as $p)if($p['status']==='Open'){$openPrompt=$p;break;}
if(!$openPrompt)return;
?>
<section class="ess-absence-popup" role="dialog" aria-labelledby="absence-reminder-title" aria-describedby="absence-reminder-text">
 <form method="post" class="ess-absence-dismiss" aria-label="Dismiss reminder"><?php token();?><input type="hidden" name="action" value="ess_absence_dismiss"><input type="hidden" name="prompt_id" value="<?=(int)$openPrompt['id']?>"><button type="submit" aria-label="Dismiss">×</button></form>
 <span class="ess-absence-icon" aria-hidden="true">!</span>
 <div>
  <p class="ess-kicker">ATTENDANCE ATTENTION</p>
  <h2 id="absence-reminder-title">Leave request may be required</h2>
  <p id="absence-reminder-text">You were marked absent on <strong><?=h(date('d M Y',strtotime($openPrompt['absence_date'])))?></strong> and no attendance or leave request was found for that working day.</p>
  <p>Please submit a leave request if the absence should be covered by leave.</p>
  <div class="ess-actions"><a class="primary" href="?page=leaves&amp;absence_date=<?=h($openPrompt['absence_date'])?>">Apply Leave</a><a href="?page=attendance&amp;month=<?=h(substr($openPrompt['absence_date'],0,7))?>">View Attendance</a></div>
 </div>
</section>
