<?php
echo '<link rel="stylesheet" href="assets/payroll-create.css?v=1">';
echo '<script src="assets/payroll-ui.js?v=2" defer></script>';
function pay_hidden(string $key,$value):void {if(is_array($value)){foreach($value as $k=>$v)pay_hidden($key.'['.$k.']',$v);return;}echo '<input type="hidden" name="'.h($key).'" value="'.h((string)$value).'">';}
function pay_form(string $action,array $hidden=[]):void {echo '<form method="post">';ftoken();pay_hidden('action',$action);foreach($hidden as $k=>$v)pay_hidden($k,$v);}
function pay_select(string $key,string $label,$value,array $options):void {echo '<label>'.h($label).'<select name="'.h($key).'">';foreach($options as $k=>$v)echo '<option value="'.h((string)$k).'" '.((string)$value===(string)$k?'selected':'').'>'.h($v).'</option>';echo '</select></label>';}
function pay_check(string $key,string $label,array $v):void {echo '<label class="pay-check"><input type="checkbox" name="'.h($key).'" value="1" '.(!empty($v[$key])?'checked':'').'>'.h($label).'</label>';}
function pay_text(string $key,string $label,array $v,string $type='text',bool $required=false):void {echo '<label><span>'.h($label).($required?' <span aria-label="required">*</span>':'').'</span><input type="'.h($type).'" name="'.h($key).'" value="'.h((string)($v[$key]??'')).'" maxlength="2000" '.($required?'required':'').'></label>';}
function pay_options(array $values):array{return array_combine($values,$values);}
function pay_breakup_table(array $parts,bool $annual=true):void {
 echo '<div class="pay-table"><table><thead><tr><th>Component</th><th>Type / section</th><th>Calculation</th><th>'.($annual?'Monthly':'Amount').'</th>'.($annual?'<th>Annual</th>':'').'</tr></thead><tbody>';
 foreach($parts as $c){$method=$c['calculation']??($c['type']??'Calculated');$rule=$method==='Percentage'?($c['value'].'% of '.$c['basis']):($method==='Formula'?($c['formula']??''):($method==='Balance'?'Remaining gross':$method));echo '<tr><td>'.h($c['name']??$c['code']).'</td><td>'.h($c['category'].' / '.($c['section']??'Fixed')).'</td><td>'.h($rule).'</td><td class="pay-money">'.pay_amount($c['amount']).'</td>'.($annual?'<td class="pay-money">'.pay_amount($c['amount']*12).'</td>':'').'</tr>';}
 echo '</tbody></table></div>';
}

echo '<link rel="stylesheet" href="assets/payroll-four.css?v=4"><div class="pay-workspace">';
if(!pay_workspace_ready($pdo)){
 echo '<section class="card pay-onboarding"><span class="eyebrow">PAYROLL</span><h2>A simpler start for your payroll</h2><p>Set up salary rules, assign employee salaries and generate payslips from processed attendance. No old payroll data is restored.</p>';
 if($user['role']==='super_admin'){pay_form('pay_initialize');echo '<button class="button primary">Set up Payroll</button></form>';}
 else echo '<p>Ask your Super Admin to complete setup.</p>';
 echo '</section></div>';return;
}
if(in_array($page,['salary_components','payslip_settings'],true)){require __DIR__.'/four-configuration.php';echo '</div>';return;}
$month=(string)($_GET['month']??date('Y-m'));try{pay_month($month);}catch(InvalidArgumentException $e){$month=date('Y-m');}
$q=$pdo->prepare('SELECT * FROM hr_payroll_runs WHERE month=?');$q->execute([$month]);$run=$q->fetch(PDO::FETCH_ASSOC);
$rows=pay_report_rows($pdo,['month'=>$month]);
if($page==='payroll_dashboard')require __DIR__.'/four-dashboard.php';else require __DIR__.'/four-payslips.php';
echo '</div>';


