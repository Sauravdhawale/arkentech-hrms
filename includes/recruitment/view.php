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
 $editId=(int)($_GET['edit']??0);$edit=null;if($editId){$q=$pdo->prepare('SELECT * FROM recruitment_jobs WHERE id=?');$q->execute([$editId]);$edit=$q->fetch(PDO::FETCH_ASSOC)?:null;}
 $showForm=isset($_GET['new'])||$edit;
 $departments=$pdo->query("SELECT id,name FROM departments WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
 $designations=$pdo->query("SELECT id,name,department_id FROM designations WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
 $managers=$pdo->query("SELECT u.id,u.name FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.active=1 AND (u.role='super_admin' OR e.deleted_at IS NULL) ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
 if($showForm):$v=$edit?:['job_code'=>'','title'=>'','department_id'=>'','designation_id'=>'','vacancies'=>1,'employment_type'=>'Full Time','experience_required'=>'','location'=>'','salary_range'=>'','description'=>'','skills_required'=>'','hiring_manager_id'=>'','opening_date'=>date('Y-m-d'),'closing_date'=>'','status'=>'Draft'];?>
<section class="card">
 <div class="section-heading"><div><p class="eyebrow">RECRUITMENT</p><h2><?=$edit?'Edit Job Opening':'Create Job Opening'?></h2></div><a class="button" href="?page=recruitment_jobs">Back to Jobs</a></div>
 <form method="post" class="form-grid"><?php ftoken();?><input type="hidden" name="action" value="recruitment_save_job"><input type="hidden" name="job_id" value="<?= (int)($edit['id']??0) ?>">
  <?php field('job_code','Job Code',$v,'text',true,60);field('title','Job Title',$v,'text',true,190);?>
  <label>Department<select name="department_id"><option value="">Select department</option><?php foreach($departments as $d):?><option value="<?=$d['id']?>" <?=((string)$v['department_id']===(string)$d['id'])?'selected':''?>><?=h($d['name'])?></option><?php endforeach;?></select></label>
  <label>Designation<select name="designation_id"><option value="">Select designation</option><?php foreach($designations as $d):?><option value="<?=$d['id']?>" data-department="<?=h($d['department_id'])?>" <?=((string)$v['designation_id']===(string)$d['id'])?'selected':''?>><?=h($d['name'])?></option><?php endforeach;?></select></label>
  <?php field('vacancies','Number of Vacancies',$v,'number',true,5);?>
  <label>Employment Type<select name="employment_type"><?php foreach(['Full Time','Part Time','Contract','Intern','Temporary'] as $o):?><option <?=($v['employment_type']===$o?'selected':'')?>><?=h($o)?></option><?php endforeach;?></select></label>
  <?php field('experience_required','Experience Required',$v,'text',false,120);field('location','Location',$v,'text',false,190);field('salary_range','Salary Range (optional)',$v,'text',false,120);?>
  <label>Hiring Manager<select name="hiring_manager_id"><option value="">Select manager</option><?php foreach($managers as $m):?><option value="<?=$m['id']?>" <?=((string)$v['hiring_manager_id']===(string)$m['id'])?'selected':''?>><?=h($m['name'])?></option><?php endforeach;?></select></label>
  <?php field('opening_date','Opening Date',$v,'date');field('closing_date','Closing Date',$v,'date');?>
  <label>Status<select name="status"><?php foreach(['Draft','Open','On Hold','Closed','Filled','Archived'] as $o):?><option <?=($v['status']===$o?'selected':'')?>><?=h($o)?></option><?php endforeach;?></select></label>
  <label class="full">Job Description <span aria-label="required">*</span><textarea name="description" rows="8" required><?=h($v['description'])?></textarea></label>
  <label class="full">Skills Required<textarea name="skills_required" rows="5"><?=h($v['skills_required'])?></textarea></label>
  <div class="full form-actions"><button class="button primary"><?=$edit?'Save Changes':'Create Job'?></button><a class="button" href="?page=recruitment_jobs">Cancel</a></div>
 </form>
</section>
<?php else:
 $statusFilter=(string)($_GET['status']??'');$departmentFilter=(int)($_GET['department']??0);
 $sql="SELECT j.*,d.name department_name,g.name designation_name,u.name manager_name,(SELECT COUNT(*) FROM recruitment_applications a WHERE a.job_id=j.id) applications FROM recruitment_jobs j LEFT JOIN departments d ON d.id=j.department_id LEFT JOIN designations g ON g.id=j.designation_id LEFT JOIN users u ON u.id=j.hiring_manager_id WHERE 1=1";$args=[];
 if($statusFilter!==''){$sql.=" AND j.status=?";$args[]=$statusFilter;}if($departmentFilter){$sql.=" AND j.department_id=?";$args[]=$departmentFilter;}$sql.=" ORDER BY j.created_at DESC";$q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);?>
<section class="card"><div class="section-heading"><div><p class="eyebrow">RECRUITMENT</p><h2>Job Openings</h2></div><a class="button primary" href="?page=recruitment_jobs&new=1">+ Create Job</a></div>
<form method="get" class="filter-bar"><input type="hidden" name="page" value="recruitment_jobs"><label>Status<select name="status"><option value="">All statuses</option><?php foreach(['Draft','Open','On Hold','Closed','Filled','Archived'] as $o):?><option <?=($statusFilter===$o?'selected':'')?>><?=h($o)?></option><?php endforeach;?></select></label><label>Department<select name="department"><option value="">All departments</option><?php foreach($departments as $d):?><option value="<?=$d['id']?>" <?=($departmentFilter===(int)$d['id']?'selected':'')?>><?=h($d['name'])?></option><?php endforeach;?></select></label><button class="button">Filter</button><?php if($statusFilter!==''||$departmentFilter):?><a class="button" href="?page=recruitment_jobs">Clear</a><?php endif;?></form>
<div class="table-wrap"><table><thead><tr><th>Job Code</th><th>Job Title</th><th>Department</th><th>Designation</th><th>Vacancies</th><th>Applications</th><th>Opening</th><th>Closing</th><th>Status</th><th>Hiring Manager</th><th>Actions</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="11" class="muted">No job openings yet.</td></tr><?php endif;foreach($rows as $r):?><tr><td><?=h($r['job_code'])?></td><td><strong><?=h($r['title'])?></strong></td><td><?=h($r['department_name']??'—')?></td><td><?=h($r['designation_name']??'—')?></td><td><?=number_format((int)$r['vacancies'])?></td><td><?=number_format((int)$r['applications'])?></td><td><?=h($r['opening_date']??'—')?></td><td><?=h($r['closing_date']??'—')?></td><td><?php fbadge($r['status'],$r['status']==='Open');?></td><td><?=h($r['manager_name']??'—')?></td><td><div class="table-actions"><a href="?page=recruitment_jobs&edit=<?=$r['id']?>">Edit</a><?php if($r['status']!=='Closed'&&$r['status']!=='Archived'):?><form method="post"><?php ftoken();?><input type="hidden" name="action" value="recruitment_job_status"><input type="hidden" name="job_id" value="<?=$r['id']?>"><input type="hidden" name="status" value="Closed"><button class="link-button">Close</button></form><?php endif;?><?php if($r['status']!=='Archived'):?><form method="post"><?php ftoken();?><input type="hidden" name="action" value="recruitment_job_status"><input type="hidden" name="job_id" value="<?=$r['id']?>"><input type="hidden" name="status" value="Archived"><button class="link-button">Archive</button></form><?php endif;?></div></td></tr><?php endforeach;?></tbody></table></div></section>
<?php endif;?>
<?php else:
 $meta=[
  'recruitment_candidates'=>['Candidates','Candidate database is ready for the next implementation step.'],
  'recruitment_applications'=>['Applications','Application pipeline and table view will live here.'],
  'recruitment_interviews'=>['Interviews','Interview scheduling and feedback will live here.'],
  'recruitment_offers'=>['Offers','Offer creation, approval and candidate-to-employee conversion will live here.']
 ][$page];?>
<section class="card empty-state"><h2><?=h($meta[0])?></h2><p><?=h($meta[1])?></p><p class="muted">The database foundation for this section is included in Phase 4A.</p></section>
<?php endif;
