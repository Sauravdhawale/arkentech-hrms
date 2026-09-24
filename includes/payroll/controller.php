<?php
require_once __DIR__.'/service.php';
$payPages=[
 'payroll_dashboard'=>['Payroll dashboard','payroll.view'],
 'employee_salary'=>['Employee Salary','payroll.salary.view'],
 'payroll_processing'=>['Payroll Processing','payroll.process.view'],
 'payroll_payslips'=>['Payslips','payslip.view'],
 'payroll_history'=>['Payroll History','payroll.process.view'],
 'payroll_reports'=>['Payroll Reports','payroll.reports'],
 'salary_components'=>['Salary Components','payroll.settings.view'],
 'salary_structures'=>['Salary Structures','payroll.settings.view'],
 'statutory_settings'=>['Statutory Settings','payroll.settings.view'],
 'payroll_settings'=>['Payroll Settings','payroll.settings.view'],
 'payslip_settings'=>['Payslip Settings','payroll.settings.view']
];
function pay_handle_post(PDO $db,array $actor,string $action,array $in):string {
 if($action==='pay_install'){pay_install($db,$actor);return 'Payroll tables added. Existing data preserved.';}
 if(!pay_ready($db))throw new InvalidArgumentException('Install the Payroll upgrade first.');
 switch($action){
  case 'pay_config':pay_save_config($db,$actor,$in);return 'Configuration revision saved.';
  case 'pay_preview':
   pay_allow($db,$actor,'payroll.salary.manage');$preview=pay_assignment_preview($db,$in);
   $q=$db->prepare('SELECT id FROM hr_salary_assignments WHERE employee_id=? ORDER BY effective_from DESC LIMIT 1');$q->execute([$preview['employee']['user_id']]);
   $in['previous_id']=(int)$q->fetchColumn();$in['preview_hash']=hash('sha256',pay_json($preview));
   $_SESSION['pay_preview']=['actor'=>$actor['id'],'input'=>$in,'preview'=>$preview];return 'Review the salary breakup below before saving.';
  case 'pay_assign':pay_assign($db,$actor,$in);unset($_SESSION['pay_preview']);return 'Salary revision saved. Earlier salary history retained.';
  case 'pay_create':$id=pay_create_run($db,$actor,(string)($in['month']??''),!empty($in['employee_id'])?[(int)$in['employee_id']]:[]);$_SESSION['pay_redirect']=$id;return 'Draft payroll created.';
  case 'pay_calculate':pay_calculate_run($db,$actor,$in);return 'Payroll calculated. Review exceptions and totals.';
  case 'pay_adjust':pay_adjust($db,$actor,$in);return 'Adjustment recorded. Recalculate this run.';
  case 'pay_transition':pay_transition($db,$actor,$in);return 'Payroll workflow updated.';
  default:throw new InvalidArgumentException('Unknown payroll action.');
 }
}
function pay_report_rows(PDO $db,array $in):array {
 $month=(string)($in['month']??date('Y-m'));pay_month($month);
 $sql='SELECT e.*,r.month,r.status AS run_status,r.settings_snapshot,u.name,p.employee_code,p.department_id FROM hr_payroll_entries e JOIN hr_payroll_runs r ON r.id=e.run_id JOIN users u ON u.id=e.employee_id JOIN employees p ON p.user_id=e.employee_id WHERE r.month=?';
 $args=[$month];if(!empty($in['employee_id'])){$sql.=' AND e.employee_id=?';$args[]=(int)$in['employee_id'];}
 $q=$db->prepare($sql.' ORDER BY u.name');$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 // Historical department filtering uses the payroll snapshot, not today's employee assignment.
 if(!empty($in['department_id']))$rows=array_values(array_filter($rows,fn($r)=>(int)(json_decode($r['snapshot'],true)['employee']['department_id']??$r['department_id'])===(int)$in['department_id']));
 return $rows;
}
function pay_export(PDO $db,array $actor,array $in):void {
 pay_allow($db,$actor,'payroll.reports');if(!pay_ready($db))return;
 $rows=pay_report_rows($db,$in);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="payroll-'.($in['month']??date('Y-m')).'.csv"');header('Cache-Control: no-store');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Month','Employee ID','Employee','Department','Status','Currency','Gross','Deductions','Net','Employer Contributions','LOP','Variable Pay','Approved OT Hours','OT Amount']);
 foreach($rows as $r){$s=json_decode($r['snapshot'],true);$e=$s['employee']??[];$currency=json_decode($r['settings_snapshot'],true)['payroll']['currency'];$ot=0;foreach($s['components']??[] as $c)if(($c['type']??'')==='Overtime')$ot+=$c['amount'];
  $row=[$r['month'],$e['employee_code']??$r['employee_code'],$e['name']??$r['name'],$e['department_name']??'',$r['status'],$currency,pay_amount((int)$r['gross']),pay_amount((int)$r['deductions']),pay_amount((int)$r['net']),pay_amount((int)$r['employer_cost']),pay_amount((int)($s['lop']??0)),pay_amount((int)($s['variable']??0)),$s['ot_hours']??0,pay_amount($ot)];
  fputcsv($out,array_map(fn($v)=>is_string($v)&&preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v,$row));
 }fclose($out);exit;
}
