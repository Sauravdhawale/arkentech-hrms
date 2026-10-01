<?php
if($user['role']!=='super_admin'){http_response_code(403);exit('Super Admin access required.');}
if(!recruitment_ready($pdo)):?>
<section class="card empty-state">
 <h2>Set up Recruitment / ATS</h2>
 <p>This additive setup creates the Phase 4 recruitment tables. Existing employees, documents, attendance, leave and payroll records are not changed.</p>
 <form method="post"><?php ftoken();?><input type="hidden" name="action" value="install_recruitment"><button class="button primary">Install Recruitment database</button></form>
</section>
<?php return;endif;
if($page==='recruitment_dashboard'):
 $c=recruitment_counts($pdo);
 $cards=['Open Positions'=>$c['open_jobs'],'Total Candidates'=>$c['candidates'],'New Applications'=>$c['new_applications'],'Interviews Scheduled'=>$c['interviews'],'Selected Candidates'=>$c['selected'],'Offers Sent'=>$c['offers_sent'],'Offers Accepted'=>$c['offers_accepted'],'Hires This Month'=>$c['hires_month']];?>
<div class="stats-grid"><?php foreach($cards as $label=>$value):?><section class="card stat-card"><p><?=h($label)?></p><strong><?=number_format($value)?></strong></section><?php endforeach;?></div>
<div class="dashboard-grid">
 <section class="card"><div class="section-heading"><div><p class="eyebrow">PIPELINE</p><h2>Hiring pipeline</h2></div></div><div class="pipeline-row"><?php foreach(['Applied','Screening','Shortlisted','Interview','Selected','Offer Sent','Hired'] as $stage):$q=$pdo->prepare('SELECT COUNT(*) FROM recruitment_applications WHERE stage=?');$q->execute([$stage]);?><div><strong><?=number_format((int)$q->fetchColumn())?></strong><span><?=h($stage)?></span></div><?php endforeach;?></div></section>
 <section class="card"><div class="section-heading"><div><p class="eyebrow">UPCOMING</p><h2>Upcoming interviews</h2></div></div><?php $rows=$pdo->query("SELECT i.*,c.first_name,c.last_name,j.title job_title FROM recruitment_interviews i JOIN recruitment_applications a ON a.id=i.application_id JOIN recruitment_candidates c ON c.id=a.candidate_id JOIN recruitment_jobs j ON j.id=a.job_id WHERE i.status='Scheduled' AND i.interview_date>=CURDATE() ORDER BY i.interview_date,i.start_time LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);if(!$rows):?><p class="muted">No upcoming interviews.</p><?php else:?><div class="simple-list"><?php foreach($rows as $r):?><div><strong><?=h(trim($r['first_name'].' '.$r['last_name']))?></strong><span><?=h($r['job_title'])?> · <?=h($r['interview_date'])?> <?=h(substr($r['start_time'],0,5))?></span></div><?php endforeach;?></div><?php endif;?></section>
</div>
<?php elseif($page==='recruitment_jobs'):
 $rows=$pdo->query("SELECT j.*,d.name department_name,g.name designation_name,u.name manager_name,(SELECT COUNT(*) FROM recruitment_applications a WHERE a.job_id=j.id) applications FROM recruitment_jobs j LEFT JOIN departments d ON d.id=j.department_id LEFT JOIN designations g ON g.id=j.designation_id LEFT JOIN users u ON u.id=j.hiring_manager_id ORDER BY j.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);?>
<section class="card"><div class="section-heading"><div><p class="eyebrow">RECRUITMENT</p><h2>Job openings</h2></div><button class="button primary" type="button" disabled title="Job creation form is the next implementation step">+ Create Job</button></div>
<div class="table-wrap"><table><thead><tr><th>Job Code</th><th>Job Title</th><th>Department</th><th>Designation</th><th>Vacancies</th><th>Applications</th><th>Opening</th><th>Closing</th><th>Status</th><th>Hiring Manager</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="10" class="muted">No job openings yet.</td></tr><?php endif;foreach($rows as $r):?><tr><td><?=h($r['job_code'])?></td><td><strong><?=h($r['title'])?></strong></td><td><?=h($r['department_name']??'—')?></td><td><?=h($r['designation_name']??'—')?></td><td><?=number_format((int)$r['vacancies'])?></td><td><?=number_format((int)$r['applications'])?></td><td><?=h($r['opening_date']??'—')?></td><td><?=h($r['closing_date']??'—')?></td><td><?php fbadge($r['status'],$r['status']==='Open');?></td><td><?=h($r['manager_name']??'—')?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php else:
 $meta=[
  'recruitment_candidates'=>['Candidates','Candidate database is ready for the next implementation step.'],
  'recruitment_applications'=>['Applications','Application pipeline and table view will live here.'],
  'recruitment_interviews'=>['Interviews','Interview scheduling and feedback will live here.'],
  'recruitment_offers'=>['Offers','Offer creation, approval and candidate-to-employee conversion will live here.']
 ][$page];?>
<section class="card empty-state"><h2><?=h($meta[0])?></h2><p><?=h($meta[1])?></p><p class="muted">The database foundation for this section is included in Phase 4A.</p></section>
<?php endif;
