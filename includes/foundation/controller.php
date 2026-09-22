<?php
require_once __DIR__.'/core.php';require_once __DIR__.'/employees.php';require_once __DIR__.'/migrate.php';
$pdo=db();$installed=foundation_ready($pdo);$page=(string)($_GET['page']??'overview');if($page==='system')$page='account';
$pages=['overview'=>['Dashboard','dashboard.view'],'employees'=>['All employees','employees.view'],'employee-add'=>['Add employee','employees.create'],'employee-edit'=>['Edit employee','employees.edit'],'employee-view'=>['Employee profile','employees.view'],'settings'=>['Company settings','settings.view'],'departments'=>['Departments','departments.view'],'designations'=>['Designations','designations.view'],'roles'=>['Roles & permissions','roles.view'],'account'=>['My account',null]];
require_once dirname(__DIR__).'/core-hr/controller.php';$pages=array_merge($pages,$corePages);
if(!isset($pages[$page])){http_response_code(404);exit('Page not found.');}
if($pages[$page][1])need($pdo,$user,$pages[$page][1]);
if(in_array($page,['devices','mapping','sync','raw_logs'],true)&&!att_biometric_enabled($pdo)){http_response_code(403);exit('Biometric attendance is disabled. Manual attendance remains available.');}
if($page==='employee-view'&&($_GET['tab']??'')==='documents')need($pdo,$user,'documents.view');
$error='';$notice=$_SESSION['foundation_notice']??'';unset($_SESSION['foundation_notice']);
$company=$installed?$pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC):['name'=>'Arkentech Solutions'];
if($installed&&!empty($company['timezone']))date_default_timezone_set($company['timezone']);
if(isset($_GET['export'])&&in_array($page,['attendance','attendance_history','monthly'],true))chr_export($pdo,$user,$page);
$docTypes=['Aadhaar Card','PAN Card','Resume','Offer Letter','Appointment Letter','Education Documents','Experience Letter','Relieving Letter','Passport','Other Documents'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf();$action=(string)($_POST['action']??'');
 try{
  if(str_starts_with($action,'att_')){$notice=att_handle_post($pdo,$user,$action,$_POST,$_FILES);
  }elseif(str_starts_with($action,'core_')){$notice=chr_handle_post($pdo,$user,$action,$_POST,$_FILES);
  }elseif($action==='install_foundation'){
   if($user['role']!=='super_admin')throw new InvalidArgumentException('Super Admin access required.');foundation_migrate($pdo);faudit($pdo,$user,'foundation.installed');$notice='Phase 1 database installed. Existing records were preserved.';
  }else{
   if(!$installed)throw new InvalidArgumentException('Install the Phase 1 database first.');
   if($action==='save_employee'){
    need($pdo,$user,empty($_POST['id'])?'employees.create':'employees.edit');$id=save_employee($pdo,$user,$_POST,$_FILES);$_SESSION['foundation_notice']='Employee saved.';header('Location: ?page=employee-view&id='.$id);exit;
   }elseif($action==='reset_employee_password'){
    if($user['role']!=='super_admin')throw new InvalidArgumentException('Only Super Admin can reset employee passwords.');$id=(int)($_POST['id']??0);$target=foundation_employee($pdo,$id);if(!$target||$id===(int)$user['id'])throw new InvalidArgumentException('Employee not found.');$temporary=(string)($_POST['temporary_password']??'');if($temporary!==''&&(strlen($temporary)<12||strlen($temporary)>72))throw new InvalidArgumentException('Use 12–72 characters or leave blank for User@123.');$pdo->beginTransaction();$q=$pdo->prepare("UPDATE users SET password_hash=?,must_change_password=1,session_version=session_version+1 WHERE id=? AND role='employee'");$q->execute([password_hash($temporary?:'User@123',PASSWORD_DEFAULT),$id]);if(!$q->rowCount())throw new InvalidArgumentException('This account is protected.');$pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);faudit($pdo,$user,'employee.password_reset',$id);$pdo->commit();$notice='Temporary password set. The employee must change it at sign-in.';
   }elseif(in_array($action,['toggle_employee','delete_employee'],true)){
    need($pdo,$user,$action==='delete_employee'?'employees.delete':'employees.edit');$id=(int)($_POST['id']??0);$pdo->beginTransaction();$q=$pdo->prepare("SELECT u.*,e.employment_status FROM users u JOIN employees e ON e.user_id=u.id WHERE u.id=? AND e.deleted_at IS NULL FOR UPDATE");$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);
    if(!$u||$u['role']==='super_admin'||$id===(int)$user['id'])throw new InvalidArgumentException('This account cannot be changed here.');
    if($action==='delete_employee'){$pdo->prepare('UPDATE employees SET deleted_at=NOW(),version=version+1 WHERE user_id=?')->execute([$id]);$pdo->prepare('UPDATE users SET active=0,session_version=session_version+1 WHERE id=?')->execute([$id]);$notice='Employee archived. Login disabled; historical records retained.';}
    else{$active=$u['active']?0:1;if($active&&in_array($u['employment_status'],['Inactive','Resigned','Terminated'],true))throw new InvalidArgumentException('Update employment status before reactivating login.');$pdo->prepare('UPDATE users SET active=?,session_version=session_version+1 WHERE id=?')->execute([$active,$id]);$notice=$active?'Employee login activated.':'Employee login deactivated.';}
    faudit($pdo,$user,$action,$id);$pdo->commit();
   }elseif(in_array($action,['save_department','save_designation','save_role'],true)){
    $kind=['save_department'=>'departments','save_designation'=>'designations','save_role'=>'roles'][$action];$id=(int)($_POST['id']??0);need($pdo,$user,$kind.'.'.($id?'edit':'create'));$name=ftext($_POST,'name',100,true);$description=ftext($_POST,'description',1000);$active=isset($_POST['active'])?1:0;$pdo->beginTransaction();
    $pdo->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();
    if($id){$q=$pdo->prepare("SELECT * FROM $kind WHERE id=? FOR UPDATE");$q->execute([$id]);$existing=$q->fetch(PDO::FETCH_ASSOC);if(!$existing)throw new InvalidArgumentException('Record not found.');}
    if($kind==='departments'){
     $code=ftext($_POST,'code',40)?:null;$head=(int)($_POST['head_id']??0)?:null;if($head){$q=$pdo->prepare('SELECT id FROM users WHERE id=? AND active=1');$q->execute([$head]);if(!$q->fetchColumn())throw new InvalidArgumentException('Choose an active department head.');}
     if($id)$pdo->prepare('UPDATE departments SET name=?,code=?,description=?,head_id=?,active=? WHERE id=?')->execute([$name,$code,$description,$head,$active,$id]);else{$pdo->prepare('INSERT INTO departments(name,code,description,head_id,active) VALUES(?,?,?,?,?)')->execute([$name,$code,$description,$head,$active]);$id=(int)$pdo->lastInsertId();}
    }elseif($kind==='designations'){
     $department=(int)($_POST['department_id']??0)?:null;if($department){$q=$pdo->prepare('SELECT id FROM departments WHERE id=? AND active=1');$q->execute([$department]);if(!$q->fetchColumn())throw new InvalidArgumentException('Choose an active department.');}
     $q=$pdo->prepare('SELECT id FROM designations WHERE name=? AND department_id <=> ? AND id<>?');$q->execute([$name,$department,$id]);if($q->fetchColumn())throw new InvalidArgumentException('This designation already exists in that department.');
     if($id&&$department){$q=$pdo->prepare('SELECT user_id FROM employees WHERE designation_id=? AND (department_id IS NULL OR department_id<>?) LIMIT 1');$q->execute([$id,$department]);if($q->fetchColumn())throw new InvalidArgumentException('Reassign affected employees before moving this designation to a different department.');}
     if($id)$pdo->prepare('UPDATE designations SET name=?,department_id=?,description=?,active=? WHERE id=?')->execute([$name,$department,$description,$active,$id]);else{$pdo->prepare('INSERT INTO designations(name,department_id,description,active) VALUES(?,?,?,?)')->execute([$name,$department,$description,$active]);$id=(int)$pdo->lastInsertId();}
    }else{
     $slug=strtolower(ftext($_POST,'slug',100,true));if(!preg_match('/^[a-z][a-z0-9-]*$/',$slug)||$slug==='super-admin'||$slug==='super_admin')throw new InvalidArgumentException('Use a lowercase slug. Super Admin is a protected system role.');
     if($id&&$existing['slug']==='employee'&&($slug!=='employee'||!$active))throw new InvalidArgumentException('Keep the default Employee role active with its existing slug.');
     if($id)$pdo->prepare('UPDATE roles SET name=?,slug=?,description=?,active=? WHERE id=?')->execute([$name,$slug,$description,$active,$id]);else{$pdo->prepare('INSERT INTO roles(name,slug,description,active) VALUES(?,?,?,?)')->execute([$name,$slug,$description,$active]);$id=(int)$pdo->lastInsertId();}
     $selected=array_map('intval',(array)($_POST['permissions']??[]));$valid=$pdo->query('SELECT id FROM permissions')->fetchAll(PDO::FETCH_COLUMN);foreach($selected as $p)if(!in_array((string)$p,array_map('strval',$valid),true))throw new InvalidArgumentException('Unknown permission.');
     $pdo->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$id]);foreach(array_unique($selected) as $p)$pdo->prepare('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)')->execute([$id,$p]);
    }faudit($pdo,$user,$action,$id);$pdo->commit();$notice='Saved successfully.';
   }elseif(in_array($action,['delete_department','delete_designation','delete_role'],true)){
    $table=['delete_department'=>'departments','delete_designation'=>'designations','delete_role'=>'roles'][$action];need($pdo,$user,$table.'.delete');$id=(int)($_POST['id']??0);$pdo->beginTransaction();
    if($table==='roles'){$q=$pdo->prepare("SELECT id FROM roles WHERE id=? AND slug<>'employee' FOR UPDATE");$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('The default Employee role cannot be deleted.');}
    $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);faudit($pdo,$user,$action,$id);$pdo->commit();$notice='Deleted. Assigned records are protected from deletion.';
   }elseif($action==='save_company'){
    need($pdo,$user,'settings.edit');$vals=['name'=>ftext($_POST,'name',150,true),'email'=>femail($_POST,'email')??''];foreach(['phone'=>40,'website'=>250,'address'=>2000,'city'=>100,'state'=>100,'country'=>100,'pin_code'=>20,'timezone'=>100] as $key=>$max)$vals[$key]=ftext($_POST,$key,$max);
    if($vals['website']!==''&&(!filter_var($vals['website'],FILTER_VALIDATE_URL)||!in_array(parse_url($vals['website'],PHP_URL_SCHEME),['http','https'],true)))throw new InvalidArgumentException('Website must use http or https.');if(!in_array($vals['timezone'],DateTimeZone::listIdentifiers(),true))throw new InvalidArgumentException('Choose a valid IANA timezone.');
    $photo=fimage($_FILES['logo']??[]);$pdo->beginTransaction();$set=implode(',',array_map(fn($k)=>"$k=?",array_keys($vals)));$pdo->prepare("UPDATE company_settings SET $set WHERE id=1")->execute(array_values($vals));save_media($pdo,$user,null,'company_logo',$photo);faudit($pdo,$user,'company.updated',1);$pdo->commit();$notice='Company settings updated.';
   }elseif($action==='preview_import'){
    need($pdo,$user,'employees.create');$file=$_FILES['workbook']??[];if(($file['error']??1)!==UPLOAD_ERR_OK||$file['size']>5000000||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Upload an XLSX workbook up to 5 MB.');$_SESSION['foundation_import']=import_xlsx($file['tmp_name']);$notice='Review the import preview. No accounts have been created yet.';
   }elseif($action==='commit_import'){
    need($pdo,$user,'employees.create');$rows=$_SESSION['foundation_import']??[];if(!$rows)throw new InvalidArgumentException('Preview a workbook first.');[$count,$skipped]=import_foundation($pdo,$user,$rows);unset($_SESSION['foundation_import']);$notice="$count accounts created. $skipped existing names skipped without password changes.";
   }elseif($action==='save_document'){
    $recordId=(int)($_POST['document_id']??0);need($pdo,$user,$recordId?'documents.edit':'documents.create');$employee=(int)($_POST['employee_id']??0);if(!foundation_employee($pdo,$employee))throw new InvalidArgumentException('Employee not found.');$type=ftext($_POST,'type',80,true);if(!in_array($type,$docTypes,true))throw new InvalidArgumentException('Select a document type.');$title=ftext($_POST,'title',190,true);$data=['type'=>$type,'issued'=>fdate($_POST,'issued'),'expires'=>fdate($_POST,'expires'),'notes'=>ftext($_POST,'notes',5000)];if($data['issued']&&$data['expires']&&$data['expires']<$data['issued'])throw new InvalidArgumentException('Expiry cannot precede issue date.');
    $file=$_FILES['document']??[];$hasFile=($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;if(!$recordId&&!$hasFile)throw new InvalidArgumentException('Choose a document file.');
    if($hasFile){if($file['error']!==UPLOAD_ERR_OK||$file['size']>4000000||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Upload a document up to 4 MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true))throw new InvalidArgumentException('Only PDF, JPEG or PNG files are supported.');}
    $pdo->beginTransaction();if($recordId){$q=$pdo->prepare("SELECT id FROM hr_records WHERE id=? AND module='documents' AND employee_id=? FOR UPDATE");$q->execute([$recordId,$employee]);if(!$q->fetchColumn())throw new InvalidArgumentException('Document not found.');$pdo->prepare('UPDATE hr_records SET title=?,data=?,version=version+1 WHERE id=?')->execute([$title,json_encode($data),$recordId]);}else{$pdo->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('documents',?,?,'Received',?,?)")->execute([$employee,$title,json_encode($data),$user['id']]);$recordId=(int)$pdo->lastInsertId();}
    if($hasFile){$name=substr(preg_replace('/[^a-zA-Z0-9._ -]/','_',basename($file['name'])),0,190);$pdo->prepare('INSERT INTO hr_files(record_id,uploaded_by,filename,mime,content) VALUES(?,?,?,?,?)')->execute([$recordId,$user['id'],$name,$mime,file_get_contents($file['tmp_name'])]);}faudit($pdo,$user,'document.saved',$recordId);$pdo->commit();$notice='Document saved.';
   }elseif($action==='delete_document'){
    need($pdo,$user,'documents.delete');$id=(int)($_POST['document_id']??0);$pdo->beginTransaction();$q=$pdo->prepare("SELECT id FROM hr_records WHERE id=? AND module='documents' FOR UPDATE");$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Document not found.');$pdo->prepare('DELETE FROM hr_files WHERE record_id=?')->execute([$id]);$pdo->prepare('DELETE FROM hr_records WHERE id=?')->execute([$id]);faudit($pdo,$user,'document.deleted',$id);$pdo->commit();$notice='Document and its uploaded versions deleted.';
   }elseif($action==='change_password'){
    $old=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');if(strlen($new)<12||strlen($new)>72||$new!==($_POST['confirm_password']??''))throw new InvalidArgumentException('Use matching new passwords of 12–72 characters.');
    $pdo->beginTransaction();$q=$pdo->prepare('SELECT password_hash,session_version FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!password_verify($old,$row['password_hash']))throw new InvalidArgumentException('Current password is incorrect.');
    $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=0,session_version=session_version+1 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);faudit($pdo,$user,'password.changed',(int)$user['id']);$pdo->commit();$_SESSION['user']['session_version']=(int)$row['session_version']+1;$_SESSION['user']['must_change_password']=0;session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$notice='Password changed. Other sessions have been signed out.';
   }else throw new InvalidArgumentException('Unknown action.');
  }
  $_SESSION['foundation_notice']=$notice;$redirect='?page='.urlencode($page);if(isset($_GET['id']))$redirect.='&id='.(int)$_GET['id'];if(in_array($_GET['tab']??'',['overview','employment','documents'],true))$redirect.='&tab='.$_GET['tab'];header('Location: '.$redirect);exit;
 }catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
 catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();$code=(int)($e->errorInfo[1]??0);$error=$code===1062?'That name, code, username or email already exists.':($code===1451?'This record is assigned to an employee or another record. Reassign it or deactivate it instead.':'Database operation failed. Please check the migration and server log.');error_log('sHRMS foundation database code '.$code);}
 catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error='The operation could not be completed. Check the server log and try again.';error_log('sHRMS foundation operation failed: '.get_class($e));}
}
$company=$installed?$pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC):['name'=>'Arkentech Solutions'];
if($installed&&!empty($company['timezone']))date_default_timezone_set($company['timezone']);
require __DIR__.'/layout.php';
