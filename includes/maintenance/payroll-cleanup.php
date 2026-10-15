<?php
/** Explicitly scoped permanent cleanup. Never called by application startup or migrations. */
function payroll_cleanup(PDO $db,bool $apply=false):array {
 $tables=array_fill_keys($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),true);
 $targets=['hr_payslip_documents','hr_payroll_overrides','hr_payroll_exceptions','hr_payroll_attendance_snapshots','hr_payroll_adjustments','hr_payroll_events','hr_payroll_entries','hr_payroll_runs','hr_salary_assignments','hr_payslip_templates'];
 // Fail closed for a newer payroll schema, rather than silently leaving or guessing at data.
 foreach(array_keys($tables) as $table)if(preg_match('/^hr_(payroll_|payslip_|salary_)/',$table)&&!in_array($table,$targets,true))throw new RuntimeException('Unrecognized payroll table; inspect before cleanup: '.$table);
 $modules=['salary','payroll','contracts','advances','components','pay_component','pay_structure','pay_statutory','pay_settings','pay_payslip','payroll_archive'];
 $placeholders=implode(',',array_fill(0,count($modules),'?'));$plan=[];$sql=[];
 foreach($targets as $table)if(isset($tables[$table])){$plan[$table]=(int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();$sql[]=['DELETE FROM `'.$table.'`',[]];}
 if(isset($tables['hr_records'])){
  foreach(['hr_files','hr_acknowledgements'] as $table)if(isset($tables[$table])){$where='record_id IN (SELECT id FROM hr_records WHERE module IN ('.$placeholders.'))';$q=$db->prepare('SELECT COUNT(*) FROM '.$table.' WHERE '.$where);$q->execute($modules);$plan[$table]=(int)$q->fetchColumn();$sql[]=['DELETE FROM '.$table.' WHERE '.$where,$modules];}
  $q=$db->prepare('SELECT COUNT(*) FROM hr_records WHERE module IN ('.$placeholders.')');$q->execute($modules);$plan['payroll_hr_records']=(int)$q->fetchColumn();$sql[]=['DELETE FROM hr_records WHERE module IN ('.$placeholders.')',$modules];
 }
 if(!$apply)return ['applied'=>false,'rows'=>$plan];
 $db->beginTransaction();try{
  // Uses the application's shared write lock; deployed retirement blocks new payroll writes.
  if(isset($tables['company_settings']))$db->query('SELECT id FROM company_settings WHERE id=1 FOR UPDATE')->fetchColumn();
  $deleted=[];foreach($sql as [$query,$args]){$q=$db->prepare($query);$q->execute($args);$deleted[]=$q->rowCount();}
  $remaining=payroll_cleanup($db,false);if(array_sum($remaining['rows'])!==0)throw new RuntimeException('Cleanup verification failed.');
  $db->commit();return ['applied'=>true,'deleted_rows'=>array_sum($deleted),'remaining'=>$remaining['rows']];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
