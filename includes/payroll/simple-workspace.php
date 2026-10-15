<?php
/** Four-section workspace adapter over the tested payroll/snapshot services. */
function pay_simple_post(PDO $db,array $actor,string $action,array $in):string {
 if($action==='pay_initialize'){
  pay_install($db,$actor);pay_workflow_install($db,$actor);pay_install_starter($db,$actor);
  return 'Payroll enabled. Set employee salaries and review Configuration before generating payslips.';
 }
 $month=(string)($in['month']??'');pay_month($month);
 pay_allow($db,$actor,'payroll.process.manage');
 $q=$db->prepare('SELECT id FROM hr_payroll_runs WHERE month=?');$q->execute([$month]);$id=(int)$q->fetchColumn();
 if($action==='pay_generate_simple'){
  if(!$id)$id=pay_create_run($db,$actor,$month);
  else{ $run=pay_run($db,$id);pay_version($run,$in);pay_mutable($run);if(!in_array($run['status'],['Draft','Calculated'],true))throw new InvalidArgumentException('This month is already under review. It cannot be regenerated.');[, $end]=pay_month($month);
   $missing=$db->prepare("SELECT COUNT(*) FROM employees e JOIN users u ON u.id=e.user_id WHERE e.deleted_at IS NULL AND u.active=1 AND e.employment_status='Active' AND (e.joining_date IS NULL OR e.joining_date<=?) AND NOT EXISTS(SELECT 1 FROM hr_payroll_entries p WHERE p.run_id=? AND p.employee_id=e.user_id)");
   $missing->execute([$end,$id]);if($missing->fetchColumn())pay_add_employees($db,$actor,['run_id'=>$id,'version'=>$run['version']]); }
  $run=pay_run($db,$id);pay_calculate_run($db,$actor,['run_id'=>$id,'version'=>$run['version']]);
  $run=pay_run($db,$id);$expected=(int)$run['version'];$db->beginTransaction();try{
   chr_lock($db);$locked=pay_run($db,$id,true);pay_version($locked,['version'=>$expected]);
   if($locked['status']!=='Calculated')throw new InvalidArgumentException('Payroll changed. Reload before generating.');
   $q=$db->prepare("SELECT id,snapshot FROM hr_payroll_entries WHERE run_id=? AND exceptions='' AND status='Calculated'");$q->execute([$id]);$n=0;
   foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$s=json_decode($row['snapshot'],true);$s['draft_generated_at']=date('Y-m-d H:i:s');$db->prepare('UPDATE hr_payroll_entries SET snapshot=? WHERE id=?')->execute([pay_json($s),$row['id']]);$n++;}
   $db->prepare('UPDATE hr_payroll_runs SET version=version+1 WHERE id=?')->execute([$id]);pay_event($db,$actor,'payslips.generated',$id);$db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  return $n.' draft payslips generated. Review Action Required before finalizing. Employees see finalized, published payslips only.';
 }
 // Explicit month approval; existing service still enforces every permission and month-close guard.
 foreach(['payroll.process.approve','payroll.process.finalize','payslip.generate'] as $permission)pay_allow($db,$actor,$permission);
 if(!$id)throw new InvalidArgumentException('Generate this month first.');
 $run=pay_run($db,$id);pay_version($run,$in);
 if(empty($in['confirm']))throw new InvalidArgumentException('Confirm you reviewed the payroll totals.');
 $reason=ftext($in,'reason',1000,true);
 foreach(['Calculated'=>'review','Reviewed'=>'approve','Approved'=>'finalize','Finalized'=>'publish'] as $status=>$transition){
  $run=pay_run($db,$id);if($run['status']===$status)pay_transition($db,$actor,['run_id'=>$id,'version'=>$run['version'],'transition'=>$transition,'reason'=>$reason,'confirm'=>1]);
 }
 return 'Payslips finalized and published to employees.';
}
function pay_simple_stage(array $r):string {
 if($r['run_status']==='Paid')return 'Paid';
 if(in_array($r['run_status'],['Finalized','Locked'],true))return 'Finalized';
 $s=json_decode($r['snapshot'],true)?:[];
 return !empty($s['draft_generated_at'])&&$r['exceptions']===''?'Generated':'Draft';
}
function pay_workspace_ready(PDO $db):bool {
 if(!pay_ready($db))return false;
 // Retirement removes permission rows but retains historical migration markers.
 return (bool)$db->query("SELECT id FROM permissions WHERE code='payroll.process.manage'")->fetchColumn();
}
function pay_config_url(string $module):string {
 return match($module){'pay_component'=>'?page=salary_components&tab=rules','pay_structure'=>'?page=salary_components&tab=rules&kind=structure','pay_statutory'=>'?page=salary_components&tab=statutory','pay_payslip'=>'?page=payslip_settings',default=>'?page=salary_components&tab=general'};
}
