<?php
$salaryEmployee=(int)($_GET['employee_id']??0);$structures=[''=>'Choose salary structure'];foreach(chr_rows($pdo,'pay_structure') as $r)if($r['status']==='Active')$structures[$r['id']]=$r['title'].' · '.$r['values']['effective_from'];
$designationOptions=[''=>'All designations'];foreach($pdo->query('SELECT id,name FROM designations ORDER BY name') as $designation)$designationOptions[$designation['id']]=$designation['name'];
?>
<?php if($salaryEmployee&&isset($employeeOptions[$salaryEmployee])):?>
<section class="card pay-card"><div class="pay-panel-heading"><div><h2><?=h($employeeOptions[$salaryEmployee])?></h2><p class="pay-muted">1. Set salary once · 2. Add dated increments · 3. Generate monthly payslips using saved salary and attendance.</p></div><div class="pay-actions">
<?php if(can($pdo,$user,'payroll.salary.manage')&&can($pdo,$user,'employees.edit')):?><a class="button primary" href="?page=employee-edit&amp;id=<?=$salaryEmployee?>#profile-salary">Set / increase salary</a><?php endif;?>
<?php if(can($pdo,$user,'payroll.process.manage')):?><a class="button" href="?page=payroll_payslips&amp;employee_id=<?=$salaryEmployee?>&amp;create=1">Generate payslip</a><?php endif;?>
</div></div><p class="pay-muted">An increment starts on its effective date. Previous salary history and published payslips are preserved.</p></section>
<?php endif;?>
<p class="pay-muted salary-intro">Set an employee’s salary here. To issue a monthly payslip, use Generate payroll after saving the salary.</p>
<form method="get" class="card pay-card pay-toolbar"><?php pay_hidden('page','employee_salary');pay_select('employee_id','Employee',$salaryEmployee,$employeeOptions);pay_select('department_id','Department',$_GET['department_id']??'',$departmentOptions);pay_select('designation_id','Designation',$_GET['designation_id']??'',$designationOptions);pay_select('structure_id','Salary structure',$_GET['structure_id']??'',[''=>'All structures']+array_diff_key($structures,[''=>true]));pay_select('salary_status','Status',$_GET['salary_status']??'',[''=>'All periods','Current'=>'Current','Future'=>'Future','Ended'=>'Ended']);?><button class="button primary">View salary history</button></form>
<?php if(can($pdo,$user,'payroll.salary.manage')):
$input=['employee_id'=>$salaryEmployee,'effective_from'=>date('Y-m-01'),'salary_basis'=>'Monthly Gross'];$preview=$_SESSION['pay_preview']??null;if($preview&&$preview['actor']===$user['id'])$input=$preview['input'];
?>
<details class="card pay-card" <?=($preview)?'open':''?>><summary>Assign or revise salary</summary><?php pay_form('pay_preview');?><div class="pay-grid"><?php pay_select('employee_id','Employee',$input['employee_id']??'',$employeeOptions);pay_select('structure_id','Salary structure',$input['structure_id']??'',$structures);choose('salary_basis','Salary input',$input,['Monthly Gross','Annual CTC']);pay_text('salary_amount','Amount',$input,'text',true);pay_text('effective_from','Effective from',$input,'date',true);pay_text('effective_to','Effective to (optional)',$input,'date');pay_text('employee_ot_rate','Custom employee hourly OT rate',$input);pay_text('bank_last4','Bank account last 4 characters',$input);pay_text('pan_last4','PAN last 4 characters',$input);?></div>
<details><summary>Manual fixed component amounts</summary><div class="pay-grid"><?php $shown=[];foreach(chr_rows($pdo,'pay_component') as $c){$v=$c['values'];if($v['calculation']!=='Manual'||$v['section']==='Variable'||isset($shown[$v['code']]))continue;$shown[$v['code']]=true;pay_text('manual['.$v['code'].']',$c['title'].' ('.$v['code'].')',['manual['.$v['code'].']'=>$input['manual'][$v['code']]??'0']);}?></div></details>
<?php pay_text('reason','Reason for assignment / revision',$input,'text',true);pay_text('notes','Notes',$input);?><div class="pay-actions"><button class="button primary">Preview salary breakup</button></div></form></details>
<?php if($preview&&$preview['actor']===$user['id']):$p=$preview['preview'];?><section class="card pay-card"><h2>Salary preview · <?=h($p['employee']['name'])?></h2><p><?=h($p['structure_name'].' · Effective '.$p['effective_from'])?> · <?=h(($p['employee']['department_name']??'—').' / '.($p['employee']['designation_name']??'—'))?></p><?php pay_breakup_table($p['breakup']['components']);?><p><strong>Monthly gross: <?=pay_amount($p['breakup']['gross'])?> · Monthly net: <?=pay_amount($p['breakup']['net'])?> · Annual CTC: <?=pay_amount($p['breakup']['annual_ctc'])?></strong></p><?php pay_form('pay_assign');foreach($preview['input'] as $key=>$value)if(!in_array($key,['action','csrf','confirm'],true))pay_hidden($key,$value);pay_check('confirm','I reviewed this salary breakup and effective period',[]);?><div class="pay-actions"><button class="button primary">Save salary revision</button></div></form></section><?php endif;endif;?>
<?php
$sql='SELECT s.*,u.name,e.employee_code,d.name department_name,j.name designation_name FROM hr_salary_assignments s JOIN users u ON u.id=s.employee_id JOIN employees e ON e.user_id=s.employee_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN designations j ON j.id=e.designation_id WHERE 1=1';$args=[];if($salaryEmployee){$sql.=' AND s.employee_id=?';$args[]=$salaryEmployee;}
foreach(['department_id'=>'e.department_id','designation_id'=>'e.designation_id','structure_id'=>'s.structure_id','assignment'=>'s.id'] as $filter=>$column)if(!empty($_GET[$filter])){$sql.=' AND '.$column.'=?';$args[]=(int)$_GET[$filter];}
$status=$_GET['salary_status']??'';if($status==='Current'){$sql.=' AND s.effective_from<=? AND (s.effective_to IS NULL OR s.effective_to>=?)';$args[]=date('Y-m-d');$args[]=date('Y-m-d');}elseif($status==='Future'){$sql.=' AND s.effective_from>?';$args[]=date('Y-m-d');}elseif($status==='Ended'){$sql.=' AND s.effective_to<?';$args[]=date('Y-m-d');}$q=$pdo->prepare($sql.' ORDER BY s.effective_from DESC,s.id DESC LIMIT 500');$q->execute($args);
?>
<?php $salaryRows=$q->fetchAll(PDO::FETCH_ASSOC);$salaryDetail=!empty($_GET['assignment']); ?>
<?php if($salaryDetail): ?>
<div class="pay-actions"><a class="button" href="?page=employee_salary">← Salary history</a><?php if($salaryRows):?><button type="button" class="button primary" onclick="window.print()">Print / Save salary breakup</button><?php endif;?></div>
<?php if(!$salaryRows):?><section class="card pay-card"><h2>Salary revision not found</h2><p>Return to salary history and select a saved revision.</p></section><?php endif;?>
<?php foreach($salaryRows as $r):$s=json_decode($r['snapshot'],true);$parts=$s['breakup']['components']??[];$salaryState=$r['effective_from']>date('Y-m-d')?'Future':($r['effective_to']&&$r['effective_to']<date('Y-m-d')?'Ended':'Current'); ?>
<article class="card pay-card salary-detail">
 <header class="salary-detail-heading"><div><p class="salary-eyebrow"><?=h($company['name'])?> · Salary breakup</p><h2><?=h($r['name'])?></h2><p><?=h($r['employee_code'])?> · <?=h($r['department_name']??'No department')?> · <?=h($r['designation_name']??'No designation')?></p></div><span class="pay-badge <?=$salaryState==='Current'?'pay-badge-success':''?>"><?=h($salaryState)?></span></header>
 <div class="salary-summary">
  <div><span>Monthly gross salary</span><strong>₹<?=pay_amount((int)$r['monthly_gross'])?></strong><small>Before deductions</small></div>
  <div><span>Monthly net salary estimate</span><strong>₹<?=pay_amount((int)($s['breakup']['net']??0))?></strong><small>Based on this saved salary structure</small></div>
  <div><span>Annual CTC</span><strong>₹<?=pay_amount((int)$r['annual_ctc'])?></strong><small>Annual cost to company</small></div>
 </div>
 <dl class="salary-meta"><div><dt>Salary structure</dt><dd><?=h($s['structure_name'])?></dd></div><div><dt>Effective from</dt><dd><?=h(date('d M Y',strtotime($r['effective_from'])))?></dd></div><div><dt>Effective until</dt><dd><?=$r['effective_to']?h(date('d M Y',strtotime($r['effective_to']))):'Ongoing'?></dd></div></dl>
 <?php foreach(['Earning'=>'Earnings & allowances','Deduction'=>'Deductions','Employer'=>'Employer contributions'] as $category=>$label):$group=array_values(array_filter($parts,fn($c)=>($c['category']??'')===$category));if(!$group)continue;$total=0; ?>
 <section class="salary-component-group"><h3><?=h($label)?></h3><div class="salary-breakdown"><table><thead><tr><th>Salary component</th><th>Monthly (₹)</th><th>Annualized (₹)</th></tr></thead><tbody>
 <?php foreach($group as $c):$amount=(int)$c['amount'];$total+=$amount;?><tr><td><?=h($c['name']??$c['code'])?><?php if(($c['section']??'')==='Variable'):?><small>Variable component</small><?php endif;?></td><td><?=pay_amount($amount)?></td><td><?=pay_amount($amount*12)?></td></tr><?php endforeach;?>
 </tbody><tfoot><tr><th>Total <?=h(strtolower($label))?></th><td><?=pay_amount($total)?></td><td><?=pay_amount($total*12)?></td></tr></tfoot></table></div></section>
 <?php endforeach;?>
 <div class="salary-footnote"><strong>Salary structure only — not a monthly payslip.</strong><p>Actual monthly pay may change with attendance, approved adjustments and variable pay. Annualized amounts are monthly amounts × 12.</p><p><strong>Revision reason:</strong> <?=h($r['reason'])?></p></div>
</article>
<?php endforeach;?>
<?php else: ?>
<section class="card pay-card"><div class="pay-panel-heading"><div><h2>Salary history</h2><p class="pay-muted">Open a revision to see its full salary breakup. Latest 500 revisions.</p></div><span class="pay-count"><?=count($salaryRows)?> revisions</span></div>
<div class="pay-table pay-modern-table"><table><thead><tr><th>Employee</th><th>Salary structure</th><th>Effective period</th><th>Monthly gross (₹)</th><th>Status</th><th>Details</th></tr></thead><tbody>
<?php foreach($salaryRows as $r):$s=json_decode($r['snapshot'],true);$salaryState=$r['effective_from']>date('Y-m-d')?'Future':($r['effective_to']&&$r['effective_to']<date('Y-m-d')?'Ended':'Current'); ?>
<tr><td><strong><?=h($r['name'])?></strong><small><?=h($r['employee_code'].' · '.($r['department_name']??'No department'))?></small></td><td><?=h($s['structure_name'])?></td><td><?=h(date('d M Y',strtotime($r['effective_from'])))?><small>Until <?=$r['effective_to']?h(date('d M Y',strtotime($r['effective_to']))):'ongoing'?></small></td><td class="pay-money"><?=pay_amount((int)$r['monthly_gross'])?></td><td><span class="pay-badge <?=$salaryState==='Current'?'pay-badge-success':''?>"><?=h($salaryState)?></span></td><td><a class="button" href="?page=employee_salary&amp;assignment=<?=(int)$r['id']?>">View salary breakup</a></td></tr>
<?php endforeach;?>
<?php if(!$salaryRows):?><tr><td colspan="6">No salary revisions found. Use “Assign or revise salary” to add an employee’s salary.</td></tr><?php endif;?>
</tbody></table></div></section>
<?php endif;?>
