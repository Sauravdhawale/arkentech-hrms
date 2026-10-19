<?php
/** Review, finalize and publish only the explicitly selected, valid snapshots. */
function pay_publish_selected(PDO $db,array $actor,array $in):int {
 foreach(['payroll.process.view','payroll.process.manage','payroll.process.approve','payroll.process.finalize','payslip.generate'] as $permission)pay_allow($db,$actor,$permission);
 if(empty($in['confirm']))throw new InvalidArgumentException('Review and confirm the selected payslips first.');
 $reason=ftext($in,'reason',1000,true);
 $raw=$in['entry_ids']??[];
 if(!is_array($raw)||!$raw||count($raw)>1000)throw new InvalidArgumentException('Select at least one ready payslip (maximum 1000).');
 $ids=[];foreach($raw as $value){if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new InvalidArgumentException('Invalid payslip selection.');$ids[]=(int)$value;}
 $ids=array_values(array_unique($ids));sort($ids);
 $db->beginTransaction();try{
  chr_lock($db);$run=pay_run($db,(int)($in['run_id']??0),true);pay_version($run,$in);
  if(!in_array($run['status'],['Calculated','Reviewed','Approved','Finalized','Paid'],true))throw new InvalidArgumentException('Update draft payslips before publishing.');
  $settings=json_decode($run['settings_snapshot'],true);[, $to]=pay_month($run['month']);
  $closing=(new DateTimeImmutable($to))->modify('+'.(int)$settings['payroll']['closing_day'].' days')->format('Y-m-d');
  if((new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d')<$closing)throw new InvalidArgumentException('Payslips can be published from '.$closing.' after the month closes.');
  $q=$db->prepare('SELECT * FROM hr_payroll_entries WHERE run_id=? AND id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id FOR UPDATE');$q->execute([$run['id'],...$ids]);$entries=$q->fetchAll(PDO::FETCH_ASSOC);
  if(count($entries)!==count($ids))throw new InvalidArgumentException('Every selected payslip must belong to this payroll month.');
  foreach($entries as $entry){
   $snapshot=json_decode($entry['snapshot'],true)?:[];
   if(!empty($entry['published_at']))throw new InvalidArgumentException('A selected payslip is already published. Refresh the list.');
   if($entry['exceptions']!==''||!in_array($entry['status'],['Calculated','Reviewed','Approved','Finalized','Paid'],true)||(!in_array($entry['status'],['Finalized','Paid'],true)&&empty($snapshot['draft_generated_at'])))throw new InvalidArgumentException('Select only generated payslips without errors or holds.');
   if(!empty($snapshot['exceptions'])||(int)($snapshot['employee']['user_id']??0)!==(int)$entry['employee_id']||!isset($snapshot['gross'],$snapshot['deductions'],$snapshot['net'])||(int)$entry['gross']!==(int)$snapshot['gross']||(int)$entry['deductions']!==(int)$snapshot['deductions']||(int)$entry['net']!==(int)$snapshot['net']||(int)$entry['net']<0||(int)$entry['gross']-(int)$entry['deductions']!==(int)$entry['net'])throw new InvalidArgumentException('A selected payslip has an incomplete calculation. Update draft payslips first.');
   pay_legacy_check($db,(int)$entry['employee_id'],$run['month']);
  }
  foreach($entries as $entry){
   $number=strtr($settings['payslip']['number_format'],['{YYYY}'=>substr($run['month'],0,4),'{MM}'=>substr($run['month'],5,2),'{ID}'=>(string)$entry['id']]);
   $db->prepare('UPDATE hr_payroll_entries SET status=?,payslip_number=?,published_at=NOW() WHERE id=?')->execute([$entry['status']==='Paid'?'Paid':'Finalized',$number,$entry['id']]);
   foreach(['reviewed','approved','finalized','published'] as $stage)pay_event($db,$actor,'entry.'.$stage,(int)$run['id'],(int)$entry['id'],$reason);
  }
  $q=$db->prepare("SELECT COUNT(*) FROM hr_payroll_entries WHERE run_id=? AND (published_at IS NULL OR status NOT IN ('Finalized','Paid'))");$q->execute([$run['id']]);
  if(!(int)$q->fetchColumn())$db->prepare("UPDATE hr_payroll_runs SET status=CASE WHEN status='Paid' THEN 'Paid' ELSE 'Finalized' END,finalized_at=COALESCE(finalized_at,NOW()) WHERE id=?")->execute([$run['id']]);
  $db->prepare('UPDATE hr_payroll_runs SET version=version+1 WHERE id=?')->execute([$run['id']]);
  $db->commit();return count($entries);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
