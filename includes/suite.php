<?php
if(!isset($user,$pdo)) {http_response_code(404);exit;}
require_once __DIR__.'/suite-definitions.php';
require_once __DIR__.'/employee-import.php';
$suite=suite_definitions();
$suiteReady=true;
try{$pdo->query('SELECT id,version FROM hr_records LIMIT 1');$pdo->query('SELECT id FROM hr_punches LIMIT 1');$pdo->query('SELECT id FROM hr_sync_events LIMIT 1');$pdo->query('SELECT id FROM hr_files LIMIT 1');$pdo->query('SELECT record_id FROM hr_acknowledgements LIMIT 1');}catch(Throwable $e){$suiteReady=false;}
function suite_rows(PDO $pdo,string $module,array $user,array $def): array {
 $sql='SELECT r.*,u.name employee_name FROM hr_records r LEFT JOIN users u ON u.id=r.employee_id WHERE r.module=?';$args=[$module];
 if($user['role']!=='super_admin') {
  if($def[2]==='admin')return [];
  if($def[2]==='own'){$sql.=' AND r.employee_id=?';$args[]=$user['id'];}
  if(in_array($module,['payroll','reviews','policies','holidays','leave_policy'],true))$sql.=" AND r.status='Published'";
 }
 $sql.=' ORDER BY r.id DESC LIMIT 1000';$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function suite_data(array $def,array $input): array {
 $out=[];
 foreach($def[5] as $key=>$f){$v=trim((string)($input[$key]??''));if($f[2] && $v==='')throw new InvalidArgumentException($f[0].' is required.');if(strlen($v)>10000)throw new InvalidArgumentException('Field exceeds 10,000 characters.');
  if($v!==''){
   if($f[1]==='number' && (!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>1000000000))throw new InvalidArgumentException($f[0].' must be a non-negative number.');
   if($f[1]==='email' && !filter_var($v,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email.');
   if($f[1]==='select' && !in_array($v,$f[3],true))throw new InvalidArgumentException('Invalid selection.');
   $formats=['date'=>'Y-m-d','month'=>'Y-m','time'=>'H:i','datetime-local'=>'Y-m-d\TH:i'];
   if(isset($formats[$f[1]])){$fmt=$formats[$f[1]];$d=DateTimeImmutable::createFromFormat('!'.$fmt,$v);if(!$d||$d->format($fmt)!==$v)throw new InvalidArgumentException('Invalid '.$f[0].'.');}
  }$out[$key]=$v;
 }
 if(!empty($out['from'])&&!empty($out['to'])&&$out['to']<$out['from'])throw new InvalidArgumentException('End date must follow start date.');
 return $out;
}
function suite_net(array $d): float {return round((float)$d['basic']+(float)$d['hra']+(float)$d['other']+(float)$d['overtime']+(float)$d['incentive']-(float)$d['deductions']-(float)$d['lwp']-(float)$d['advance'],2);}
function suite_csv(array $head,array $rows): void {
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="peopleflow-export.csv"');$f=fopen('php://output','w');
 foreach(array_merge([$head],$rows) as $row){$safe=array_map(function($v){$s=(string)$v;return preg_match('/^[=+@\-\t\r\n]/',$s)?"'".$s:$s;},$row);fputcsv($f,$safe);}fclose($f);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && str_starts_with((string)($_POST['action']??''),'suite_')) {
 csrf();
 try {
  $action=$_POST['action'];
  if($action==='suite_install'){
   if(!$admin)throw new InvalidArgumentException('Administrator access required.');
   foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/003-hr-suite.sql')) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
   install_login_columns($pdo);
   audit_action($pdo,(int)$user['id'],'suite.installed',0);
  }elseif(in_array($action,['suite_upload','suite_ack'],true)){
   if(!$suiteReady)throw new InvalidArgumentException('Install the HR modules first.');
   $id=(int)($_POST['id']??0);$pdo->beginTransaction();
   $q=$pdo->prepare('SELECT * FROM hr_records WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
   if(!$r)throw new InvalidArgumentException('Record unavailable.');
   if($action==='suite_ack'){
    if($page!=='policies'||$r['module']!=='policies'||$r['status']!=='Published'||(int)($_POST['version']??0)!==(int)$r['version'])throw new InvalidArgumentException('Open the current published policy first.');
    $pdo->prepare('INSERT IGNORE INTO hr_acknowledgements(record_id,user_id,version) VALUES(?,?,?)')->execute([$id,$user['id'],$r['version']]);
   }else{
    if($page!=='documents'||$r['module']!=='documents'||(!$admin&&(int)$r['employee_id']!==(int)$user['id']))throw new InvalidArgumentException('Document unavailable.');
    $file=$_FILES['document']??[];if(($file['error']??1)!==UPLOAD_ERR_OK||($file['size']??0)>4000000||!is_uploaded_file($file['tmp_name']??''))throw new InvalidArgumentException('Upload a PDF, JPEG or PNG smaller than 4 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true))throw new InvalidArgumentException('Only PDF, JPEG and PNG are accepted.');
    $name=substr(preg_replace('/[^a-zA-Z0-9._ -]/','_',basename($file['name'])),0,190);
    $pdo->prepare('INSERT INTO hr_files(record_id,uploaded_by,filename,mime,content) VALUES(?,?,?,?,?)')->execute([$id,$user['id'],$name,$mime,file_get_contents($file['tmp_name'])]);
    $pdo->prepare("UPDATE hr_records SET status='Received',version=version+1 WHERE id=?")->execute([$id]);
   }
   audit_action($pdo,(int)$user['id'],$action,$id);$pdo->commit();
  }elseif($action==='suite_account'){
   if(!$admin||$page!=='employees'||!$suiteReady)throw new InvalidArgumentException('Administrator access required.');
   $id=(int)($_POST['employee_id']??0);$email=strtolower(field_text('email',190,false));$username=strtolower(field_text('username',190));
   if(($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))||!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',$username))throw new InvalidArgumentException('Use a valid email and a username with letters, digits, dots, dashes or underscores.');
   $pdo->beginTransaction();$q=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='employee' FOR UPDATE");$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Employee not found.');
   $pdo->prepare('UPDATE users SET email=?,username=? WHERE id=?')->execute([$email?:null,$username,$id]);audit_action($pdo,(int)$user['id'],'employee.login_updated',$id);$pdo->commit();
  }elseif($action==='suite_import_preview'){
   if(!$admin||$page!=='employees'||!$suiteReady)throw new InvalidArgumentException('Install the HR modules first.');
   $f=$_FILES['workbook']??[];if(($f['error']??1)!==UPLOAD_ERR_OK||($f['size']??0)>5000000||!is_uploaded_file($f['tmp_name']??''))throw new InvalidArgumentException('Upload an XLSX file up to 5 MB.');
   $_SESSION['employee_import']=import_xlsx($f['tmp_name']);
   header('Location: ?page=employees&preview=1');exit;
  }elseif($action==='suite_import_commit'){
   if(!$admin||$page!=='employees'||!$suiteReady)throw new InvalidArgumentException('Administrator access required.');
   $rows=$_SESSION['employee_import']??[];if(!$rows)throw new InvalidArgumentException('Preview the workbook first.');
   install_login_columns($pdo);
   $pdo->beginTransaction();$pdo->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();$created=0;$skipped=0;
   foreach($rows as $r){
    $q=$pdo->prepare('SELECT id FROM users WHERE LOWER(TRIM(name))=LOWER(?)');$q->execute([$r['name']]);if($q->fetchColumn()){$skipped++;continue;}
    $base=$r['username'];$uname=$base;$suffix=2;
    while(true){$q=$pdo->prepare('SELECT id FROM users WHERE username=?');$q->execute([$uname]);if(!$q->fetchColumn())break;$uname=$base.'.'.$suffix++;}
    $pdo->prepare("INSERT INTO users(name,email,username,password_hash,role,must_change_password) VALUES(?,NULL,?,?,'employee',1)")->execute([$r['name'],$uname,password_hash('User@123',PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();
    $d=$r;unset($d['name'],$d['username']);$d['employee_code']='';
    $pdo->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('employment',?,?,'Active',?,?)")->execute([$uid,$r['name'],json_encode($d,JSON_THROW_ON_ERROR),$user['id']]);
    audit_action($pdo,(int)$user['id'],'employee.imported',$uid);$created++;
   }
   $pdo->commit();unset($_SESSION['employee_import']);$_SESSION['import_result']="$created employee accounts created; $skipped existing names skipped without changing passwords.";
  }else{
   if(!$suiteReady)throw new InvalidArgumentException('Install the HR modules from System configuration first.');
   $def=$suite[$page]??null;if(!$def)throw new InvalidArgumentException('Unknown section.');
   $selfCreate=['expenses','advances'];$selfUpdate=['tasks','onboarding'];
   if(!$admin && !(($action==='suite_save' && in_array($page,$selfCreate,true) && empty($_POST['id']))||($action==='suite_progress' && in_array($page,$selfUpdate,true))))throw new InvalidArgumentException('This action requires administrator access.');
   $pdo->beginTransaction();$pdo->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();
   $id=(int)($_POST['id']??0);$old=null;
   if($id){$q=$pdo->prepare('SELECT * FROM hr_records WHERE id=? AND module=? FOR UPDATE');$q->execute([$id,$page]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old||(!$admin&&(int)$old['employee_id']!==(int)$user['id']))throw new InvalidArgumentException('Record unavailable.');if((int)($_POST['version']??0)!==(int)$old['version'])throw new InvalidArgumentException('This record changed. Reload before saving.');}
   if($action==='suite_progress'){
    if(!$old)throw new InvalidArgumentException('Choose a record.');$status=(string)($_POST['status']??'');if(!in_array($status,['To do','In progress','Completed'],true))throw new InvalidArgumentException('Invalid status.');
    $pdo->prepare('UPDATE hr_records SET status=?,version=version+1 WHERE id=?')->execute([$status,$id]);
   }elseif($action==='suite_save'){
    $title=field_text('title',190);$status=(string)($_POST['status']??'');if(!$admin)$status='Pending';if(!in_array($status,$def[4],true))throw new InvalidArgumentException('Invalid status.');
    $employee=$def[3]?($admin?(int)($_POST['employee_id']??0):(int)$user['id']):null;
    if($def[3]){$q=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='employee'");$q->execute([$employee]);if(!$q->fetchColumn())throw new InvalidArgumentException('Choose an employee account.');}
    $data=suite_data($def,$_POST);
    if($page==='settings'&&!in_array($data['timezone'],DateTimeZone::listIdentifiers(),true))throw new InvalidArgumentException('Use a timezone such as Asia/Kolkata.');
    if($page==='payroll'){
     require_once __DIR__.'/payroll/config.php';
     if(pay_ready($pdo)){$newPayroll=$pdo->prepare("SELECT e.id FROM hr_payroll_entries e JOIN hr_payroll_runs r ON r.id=e.run_id WHERE e.employee_id=? AND r.month=? AND r.status IN ('Finalized','Paid') LIMIT 1");$newPayroll->execute([$employee,$data['month']]);if($newPayroll->fetchColumn())throw new InvalidArgumentException('This employee already has finalized payroll for the month. Use its existing payslip.');}
     if(suite_net($data)<0)throw new InvalidArgumentException('Net pay cannot be negative.');
     if(!$old && $status!=='Draft')throw new InvalidArgumentException('Create a payroll draft before approving it.');
     if($old){$previous=json_decode($old['data'],true);if($old['status']==='Published')throw new InvalidArgumentException('Published payslips are locked. Create a separate adjustment next month.');if($status==='Published'&&($old['status']!=='Approved'||$data!==$previous||(int)$old['employee_id']!==$employee))throw new InvalidArgumentException('Approve the unchanged payroll before publishing it.');if($old['status']==='Approved'&&$data!==$previous&&$status!=='Draft')throw new InvalidArgumentException('Return to Draft to change approved payroll.');}
     foreach(suite_rows($pdo,'payroll',$user,$def) as $r){$d=json_decode($r['data'],true);if((int)$r['id']!==$id&&(int)$r['employee_id']===$employee&&$d['month']===$data['month'])throw new InvalidArgumentException('This employee already has a payroll record for the month.');}
    }
    if(in_array($page,['devices','mapping','employment'],true))foreach(suite_rows($pdo,$page,$user,$def) as $r){if((int)$r['id']===$id)continue;$d=json_decode($r['data'],true);if(($page==='devices'&&$d['device_code']===$data['device_code'])||($page==='mapping'&&$d['device_code']===$data['device_code']&&$d['biometric_id']===$data['biometric_id'])||($page==='employment'&&((int)$r['employee_id']===$employee||($data['employee_code']!==''&&($d['employee_code']??'')===$data['employee_code']))))throw new InvalidArgumentException('This identifier already has a record. Edit the existing record.');}
    if($page==='roster'){$q=$pdo->prepare("SELECT id FROM hr_records WHERE id=? AND module='shifts' AND status='Active'");$q->execute([$data['shift_id']]);if(!$q->fetchColumn())throw new InvalidArgumentException('Choose an active shift record ID.');}
    $encoded=json_encode($data,JSON_THROW_ON_ERROR);
    if($old)$pdo->prepare('UPDATE hr_records SET title=?,status=?,employee_id=?,data=?,version=version+1 WHERE id=?')->execute([$title,$status,$employee,$encoded,$id]);
    else{$pdo->prepare('INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES(?,?,?,?,?,?)')->execute([$page,$employee,$title,$status,$encoded,$user['id']]);$id=(int)$pdo->lastInsertId();}
   }else throw new InvalidArgumentException('Unknown action.');
   audit_action($pdo,(int)$user['id'],$page.'.'.$action,$id);$pdo->commit();
  }
  header('Location: ?page='.urlencode($page).'&saved=1');exit;
 }catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
 catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error='Unable to save this record. Check the database migration and try again.';}
 $suiteHandledPost=true;
}
if($suiteReady && isset($suite[$page]) && isset($_GET['export'])) {
 $def=$suite[$page];$rows=suite_rows($pdo,$page,$user,$def);$csv=[];foreach($rows as $r){$d=json_decode($r['data'],true);$csv[]=array_merge([$r['id'],$r['title'],$r['employee_name'],$r['status']],array_map(fn($k)=>$d[$k]??'',array_keys($def[5])));}
 suite_csv(array_merge(['ID','Title','Employee','Status'],array_column($def[5],0)),$csv);
}
function suite_render(PDO $pdo,array $user,string $page,array $def,bool $ready): void {
 $admin=$user['role']==='super_admin';
 if(!$ready){echo '<section class="panel"><h2>Install HR modules</h2><p>Open System configuration to install the additional database tables.</p></section>';return;}
 $rows=suite_rows($pdo,$page,$user,$def);$edit=null;foreach($rows as $r)if((int)$r['id']===(int)($_GET['edit']??0))$edit=$r;
 $data=$edit?json_decode($edit['data'],true):[];
 $canCreate=$admin||in_array($page,['expenses','advances'],true);
 echo '<div class="suite-toolbar"><span>'.count($rows).' records · showing latest 1,000</span><a class="wide-button" href="?page='.h($page).'&export=1">Export CSV</a>';
 if($canCreate)echo '<a class="primary" href="?page='.h($page).'&new=1">+ Add record</a>';echo '</div>';
 if($page==='payroll')echo '<section class="panel"><h2>Monthly payroll review</h2><p>Enter verified earnings and deductions, save a draft, approve, then publish. Only published payslips are visible to employees. Statutory calculations and attendance deductions must be checked and entered by payroll staff.</p></section>';
 if($page==='balances')echo '<section class="panel"><p>Entitlements are annual calendar-day allowances. Approved leave is counted inclusively, including weekends and holidays. LWP is unpaid leave without an entitlement cap.</p></section>';
 if($page==='documents')echo '<section class="panel"><p>Track document receipt, verification and expiry. Upload PDF, JPEG or PNG files up to 4 MB. Downloads are restricted to the employee and Super Admin.</p></section>';
 if($canCreate && (isset($_GET['new'])||($edit&&$admin))){
 echo '<section class="panel"><h2>'.($edit?'Edit record #'.(int)$edit['id']:'New '.h(strtolower($def[0]))).' </h2><form method="post" class="work-form suite-form">';token();echo '<input type="hidden" name="action" value="suite_save"><input type="hidden" name="id" value="'.(int)($edit['id']??0).'"><input type="hidden" name="version" value="'.(int)($edit['version']??0).'"><label>Title / name<input name="title" maxlength="190" required value="'.h($edit['title']??'').'"></label>';
 if($def[3]&&$admin){echo '<label>Employee<select name="employee_id" required><option value="">Select employee</option>';foreach($pdo->query("SELECT id,name FROM users WHERE role='employee' ORDER BY name") as $u)echo '<option value="'.(int)$u['id'].'" '.((int)($edit['employee_id']??0)===(int)$u['id']?'selected':'').'>'.h($u['name']).' #'.(int)$u['id'].'</option>';echo '</select></label>';}
 if($admin){echo '<label>Status<select name="status">';foreach($def[4] as $s)echo '<option '.(($edit['status']??$def[4][0])===$s?'selected':'').'>'.h($s).'</option>';echo '</select></label>';}
 foreach($def[5] as $k=>$f){$v=$data[$k]??'';$req=$f[2]?' required':'';echo '<label>'.h($f[0]);if($f[1]==='textarea')echo '<textarea name="'.h($k).'" maxlength="10000"'.$req.'>'.h($v).'</textarea>';elseif($f[1]==='select'){echo '<select name="'.h($k).'"'.$req.'>';foreach($f[3] as $opt)echo '<option '.($v===$opt?'selected':'').'>'.h($opt).'</option>';echo '</select>';}else echo '<input name="'.h($k).'" type="'.h($f[1]).'" value="'.h($v).'"'.($f[1]==='number'?' min="0" max="1000000000" step="0.01"':'').' '.$req.'>';echo '</label>';}
 echo '<button class="primary">Save record</button><a href="?page='.h($page).'">Cancel</a></form></section>';
 }
 echo '<section class="panel"><div class="suite-toolbar"><h2>'.h($def[0]).'</h2><label>Search <input class="record-search" type="search" placeholder="Search these records" aria-label="Search records"></label></div><div class="table-scroll"><table class="record-table"><thead><tr><th>ID / title</th><th>Employee</th><th>Status</th><th>Details</th><th>Action</th></tr></thead><tbody>';
 foreach($rows as $r){$d=json_decode($r['data'],true);echo '<tr><td>#'.(int)$r['id'].'<br><b>'.h($r['title']).'</b></td><td>'.h($r['employee_name']??'Company').'</td><td><span class="badge">'.h($r['status']).'</span></td><td><details><summary>View details</summary><dl class="record-detail">';foreach($def[5] as $k=>$f)echo '<dt>'.h($f[0]).'</dt><dd>'.nl2br(h($d[$k]??'—')).'</dd>';if($page==='balances'){require_once __DIR__.'/leave-balances.php';foreach(['CL','SL','PL'] as $type){$used=leave_used($pdo,(int)$r['employee_id'],$type,(int)$d['year']);echo '<dt>'.h($type).' used / remaining</dt><dd>'.$used.' / '.max(0,(float)$d[$type]-$used).'</dd>';}}if($page==='payroll')echo '<dt>Net payable</dt><dd>₹'.number_format(suite_net($d),2).'</dd>';echo '</dl></details></td><td>';
 if($admin)echo '<a href="?page='.h($page).'&edit='.(int)$r['id'].'">Edit</a>';
 if($page==='payroll'&&$r['status']==='Published')echo ' <a target="_blank" rel="noopener" href="payslip.php?id='.(int)$r['id'].'">Print / PDF</a>';
 if(!$admin&&in_array($page,['tasks','onboarding'],true)){echo '<form method="post">';token();echo '<input type="hidden" name="action" value="suite_progress"><input type="hidden" name="id" value="'.(int)$r['id'].'"><input type="hidden" name="version" value="'.(int)$r['version'].'"><select name="status" aria-label="Task status">';foreach(['To do','In progress','Completed'] as $s)echo '<option '.($r['status']===$s?'selected':'').'>'.h($s).'</option>';echo '</select><button class="primary">Update</button></form>';}
 if($page==='documents'){
  echo '<form method="post" enctype="multipart/form-data">';token();echo '<input type="hidden" name="action" value="suite_upload"><input type="hidden" name="id" value="'.(int)$r['id'].'"><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" aria-label="Document file" required><button class="primary">Upload</button></form>';
  $fq=$pdo->prepare('SELECT id,filename FROM hr_files WHERE record_id=? ORDER BY id DESC');$fq->execute([$r['id']]);foreach($fq as $file)echo '<p><a href="document.php?id='.(int)$file['id'].'">'.h($file['filename']).'</a></p>';
 }
 if($page==='policies'&&$r['status']==='Published'){
  $aq=$pdo->prepare('SELECT acknowledged_at FROM hr_acknowledgements WHERE record_id=? AND user_id=? AND version=?');$aq->execute([$r['id'],$user['id'],$r['version']]);$when=$aq->fetchColumn();
  if($when)echo '<p>Acknowledged '.h($when).'</p>';else{echo '<form method="post">';token();echo '<input type="hidden" name="action" value="suite_ack"><input type="hidden" name="id" value="'.(int)$r['id'].'"><input type="hidden" name="version" value="'.(int)$r['version'].'"><button class="primary">I have read this policy</button></form>';}
 }
 echo '</td></tr>';}
 if(!$rows)echo '<tr><td colspan="5">No records yet.</td></tr>';echo '</tbody></table></div></section>';
}
