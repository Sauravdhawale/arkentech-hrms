<?php
if(($user['role']??'')!=='super_admin')return;
// Additional modules retain their existing routes, permissions and stored records.
$additionalGroups=[
 'Onboarding'=>['onboarding'=>'Joining tasks'],
 'Offboarding'=>['exit_tasks'=>'Exit checklist','offboarding'=>'Access & clearance'],
 'Performance & PMS'=>['performance'=>'Goals & KPIs','reviews'=>'Reviews','pip'=>'Improvement plans'],
 'Assets & IT'=>['assets'=>'Assets','access'=>'Access & licenses'],
 'Workplace'=>['tasks'=>'Tasks','policies'=>'Policies & handbook','documents'=>'Document register','expenses'=>'Expenses','announcements'=>'Announcements','helpdesk'=>'Help desk'],
 'Reports & earlier records'=>['reports'=>'Exports','audit'=>'Activity history','salary'=>'Earlier salary structures','components'=>'Earlier components','payroll'=>'Earlier payroll records','jobs'=>'Earlier job records','recruitment'=>'Earlier candidates','interviews'=>'Earlier interviews','offers'=>'Earlier offer checklists']
];
if(att_biometric_enabled($pdo))$additionalGroups['Reports & earlier records']+=['device_attendance'=>'Device punch attendance','device_monthly'=>'Device punch summary'];
foreach($additionalGroups as $label=>$links):?>
<details class="attendance-navigation" <?=isset($links[$page])?'open':''?>><summary><span><?=h($label)?></span></summary><div><?php foreach($links as $key=>$label)fnav($key,$label);?></div></details>
<?php endforeach;?>
