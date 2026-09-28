<?php
// Admin review queue only; no employee account context or unread-state writes.
$notificationKinds=['leave'=>['Leave request','leaves'],'regularisation'=>['Attendance request','attendance_requests']];
$notificationCount=(int)$pdo->query("SELECT COUNT(*) FROM hr_requests WHERE status='Pending' AND kind IN ('leave','regularisation')")->fetchColumn();
$notificationRows=$pdo->query("SELECT r.id,r.kind,r.created_at,u.name FROM hr_requests r JOIN users u ON u.id=r.user_id WHERE r.status='Pending' AND r.kind IN ('leave','regularisation') ORDER BY r.created_at DESC,r.id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="admin-notifications">
<button type="button" class="icon-button notification-toggle" aria-label="Notifications: <?=$notificationCount?> pending requests" aria-expanded="false" aria-controls="admin-notification-panel">
<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
<?php if($notificationCount):?><span class="notification-count" aria-hidden="true"><?=$notificationCount>99?'99+':$notificationCount?></span><?php endif;?>
</button>
<section class="notification-panel" id="admin-notification-panel" aria-labelledby="admin-notification-title" hidden>
<div class="notification-heading"><div><h2 id="admin-notification-title">Notifications</h2><p class="hint">Super Admin · Pending approvals</p></div><span class="badge"><?=$notificationCount?></span></div>
<?php if(!$notificationRows):?><div class="empty-state"><strong>You’re all caught up</strong><p>No leave or attendance requests are waiting for review.</p></div><?php else:?><div class="notification-items">
<?php foreach($notificationRows as $notification):[$notificationLabel,$notificationPage]=$notificationKinds[$notification['kind']];?>
<a class="notification-item" href="?<?=h(http_build_query(['page'=>$notificationPage,'status'=>'Pending']))?>"><strong><?=h($notification['name'])?></strong><span><?=h($notificationLabel)?> #<?=(int)$notification['id']?></span><small><?=h($notification['created_at'])?> · Review request →</small></a>
<?php endforeach;?></div><?php endif;?>
<div class="notification-footer"><a href="?page=leaves&amp;status=Pending">Leave requests</a><a href="?page=attendance_requests">Attendance requests</a></div>
<p class="notification-note">Updates when you load or refresh a page.</p>
</section></div>
