<?php
require_once dirname(__DIR__).'/foundation/employees.php';
require_once dirname(__DIR__).'/core-hr/service.php';
require_once __DIR__.'/calculation.php';
function pay_ready(PDO $db):bool {try{return (bool)$db->query("SELECT name FROM hr_migrations WHERE name='007-payroll'")->fetchColumn();}catch(Throwable $e){return false;}}
function pay_allow(PDO $db,array $actor,string $permission):void{if(!can($db,$actor,$permission))throw new InvalidArgumentException('Payroll permission required: '.$permission);}
function pay_install(PDO $db,array $actor):void {
 if($actor['role']!=='super_admin')throw new InvalidArgumentException('Super Admin required.');
 if(!chr_ready($db)||!att_ready($db))throw new InvalidArgumentException('Enable the existing Core HR and Attendance upgrades first.');
 if(!$db->query("SELECT GET_LOCK('peopleflow_payroll_migration',10)")->fetchColumn())throw new InvalidArgumentException('Payroll setup is already running.');
 try{
  foreach(explode(';',file_get_contents(dirname(__DIR__,2).'/database/007-payroll.sql')) as $sql)if(trim($sql)!=='')$db->exec($sql);
  foreach(['payroll.view','payroll.salary.view','payroll.salary.manage','payroll.process.view','payroll.process.manage','payroll.process.approve','payroll.process.finalize','payroll.process.pay','payslip.view','payslip.generate','payroll.reports','payroll.settings.view','payroll.settings.manage'] as $code)$db->prepare('INSERT IGNORE INTO permissions(code,label) VALUES(?,?)')->execute([$code,ucwords(str_replace('.',' ',$code))]);
  $db->exec("INSERT IGNORE INTO hr_migrations(name) VALUES('007-payroll')");faudit($db,$actor,'payroll.installed');
 }finally{$db->query("SELECT RELEASE_LOCK('peopleflow_payroll_migration')");}
}
function pay_enum(array $in,string $key,array $allowed,string $default=''):string{$v=(string)($in[$key]??$default);if(!in_array($v,$allowed,true))throw new InvalidArgumentException('Choose a valid '.str_replace('_',' ',$key));return $v;}
function pay_code(string $s):string{$s=strtoupper(trim($s));if(!preg_match('/^[A-Z][A-Z0-9_]{0,39}$/D',$s)||$s==='GROSS')throw new InvalidArgumentException('Use a unique component code (letters, numbers, underscore); GROSS is reserved.');return $s;}
function pay_json($value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
function pay_current(PDO $db,string $module,string $day,?string $code=null):array {
 $selected=[];foreach(chr_rows($db,$module) as $r){$v=$r['values'];$key=$v['code']??'DEFAULT';if($code!==null&&$key!==$code)continue;
  if(($v['effective_from']??'9999')>$day||(!empty($v['effective_to'])&&$v['effective_to']<$day)||isset($selected[$key]))continue;
  $selected[$key]=$r;
 }return array_filter($selected,fn($r)=>$r['status']==='Active');
}
function pay_settings(PDO $db,string $day):array {
 $rows=pay_current($db,'pay_settings',$day);if(!$rows)throw new InvalidArgumentException('Save effective Payroll Settings before processing.');
 return array_values($rows)[0]['values'];
}
function pay_setting_defaults():array{return ['cycle'=>'Monthly','closing_day'=>1,'proration'=>'Calendar Days','lop_basis'=>'GROSS','lop_days'=>'Absent + unpaid leave','ot_enabled'=>false,'ot_method'=>'Fixed Hourly Rate','ot_rate'=>'0','ot_basis'=>'GROSS','hours_per_day'=>8,'variable_enabled'=>false,'rounding'=>'Paise','approval_required'=>true,'auto_payslip'=>false,'currency'=>'INR','currency_position'=>'Before'];}
function pay_slip_defaults():array{return ['show_logo'=>true,'show_address'=>true,'show_attendance'=>true,'show_bank'=>false,'show_pan'=>false,'mask_sensitive'=>true,'footer'=>'','signatory'=>'','number_format'=>'PAY-{YYYY}{MM}-{ID}'];}
function pay_save_config(PDO $db,array $actor,array $in):int {
 pay_allow($db,$actor,'payroll.settings.manage');$module=pay_enum($in,'module',['pay_component','pay_structure','pay_statutory','pay_settings','pay_payslip']);
 $from=chr_date((string)($in['effective_from']??''));$to=fdate($in,'effective_to');if($to&&$to<$from)throw new InvalidArgumentException('Effective end must follow start.');
 $name=ftext($in,'title',190,true);$code=in_array($module,['pay_settings','pay_payslip'],true)?'DEFAULT':pay_code((string)($in['code']??''));
 $v=['code'=>$code,'effective_from'=>$from,'effective_to'=>$to,'description'=>ftext($in,'description',2000)];$status=!empty($in['active'])?'Active':'Inactive';
 if($module==='pay_component'){
  $v+=['name'=>$name,'category'=>pay_enum($in,'category',['Earning','Deduction','Employer']),'calculation'=>pay_enum($in,'calculation',['Fixed','Percentage','Formula','Manual','Balance']),'section'=>pay_enum($in,'section',['Fixed','Variable'],'Fixed'),'basis'=>strtoupper(ftext($in,'basis',500))?:'GROSS','value'=>pay_amount(pay_money($in['value']??'0')),'formula'=>strtoupper(ftext($in,'formula',500)),'taxable'=>!empty($in['taxable']),'include_gross'=>!empty($in['include_gross']),'include_ctc'=>!empty($in['include_ctc']),'visible'=>!empty($in['visible'])];
  if($v['category']!=='Earning'&&$v['include_gross'])throw new InvalidArgumentException('Only earnings can be included in gross.');
  if($v['section']==='Variable'&&($v['category']!=='Earning'||$v['calculation']!=='Manual'))throw new InvalidArgumentException('Variable pay components must be manual earnings.');
  if($v['calculation']==='Balance'&&($v['category']!=='Earning'||!$v['include_gross']||$v['section']==='Variable'))throw new InvalidArgumentException('Balance must be a fixed-section gross earning.');
  if($v['calculation']==='Percentage')pay_number($v['value'],1000);
  foreach(($v['calculation']==='Formula'?[$v['formula']]:($v['calculation']==='Percentage'?[$v['basis']]:[])) as $expression){try{pay_expression($expression,['GROSS'=>1]);}catch(PayrollDependency $e){}}
 }elseif($module==='pay_structure'){
  $ids=array_values(array_unique(array_map('intval',(array)($in['component_ids']??[]))));if(!$ids||count($ids)>60)throw new InvalidArgumentException('Choose 1–60 components.');
  $parts=[];$codes=[];$balance=0;foreach($ids as $id){$r=chr_record($db,'pay_component',$id);if(!$r||$r['status']!=='Active'||$r['values']['effective_from']>$from||(!empty($r['values']['effective_to'])&&$r['values']['effective_to']<$from))throw new InvalidArgumentException('Choose components effective on the structure start date.');$c=$r['values']+['record_id'=>$id,'record_version'=>$r['version']];if(isset($codes[$c['code']]))throw new InvalidArgumentException('Choose one version of each component.');$codes[$c['code']]=true;$balance+=$c['calculation']==='Balance'?1:0;$parts[]=$c;}
  if($balance>1)throw new InvalidArgumentException('Choose at most one balancing component.');
  $v+=['components'=>$parts,'variable_enabled'=>!empty($in['variable_enabled']),'variable_cap'=>pay_number($in['variable_cap']??0,100)];
 }elseif($module==='pay_statutory'){
  $v+=['name'=>$name,'scheme'=>pay_enum($in,'scheme',['PF','ESI','TDS','Professional Tax','Other']),'basis'=>strtoupper(ftext($in,'basis',40,true)),'method'=>pay_enum($in,'method',['Percentage','Fixed']),'employee_value'=>pay_number($in['employee_value']??0),'employer_value'=>pay_number($in['employer_value']??0),'wage_cap'=>pay_money($in['wage_cap']??'0'),'eligibility_limit'=>pay_money($in['eligibility_limit']??'0')];
  if($v['method']==='Percentage'&&max($v['employee_value'],$v['employer_value'])>100)throw new InvalidArgumentException('Contribution percentages cannot exceed 100.');
 }elseif($module==='pay_settings'){
  $v+=['cycle'=>'Monthly','closing_day'=>(int)pay_number($in['closing_day']??1,28),'proration'=>pay_enum($in,'proration',['Calendar Days','Working Days','Fixed 30 Days']),'lop_basis'=>strtoupper(ftext($in,'lop_basis',40,true)),'lop_days'=>pay_enum($in,'lop_days',['Absent + unpaid leave','Unpaid leave only']),'ot_enabled'=>!empty($in['ot_enabled']),'ot_method'=>pay_enum($in,'ot_method',['Fixed Hourly Rate','Basic Salary Based','Gross Salary Based','Custom Employee Rate']),'ot_rate'=>pay_amount(pay_money($in['ot_rate']??'0')),'ot_basis'=>strtoupper(ftext($in,'ot_basis',40))?:'GROSS','hours_per_day'=>pay_number($in['hours_per_day']??8,24),'variable_enabled'=>!empty($in['variable_enabled']),'rounding'=>pay_enum($in,'rounding',['Paise','Nearest Rupee']),'approval_required'=>!empty($in['approval_required']),'auto_payslip'=>!empty($in['auto_payslip']),'currency'=>strtoupper(ftext($in,'currency',3,true)),'currency_position'=>pay_enum($in,'currency_position',['Before','After'])];
  if(!preg_match('/^[A-Z]{3}$/D',$v['currency'])||$v['closing_day']<1||$v['hours_per_day']<=0)throw new InvalidArgumentException('Check currency, closing day and hours per day.');
 }else{
  foreach(['show_logo','show_address','show_attendance','show_bank','show_pan','mask_sensitive'] as $key)$v[$key]=!empty($in[$key]);
  $v+=['footer'=>ftext($in,'footer',1000),'signatory'=>ftext($in,'signatory',190),'number_format'=>ftext($in,'number_format',60,true)];
  if(!str_contains($v['number_format'],'{ID}')||!preg_match('/^[A-Za-z0-9{} _\/-]+$/D',$v['number_format']))throw new InvalidArgumentException('Payslip number format must contain {ID}; use letters, digits, spaces, / or -.');
 }
 $db->beginTransaction();try{
  chr_lock($db);$latest=null;foreach(chr_rows($db,$module) as $r)if(($r['values']['code']??'')===$code){$latest=$r;break;}
  if((int)($in['previous_id']??0)!==(int)($latest['id']??0))throw new InvalidArgumentException('Configuration changed. Select the latest revision and retry.');
  if($latest&&$from<=$latest['values']['effective_from'])throw new InvalidArgumentException('A revision must start after the previous version.');
  if($latest&&(!$latest['values']['effective_to']||$latest['values']['effective_to']>=$from)){$old=$latest['values'];$old['effective_to']=(new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');$db->prepare('UPDATE hr_records SET data=?,version=version+1 WHERE id=?')->execute([pay_json($old),$latest['id']]);}
  $db->prepare('INSERT INTO hr_records(module,title,status,data,created_by) VALUES(?,?,?,?,?)')->execute([$module,$name,$status,pay_json($v),$actor['id']]);$id=(int)$db->lastInsertId();faudit($db,$actor,'payroll.config.created',$id);$db->commit();return $id;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function pay_assignment_preview(PDO $db,array $in):array {
 $employee=foundation_employee($db,(int)($in['employee_id']??0));if(!$employee||!$employee['active']||$employee['employment_status']!=='Active')throw new InvalidArgumentException('Choose an active employee.');
 $from=chr_date((string)($in['effective_from']??''));$to=fdate($in,'effective_to');if($to&&$to<$from)throw new InvalidArgumentException('Invalid salary effective period.');
 $s=chr_record($db,'pay_structure',(int)($in['structure_id']??0));if(!$s||$s['status']!=='Active'||$s['values']['effective_from']>$from||(!empty($s['values']['effective_to'])&&$s['values']['effective_to']<$from))throw new InvalidArgumentException('Choose an effective salary structure.');
 $s['values']['statutory']=array_map(fn($r)=>$r['values']+['record_id'=>$r['id'],'record_version'=>$r['version']],array_values(pay_current($db,'pay_statutory',$from)));
 $manual=(array)($in['manual']??[]);$basis=pay_enum($in,'salary_basis',['Monthly Gross','Annual CTC'],'Monthly Gross');$amount=pay_money($in['salary_amount']??'0');if(!$amount)throw new InvalidArgumentException('Salary must be greater than zero.');
 $breakup=$basis==='Monthly Gross'?pay_breakup($s['values'],$amount,$manual):pay_from_ctc($s['values'],$amount,$manual);
 return ['employee'=>array_intersect_key($employee,array_flip(['user_id','name','employee_code','department_name','designation_name','joining_date'])),'structure_id'=>(int)$s['id'],'structure_name'=>$s['title'],'structure_version'=>(int)$s['version'],'effective_from'=>$from,'effective_to'=>$to,'structure'=>$s['values'],'breakup'=>$breakup,'manual'=>$manual,'ot_rate'=>pay_money(($in['employee_ot_rate']??'')?:'0'),'bank_last4'=>ftext($in,'bank_last4',4),'pan_last4'=>ftext($in,'pan_last4',4)];
}
function pay_assign(PDO $db,array $actor,array $in):int {
 pay_allow($db,$actor,'payroll.salary.manage');$reason=ftext($in,'reason',1000,true);
 if(empty($in['confirm']))throw new InvalidArgumentException('Review and confirm the salary preview.');
 $db->beginTransaction();try{chr_lock($db);$p=pay_assignment_preview($db,$in);$q=$db->prepare('SELECT * FROM hr_salary_assignments WHERE employee_id=? ORDER BY effective_from DESC LIMIT 1 FOR UPDATE');$q->execute([$p['employee']['user_id']]);$old=$q->fetch(PDO::FETCH_ASSOC);
  if((int)($in['previous_id']??0)!==(int)($old['id']??0))throw new InvalidArgumentException('Salary changed. Refresh the preview.');
  if($old&&$p['effective_from']<=$old['effective_from'])throw new InvalidArgumentException('A revision must start after the latest salary assignment.');
  // Preview fingerprint binds confirmation to the exact salary structure and computed amounts.
  if(!hash_equals((string)($in['preview_hash']??''),hash('sha256',pay_json($p))))throw new InvalidArgumentException('Salary preview changed. Preview again.');
  if($old&&(!$old['effective_to']||$old['effective_to']>=$p['effective_from']))$db->prepare('UPDATE hr_salary_assignments SET effective_to=? WHERE id=?')->execute([(new DateTimeImmutable($p['effective_from']))->modify('-1 day')->format('Y-m-d'),$old['id']]);
  $db->prepare('INSERT INTO hr_salary_assignments(employee_id,structure_id,effective_from,effective_to,monthly_gross,annual_ctc,snapshot,reason,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$p['employee']['user_id'],$p['structure_id'],$p['effective_from'],$p['effective_to'],$p['breakup']['gross'],$p['breakup']['annual_ctc'],pay_json($p),$reason,ftext($in,'notes',3000),$actor['id']]);$id=(int)$db->lastInsertId();faudit($db,$actor,'payroll.salary.assigned',$id);$db->commit();return $id;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
