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
