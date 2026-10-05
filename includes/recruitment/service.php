<?php
function recruitment_ready(PDO $pdo): bool {
 try{return (bool)$pdo->query("SELECT name FROM hr_migrations WHERE name='008-recruitment'")->fetchColumn();}catch(Throwable $e){return false;}
}
function recruitment_migrate(PDO $pdo): void {
 if(recruitment_ready($pdo))return;
 if(!foundation_ready($pdo))throw new RuntimeException('Install the HRMS foundation first.');
 $lock=$pdo->query("SELECT GET_LOCK('peopleflow_recruitment_migration',10)")->fetchColumn();
 if(!$lock)throw new RuntimeException('Another migration is running. Try again shortly.');
 try{
  if(recruitment_ready($pdo))return;
  $sql=file_get_contents(dirname(__DIR__,2).'/database/008-recruitment.sql');
  foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$pdo->exec($statement);
  $pdo->beginTransaction();
  $permissions=[
   'recruitment.view'=>'View recruitment',
   'recruitment.jobs.manage'=>'Manage job openings',
   'recruitment.candidates.view'=>'View candidates',
   'recruitment.candidates.manage'=>'Manage candidates',
   'recruitment.applications.view'=>'View applications',
   'recruitment.applications.manage'=>'Manage applications',
   'recruitment.interviews.view'=>'View interviews',
   'recruitment.interviews.manage'=>'Manage interviews',
   'recruitment.feedback.create'=>'Create interview feedback',
   'recruitment.offers.view'=>'View offers',
   'recruitment.offers.manage'=>'Manage offers'
  ];
  foreach($permissions as $code=>$label)$pdo->prepare('INSERT IGNORE INTO permissions(code,label) VALUES(?,?)')->execute([$code,$label]);
  $pdo->exec("INSERT IGNORE INTO hr_migrations(name) VALUES('008-recruitment')");
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 finally{$pdo->query("SELECT RELEASE_LOCK('peopleflow_recruitment_migration')");}
}
function recruitment_counts(PDO $pdo): array {
 $out=['open_jobs'=>0,'candidates'=>0,'new_applications'=>0,'interviews'=>0,'selected'=>0,'offers_sent'=>0,'offers_accepted'=>0,'hires_month'=>0];
 if(!recruitment_ready($pdo))return $out;
 $out['open_jobs']=(int)$pdo->query("SELECT COUNT(*) FROM recruitment_jobs WHERE status='Open'")->fetchColumn();
 $out['candidates']=(int)$pdo->query("SELECT COUNT(*) FROM recruitment_candidates")->fetchColumn();
 $out['new_applications']=(int)$pdo->query("SELECT COUNT(*) FROM recruitment_applications WHERE stage='Applied'")->fetchColumn();
 $out['interviews']=(int)$pdo->query("SELECT COUNT(*) FROM recruitment_interviews WHERE status='Scheduled' AND interview_date>=CURDATE()")->fetchColumn();
 $out['selected']=(int)$pdo->query("SELECT COUNT(*) FROM recruitment_applications WHERE stage='Selected'")->fetchColumn();
 $out['offers_sent']=(int)$pdo->query("SELECT COUNT(*) FROM recruitment_offers WHERE status='Sent'")->fetchColumn();
 $out['offers_accepted']=(int)$pdo->query("SELECT COUNT(*) FROM recruitment_offers WHERE status='Accepted'")->fetchColumn();
 $out['hires_month']=(int)$pdo->query("SELECT COUNT(*) FROM recruitment_applications WHERE stage='Hired' AND updated_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
 return $out;
}

function recruitment_job_save(PDO $pdo,array $user,array $in): int {
 if($user['role']!=='super_admin')throw new InvalidArgumentException('Super Admin required.');
 if(!recruitment_ready($pdo))throw new InvalidArgumentException('Install Recruitment first.');
 $id=(int)($in['job_id']??0);$code=strtoupper(ftext($in,'job_code',60,true));$title=ftext($in,'title',190,true);
 $department=(int)($in['department_id']??0)?:null;$designation=(int)($in['designation_id']??0)?:null;$manager=(int)($in['hiring_manager_id']??0)?:null;
 $vacancies=max(1,(int)($in['vacancies']??1));if($vacancies>10000)throw new InvalidArgumentException('Check the vacancies field.');
 $employment=ftext($in,'employment_type',30,true);$types=['Full Time','Part Time','Contract','Intern','Temporary'];if(!in_array($employment,$types,true))throw new InvalidArgumentException('Choose a valid employment type.');
 $status=ftext($in,'status',30,true);$statuses=['Draft','Open','On Hold','Closed','Filled','Archived'];if(!in_array($status,$statuses,true))throw new InvalidArgumentException('Choose a valid job status.');
 $opening=fdate($in,'opening_date');$closing=fdate($in,'closing_date');if($opening&&$closing&&$closing<$opening)throw new InvalidArgumentException('Closing date cannot be before opening date.');
 if($department){$q=$pdo->prepare('SELECT id FROM departments WHERE id=? AND active=1');$q->execute([$department]);if(!$q->fetchColumn())throw new InvalidArgumentException('Choose an active department.');}
 if($designation){$q=$pdo->prepare('SELECT department_id FROM designations WHERE id=? AND active=1');$q->execute([$designation]);$dd=$q->fetchColumn();if($dd===false)throw new InvalidArgumentException('Choose an active designation.');if($department&&$dd!==null&&(int)$dd!==$department)throw new InvalidArgumentException('Designation does not belong to the selected department.');}
 if($manager){$q=$pdo->prepare('SELECT id FROM users WHERE id=? AND active=1');$q->execute([$manager]);if(!$q->fetchColumn())throw new InvalidArgumentException('Choose an active hiring manager.');}
 $values=[$code,$title,$department,$designation,$vacancies,$employment,ftext($in,'experience_required',120),ftext($in,'location',190),ftext($in,'salary_range',120),ftext($in,'description',20000,true),ftext($in,'skills_required',10000),$manager,$opening,$closing,$status];
 $pdo->beginTransaction();
 if($id){$q=$pdo->prepare('SELECT id FROM recruitment_jobs WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Job opening not found.');$pdo->prepare('UPDATE recruitment_jobs SET job_code=?,title=?,department_id=?,designation_id=?,vacancies=?,employment_type=?,experience_required=?,location=?,salary_range=?,description=?,skills_required=?,hiring_manager_id=?,opening_date=?,closing_date=?,status=? WHERE id=?')->execute([...$values,$id]);}
 else{$pdo->prepare('INSERT INTO recruitment_jobs(job_code,title,department_id,designation_id,vacancies,employment_type,experience_required,location,salary_range,description,skills_required,hiring_manager_id,opening_date,closing_date,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$values,$user['id']]);$id=(int)$pdo->lastInsertId();}
 faudit($pdo,$user,'recruitment.job_saved',$id);$pdo->commit();return $id;
}
function recruitment_job_status(PDO $pdo,array $user,array $in): void {
 if($user['role']!=='super_admin')throw new InvalidArgumentException('Super Admin required.');
 $id=(int)($in['job_id']??0);$status=(string)($in['status']??'');if(!in_array($status,['Open','On Hold','Closed','Filled','Archived'],true))throw new InvalidArgumentException('Invalid job status.');
 $pdo->beginTransaction();$q=$pdo->prepare('SELECT id FROM recruitment_jobs WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Job opening not found.');$pdo->prepare('UPDATE recruitment_jobs SET status=? WHERE id=?')->execute([$status,$id]);faudit($pdo,$user,'recruitment.job_status',$id);$pdo->commit();
}
