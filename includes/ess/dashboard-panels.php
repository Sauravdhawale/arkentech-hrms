<?php
$dashboardYear=(int)substr($month,0,4);$balances=ess_balances($pdo,$owner,$dashboardYear);
$summary=[];foreach($rows as $r)$summary[$r['status']]=($summary[$r['status']]??0)+1;
$lateCount=count(array_filter($rows,fn($r)=>$r['late_minutes']>0));$earlyCount=count(array_filter($rows,fn($r)=>$r['early_minutes']>0));
$events=array_values(array_filter(ess_events($pdo,$employee),fn($e)=>$e['end']>=$from&&$e['start']<=$to));
?>
<div class="ess-dashboard-tasks">
 <?php foreach(['Open tasks'=>count(array_filter($tasks,fn($r)=>$r['status']!=='Completed')),'Completed tasks'=>count(array_filter($tasks,fn($r)=>$r['status']==='Completed'))] as $label=>$value):?>
 <a class="panel ess-task-summary" href="?page=tasks"><span><?=h($label)?><small>View my tasks →</small></span><strong><?=(int)$value?></strong></a>
 <?php endforeach;?>
</div>
<form method="get" class="panel work-form ess-overview-filter"><input type="hidden" name="page" value="overview"><div><h2>Your monthly overview</h2><p>Attendance, calendar and birthdays in one place.</p></div><label>Month<input type="month" name="month" value="<?=h($month)?>" required></label><button class="primary">Apply</button></form>
<section class="panel ess-dashboard-panel"><div class="ess-panel-heading"><div><span class="ess-kicker">TIME OFF</span><h2>Leave balance</h2><p><?=h($dashboardYear)?> · Available days</p></div><a class="ess-text-link" href="?page=balances">View leave →</a></div>
 <div class="ess-balance-grid"><?php foreach($balances as $balance):?><div><span><?=h($balance['type'])?></span><strong><?=h($balance['available'])?><small>days</small></strong></div><?php endforeach;if(!$balances):?><p class="ess-panel-empty">Your leave balances will appear after HR allocates them.</p><?php endif;?></div>
</section>
<section class="panel ess-dashboard-panel"><div class="ess-panel-heading"><div><span class="ess-kicker">ATTENDANCE</span><h2><?=h(date('F Y',strtotime($from)))?></h2><p>Your monthly attendance breakdown</p></div><a class="ess-text-link" href="?page=attendance&amp;month=<?=h($month)?>">View details →</a></div>
 <div class="ess-attendance-flags"><div><strong><?=$lateCount?></strong><span>Late arrivals</span></div><div><strong><?=$earlyCount?></strong><span>Early departures</span></div></div>
 <div class="ess-attendance-summary"><?php foreach($summary as $status=>$count):?><div><span><?=h($status)?></span><strong><?=(int)$count?> <small>days</small></strong></div><?php endforeach;if(!$summary):?><p class="ess-panel-empty">No attendance records for this month.</p><?php endif;?></div>
</section>
<section class="panel ess-dashboard-panel"><div class="ess-panel-heading"><div><span class="ess-kicker">WHAT’S COMING UP</span><h2>Company calendar</h2></div></div>
 <div class="ess-event-list"><?php foreach($events as $event):?><article><div class="ess-event-date"><strong><?=h(date('d',strtotime($event['start'])))?></strong><span><?=h(date('M',strtotime($event['start'])))?></span></div><div><h3><?=h($event['title'])?></h3><p><?=h($event['start'])?><?=($event['end']!==$event['start'])?' – '.h($event['end']):''?></p><?php if(!empty($event['description'])):?><p><?=h($event['description'])?></p><?php endif;?></div></article><?php endforeach;if(!$events):?><div class="ess-panel-empty"><strong>No events this month</strong><p>Company events and holidays will appear here.</p></div><?php endif;?></div>
</section>
<?php $portal=chr_rows($pdo,'ess_settings')[0]['values']??[];if(!empty($portal['birthdays_enabled'])):
$b=$pdo->prepare("SELECT u.name,DATE_FORMAT(e.birth_date,'%d %b') birthday FROM employees e JOIN users u ON u.id=e.user_id WHERE e.deleted_at IS NULL AND u.active=1 AND e.employment_status='Active' AND MONTH(e.birth_date)=? ORDER BY DAY(e.birth_date)");$b->execute([(int)substr($month,5,2)]);$birthdays=$b->fetchAll(PDO::FETCH_ASSOC);?>
<section class="panel ess-dashboard-panel"><div class="ess-panel-heading"><div><span class="ess-kicker">CELEBRATE TOGETHER</span><h2>Birthdays this month</h2></div></div><div class="ess-birthday-list"><?php foreach($birthdays as $person):?><div><span class="ess-colleague-avatar" aria-hidden="true"><?=h(preg_match('/^./u',$person['name'],$initial)?$initial[0]:'')?></span><strong><?=h($person['name'])?></strong><span><?=h($person['birthday'])?></span></div><?php endforeach;if(!$birthdays):?><div class="ess-panel-empty"><strong>No birthdays this month</strong><p>Check next month for upcoming celebrations.</p></div><?php endif;?></div></section>
<?php endif;?>
<div class="quick-links ess-dashboard-links"><a href="?page=leaves">Apply for leave</a><a href="?page=inbox">Open inbox</a><a href="?page=profile">My profile</a></div>
