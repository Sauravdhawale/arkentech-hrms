<?php
function pay_hidden(string $key,$value):void {if(is_array($value)){foreach($value as $k=>$v)pay_hidden($key.'['.$k.']',$v);return;}echo '<input type="hidden" name="'.h($key).'" value="'.h((string)$value).'">';}
function pay_form(string $action,array $hidden=[]):void {echo '<form method="post">';ftoken();pay_hidden('action',$action);foreach($hidden as $k=>$v)pay_hidden($k,$v);}
function pay_select(string $key,string $label,$value,array $options):void {echo '<label>'.h($label).'<select name="'.h($key).'">';foreach($options as $k=>$v)echo '<option value="'.h((string)$k).'" '.((string)$value===(string)$k?'selected':'').'>'.h($v).'</option>';echo '</select></label>';}
function pay_check(string $key,string $label,array $v):void {echo '<label class="pay-check"><input type="checkbox" name="'.h($key).'" value="1" '.(!empty($v[$key])?'checked':'').'>'.h($label).'</label>';}
function pay_text(string $key,string $label,array $v,string $type='text',bool $required=false):void {field($key,$label,$v,$type,$required,2000);}
function pay_options(array $values):array{return array_combine($values,$values);}
function pay_breakup_table(array $parts):void {echo '<div class="pay-table"><table><thead><tr><th>Component</th><th>Type</th><th>Monthly</th><th>Annual</th></tr></thead><tbody>';foreach($parts as $c)echo '<tr><td>'.h($c['name']??$c['code']).'</td><td>'.h($c['category']).'</td><td class="pay-money">'.pay_amount($c['amount']).'</td><td class="pay-money">'.pay_amount($c['amount']*12).'</td></tr>';echo '</tbody></table></div>';}
if(!pay_ready($pdo)):?>
<section class="card pay-card"><h2>Enable Payroll</h2><p>Add salary revisions and payroll snapshots to the existing workspace. Existing salaries and published payslips stay available.</p><?php if($user['role']==='super_admin'){pay_form('pay_install');echo '<button class="button primary">Install Payroll upgrade</button></form>';}else echo '<p>Ask your Super Admin to install the Payroll upgrade.</p>';?></section>
<?php return;endif;
$configModules=['salary_components'=>'pay_component','salary_structures'=>'pay_structure','statutory_settings'=>'pay_statutory','payroll_settings'=>'pay_settings','payslip_settings'=>'pay_payslip'];
if(isset($configModules[$page])){require __DIR__.'/settings-view.php';return;}
$employeeOptions=[''=>'All employees'];foreach($pdo->query('SELECT e.user_id,e.employee_code,u.name FROM employees e JOIN users u ON u.id=e.user_id ORDER BY u.name') as $e)$employeeOptions[$e['user_id']]=$e['name'].' · '.$e['employee_code'];
$departmentOptions=[''=>'All departments'];foreach($pdo->query('SELECT id,name FROM departments ORDER BY name') as $d)$departmentOptions[$d['id']]=$d['name'];
if($page==='employee_salary'){require __DIR__.'/salary-view.php';return;}
if($page==='payroll_processing'&&isset($_GET['run'])){require __DIR__.'/run-view.php';return;}
$month=(string)($_GET['month']??date('Y-m'));try{pay_month($month);}catch(InvalidArgumentException $e){$month=date('Y-m');}
?>
<form method="get" class="card pay-card pay-toolbar"><?php pay_hidden('page',$page);pay_text('month','Payroll month',['month'=>$month],'month',true);if(in_array($page,['payroll_reports','payroll_payslips'],true)){pay_select('employee_id','Employee',$_GET['employee_id']??'',$employeeOptions);pay_select('department_id','Department',$_GET['department_id']??'',$departmentOptions);}?>
<button class="button primary">Apply filters</button><?php if($page==='payroll_reports'):?><button class="button" name="export" value="csv">Download CSV</button><button type="button" class="button" onclick="window.print()">Print / Save PDF</button><?php endif;?></form>
<?php
$q=$pdo->prepare('SELECT r.*,COUNT(e.id) employees,COALESCE(SUM(e.gross),0) gross,COALESCE(SUM(e.deductions),0) deductions,COALESCE(SUM(e.net),0) net,COALESCE(SUM(e.published_at IS NOT NULL),0) published,COALESCE(SUM(e.exceptions<>""),0) exceptions FROM hr_payroll_runs r LEFT JOIN hr_payroll_entries e ON e.run_id=r.id WHERE r.month=? GROUP BY r.id');$q->execute([$month]);$runs=$q->fetchAll(PDO::FETCH_ASSOC);
if($page==='payroll_dashboard'){
 $r=$runs[0]??[];$stats=['Employees in payroll'=>$r['employees']??0,'Gross payroll'=>pay_amount((int)($r['gross']??0)),'Deductions'=>pay_amount((int)($r['deductions']??0)),'Net payroll'=>pay_amount((int)($r['net']??0)),'Exceptions'=>$r['exceptions']??0,'Payslips generated'=>$r['published']??0];
 echo '<div class="pay-grid">';foreach($stats as $label=>$value)echo '<section class="card pay-card"><span>'.h($label).'</span><strong class="pay-stat">'.h((string)$value).'</strong></section>';echo '</div>';
 $q=$pdo->prepare("SELECT u.name,e.employee_code FROM employees e JOIN users u ON u.id=e.user_id WHERE e.deleted_at IS NULL AND e.employment_status='Active' AND u.active=1 AND NOT EXISTS(SELECT 1 FROM hr_salary_assignments s WHERE s.employee_id=e.user_id AND s.effective_from<=? AND (s.effective_to IS NULL OR s.effective_to>=?)) ORDER BY u.name");$q->execute([$month.'-01',$month.'-01']);
 echo '<details class="card pay-card"><summary>Employees missing salary at month start</summary><ul>';foreach($q as $e)echo '<li>'.h($e['name'].' · '.$e['employee_code']).'</li>';echo '</ul></details>';
}
if($page==='payroll_processing'&&can($pdo,$user,'payroll.process.manage')){echo '<section class="card pay-card"><h2>Create monthly payroll</h2>';pay_form('pay_create');pay_text('month','Month',['month'=>$month],'month',true);pay_select('employee_id','Employees for this run','',$employeeOptions);echo '<p class="pay-muted">The run stores this month’s settings. Configure salary, shifts and attendance before calculating. Finalization is available after the month closes.</p><button class="button primary">Create draft</button></form></section>';}
if(in_array($page,['payroll_dashboard','payroll_processing','payroll_history'],true)){
 echo '<section class="card pay-card"><h2>Payroll runs</h2><div class="pay-table"><table><thead><tr><th>Month</th><th>Status</th><th>Employees</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Exceptions</th><th></th></tr></thead><tbody>';
 foreach($runs as $r){echo '<tr><td>'.h($r['month']).'</td><td>'.h($r['status']).'</td><td>'.$r['employees'].'</td><td>'.pay_amount((int)$r['gross']).'</td><td>'.pay_amount((int)$r['deductions']).'</td><td>'.pay_amount((int)$r['net']).'</td><td>'.$r['exceptions'].'</td><td>';if(can($pdo,$user,'payroll.process.view'))echo '<a href="?page=payroll_processing&run='.$r['id'].'">Open run</a>';echo '</td></tr>';}
 if(!$runs)echo '<tr><td colspan="8">No payroll run for this month.</td></tr>';echo '</tbody></table></div></section>';
 if($user['role']==='super_admin')echo '<p><a href="?page=payroll">Previous payroll records and payslips</a> · <a href="?page=salary">Previous salary records</a></p>';
}
if(in_array($page,['payroll_reports','payroll_payslips'],true)){
 $rows=pay_report_rows($pdo,array_merge($_GET,['month'=>$month]));echo '<section class="card pay-card"><h2>'.($page==='payroll_reports'?'Payroll register':'Published payslips').'</h2><p class="pay-muted">Amounts come from stored payroll calculations. Draft calculations are provisional.</p><div class="pay-table"><table><thead><tr><th>Employee</th><th>Status</th><th>Gross</th><th>Deductions</th><th>Net</th><th>LOP</th><th>Variable</th><th>Approved OT</th><th>Employer contribution</th><th>Payslip</th></tr></thead><tbody>';
 $totals=['gross'=>0,'deductions'=>0,'net'=>0];$count=0;
 foreach($rows as $r){if($page==='payroll_payslips'&&!$r['published_at'])continue;$count++;$s=json_decode($r['snapshot'],true);$name=$s['employee']['name']??$r['name'];$code=$s['employee']['employee_code']??$r['employee_code'];echo '<tr><td>'.h($name).' <small>'.h($code).'</small></td><td>'.h($r['status']).'</td>';foreach($totals as $key=>$v){$totals[$key]+=(int)$r[$key];echo '<td class="pay-money">'.pay_amount((int)$r[$key]).'</td>';}echo '<td>'.pay_amount((int)($s['lop']??0)).'</td><td>'.pay_amount((int)($s['variable']??0)).'</td><td>'.h((string)($s['ot_hours']??0)).' h</td><td>'.pay_amount((int)$r['employer_cost']).'</td><td>';if($r['published_at']&&can($pdo,$user,'payslip.view'))echo '<a href="payroll-slip.php?entry='.$r['id'].'">View / PDF</a>';else echo 'Not published';echo '</td></tr>';}
 if(!$count)echo '<tr><td colspan="10">No matching records.</td></tr>';echo '</tbody><tfoot><tr><th colspan="2">Total</th>';foreach($totals as $v)echo '<th class="pay-money">'.pay_amount($v).'</th>';echo '<th colspan="5"></th></tr></tfoot></table></div></section>';
 if($page==='payroll_reports'){
  $summary=[];foreach($rows as $r){$s=json_decode($r['snapshot'],true);foreach($s['components']??[] as $c){$key=$c['category'].' · '.($c['name']??$c['code']);$summary[$key]=($summary[$key]??0)+$c['amount'];}}
  echo '<section class="card pay-card"><h2>Component and statutory totals</h2><div class="pay-table"><table><thead><tr><th>Component</th><th>Amount</th></tr></thead><tbody>';foreach($summary as $key=>$value)echo '<tr><td>'.h($key).'</td><td>'.pay_amount($value).'</td></tr>';echo '</tbody></table></div></section>';
 }
}
