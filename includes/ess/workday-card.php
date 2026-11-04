<section class="panel ess-workday" data-workday>
 <div class="ess-section-heading"><div><span class="eyebrow">TODAY AT A GLANCE</span><h2>Hello, <?=h($user['name'])?></h2></div><span class="badge" data-workday-state>Loading attendance…</span></div>
 <p data-workday-shift>Finding your assigned shift…</p>
 <div class="ess-workday-main"><div><span>Total worked</span><strong data-workday-value="worked">—</strong><small data-workday-target>Based on your assigned shift</small></div><div class="ess-workday-punches"><p>First check-in <b data-workday-in>—</b></p><p>Last OUT punch <b data-workday-out>—</b></p></div></div>
 <p class="ess-workday-progress-label" data-workday-progress-label>Within-shift work progress</p>
 <progress data-workday-progress max="100" value="0" aria-label="Within-shift working time completed"></progress>
 <div class="ess-workday-metrics">
 <?php foreach(['allowed_break'=>'Allowed break','break'=>'Break taken','excess_break'=>'Excess break','late_remaining'=>'Late time to cover'] as $key=>$label):?>
 <div><small><?=h($label)?></small><strong data-workday-value="<?=h($key)?>">—</strong></div>
 <?php endforeach;?>
 </div>
 <details class="ess-time-details">
 <summary>Time details <span>Shift, overtime &amp; calculation notes</span></summary>
 <div class="ess-workday-metrics ess-workday-secondary">
 <?php foreach(['shift_worked'=>'Within shift','overtime'=>'Net overtime','before_start'=>'Before shift','after_end'=>'After shift'] as $key=>$label):?>
 <div><small><?=h($label)?></small><strong data-workday-value="<?=h($key)?>">—</strong></div>
 <?php endforeach;?>
 </div>
 <p class="ess-hint" data-workday-note>Waiting for the latest punch data.</p>
 </details>
 <section class="my-punches-summary" aria-label="My current shift punches" data-my-punches-summary>
  <div class="my-punches-summary-title"><strong>My punches</strong><button type="button" data-my-punches-open aria-haspopup="dialog">View punch times</button></div>
  <div class="my-punches-counts">
   <div><span>Total punches</span><strong data-punch-count="total">—</strong></div>
   <div><span>IN punches</span><strong data-punch-count="in">—</strong></div>
   <div><span>OUT punches</span><strong data-punch-count="out">—</strong></div>
  </div>
  <p class="my-punches-meta" data-punch-summary-note>Loading your current shift punches…</p>
 </section>
 <div class="ess-actions">
 <?php if(att_ready($pdo)&&!empty(att_config($pdo)['web_punch_enabled'])):?><form method="post"><?php token();?><input type="hidden" name="action" value="ess_punch"><input type="hidden" name="request_key" value="<?=bin2hex(random_bytes(32))?>"><button class="primary" name="punch_action" value="Check In">Check In</button><button name="punch_action" value="Check Out">Check Out</button></form><?php endif;?>
 <button type="button" data-dialog-open="correction-dialog">Request a correction</button>
 </div>
</section>






