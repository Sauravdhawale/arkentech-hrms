<?php
$tlEmployee=(int)($_GET['timeline_employee']??0);$tlDate=(string)($_GET['timeline_date']??date('Y-m-d'));$tlRequest=(int)($_GET['request_id']??0);$tlContext=null;$tlError='';
$tlCanEdit=can($pdo,$user,'attendance.manage');
if($tlEmployee){try{$tlContext=att_timeline_context($pdo,$tlEmployee,$tlDate);}catch(InvalidArgumentException $e){$tlError=$e->getMessage();}}
?>
<link rel="stylesheet" href="assets/css/attendance-timeline.css?v=<?=filemtime(__DIR__.'/../../assets/css/attendance-timeline.css')?>">
<section class="card atl" id="correction-timeline">
 <header class="atl-heading"><div><span class="atl-eyebrow">Attendance corrections</span><h2>Make the day accurate</h2><p>Choose an employee and shift date to adjust working time or breaks.</p></div><a class="button" href="#employee-requests">View employee requests</a></header>
 <form method="get" class="atl-filter"><?php chr_hidden('page','attendance_requests');chr_select('timeline_employee','Employee',$tlEmployee,[''=>'Choose employee']+$historyEmployeeOptions);field('timeline_date','Shift start date',['timeline_date'=>$tlDate],'date',true);?><button class="button primary">Open timeline</button></form>
 <?php if($tlError):?><p class="alert" role="alert"><?=h($tlError)?></p><?php elseif(!$tlContext):?><div class="atl-empty"><strong>Working hours and breaks, in one view</strong><p>Yellow shows work. Red shows breaks. Select an employee above to begin.</p></div><?php else:
 $tlEvents=$tlContext['sequence']['events'];$tlInitial=array_map(fn($e)=>['at'=>(int)$e['at'],'state'=>$e['state']],$tlEvents);
 $tlRetry=($_POST['action']??'')==='att_timeline'&&!empty($error);
 if($tlRetry){$retry=json_decode((string)($_POST['timeline_events']??''),true);if(is_array($retry)&&count($retry)<=200)$tlInitial=$retry;}
 $tlConfig=['events'=>$tlInitial,'original'=>array_map(fn($e)=>['at'=>(int)$e['at'],'state'=>$e['state']],$tlEvents),'start'=>$tlContext['start'],'end'=>$tlContext['end'],'cutoff'=>$tlRetry?(int)($_POST['timeline_cutoff']??$tlContext['as_of']):$tlContext['as_of'],'offset'=>(int)date('Z'),'editable'=>$tlCanEdit];
 ?>
 <div class="atl-person"><div><h3><?=h($tlContext['person']['name'])?></h3><p><?=h(date('d M Y, h:i A',$tlContext['start']).' → '.date('d M Y, h:i A',$tlContext['end']))?> · <?=h(date_default_timezone_get())?></p></div><span class="badge">Review before saving</span></div>
 <?php if($tlRequest):?><p class="atl-request">Reviewing employee request #<?=$tlRequest?>. Saving this correction also approves the matching pending request.</p><?php endif;?>
 <form method="post" data-attendance-timeline>
 <?php ftoken();chr_hidden('action','att_timeline');chr_hidden('employee_id',$tlEmployee);chr_hidden('attendance_date',$tlDate);chr_hidden('request_id',$tlRequest);chr_hidden('timeline_revision',$tlRetry?($_POST['timeline_revision']??''):$tlContext['revision']);chr_hidden('timeline_cutoff',$tlConfig['cutoff']);chr_hidden('timeline_events',''); ?>
 <script type="application/json" data-timeline-config><?=json_encode($tlConfig,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
 <div class="atl-totals" aria-live="polite"><div><span>Working time</span><strong data-tl-work>—</strong></div><div><span>Breaks between punches</span><strong data-tl-break>—</strong></div><div><span>Change in working time</span><strong data-tl-difference>—</strong></div></div>
 <div class="atl-legend"><span><i class="atl-work-dot"></i> Working</span><span><i class="atl-break-dot"></i> Break</span><span><i class="atl-idle-dot"></i> Outside recorded time</span></div>
 <p class="atl-help">Drag a time marker to adjust it, or use the exact time fields below. Change a break to Working to credit approved office duty.</p>
 <div class="atl-track" data-tl-track aria-label="Working and break timeline"></div><div class="atl-scale"><span data-tl-start></span><span data-tl-end></span></div>
 <p class="atl-message" data-tl-message role="status"></p>
 <div class="atl-boundaries" data-tl-boundaries></div>
 <?php if($tlCanEdit):?><div class="atl-tools"><button type="button" class="button" data-tl-add>Add time marker</button><button type="button" class="button" data-tl-reset>Reset changes</button></div>
 <label class="atl-reason">Reason for correction<textarea name="notes" rows="2" maxlength="3000" required placeholder="For example: client visit from 2:00 PM to 3:00 PM counts as working time."><?=h($tlRetry?($_POST['notes']??''):'')?></textarea></label>
 <footer class="atl-footer"><p>Only approved working time is credited. Original device punches stay unchanged. Existing published payslips are not regenerated.</p><button class="button primary" data-tl-save><?=$tlRequest?'Save correction & approve request':'Save correction'?></button></footer>
 <?php else:?><p>You have view access. An attendance administrator can save corrections.</p><?php endif;?>
 <noscript><p class="alert">Enable JavaScript to edit this timeline. No changes have been saved.</p></noscript>
 </form>
 <details class="atl-audit"><summary>Original punches &amp; correction history</summary>
 <div class="atl-audit-grid"><div><h3>Original device punches</h3><?php if(!$tlContext['raw']):?><p>No device punches for this shift.</p><?php endif;?><ul><?php foreach($tlContext['raw'] as $raw):?><li><?=h(date('d M, h:i:s A',strtotime($raw['punched_at'])))?> — <?=h($raw['direction']==='unknown'?'Direction inferred':$raw['direction'])?> · <?=h($raw['status'])?></li><?php endforeach;?></ul><h3>Administrator punches</h3><ul><?php foreach($tlContext['manual'] as $manual):?><li><?=h(date('d M, h:i:s A',strtotime($manual['punched_at'])).' — '.$manual['action'])?></li><?php endforeach;?></ul></div>
 <div><h3>Saved corrections</h3><?php $q=$pdo->prepare("SELECT r.*,u.name AS actor_name FROM hr_records r LEFT JOIN users u ON u.id=r.created_by WHERE r.module='attendance_timeline' AND r.employee_id=? AND JSON_UNQUOTE(JSON_EXTRACT(r.data,'$.date'))=? ORDER BY r.id DESC LIMIT 20");$q->execute([$tlEmployee,$tlDate]);$tlHistory=$q->fetchAll(PDO::FETCH_ASSOC);if(!$tlHistory):?><p>No timeline corrections yet.</p><?php endif;foreach($tlHistory as $change):$cv=json_decode($change['data'],true);?><article><strong><?=h($change['actor_name']??'Administrator')?></strong> · <?=h($change['status'])?><p><?=h($cv['reason'])?></p><small><?=h($cv['corrected_at'])?></small></article><?php endforeach;?></div></div></details>
 <?php endif;?>
</section>
<script src="assets/js/attendance-timeline.js?v=<?=filemtime(__DIR__.'/../../assets/js/attendance-timeline.js')?>" defer></script>
