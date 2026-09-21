<?php
require_once __DIR__.'/core.php';require_once dirname(__DIR__).'/employee-import.php';
function foundation_migrate(PDO $pdo):void {
 $lock=$pdo->query("SELECT GET_LOCK('peopleflow_phase1_migration',10)")->fetchColumn();if(!$lock)throw new RuntimeException('Another migration is running. Try again shortly.');
 try{
  if(foundation_ready($pdo))return;
  foreach(['002-workspaces.sql','003-hr-suite.sql','004-foundation.sql'] as $file)foreach(explode(';',file_get_contents(dirname(__DIR__,2).'/database/'.$file)) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
  install_login_columns($pdo);$cols=$pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);if(!in_array('session_version',$cols,true))$pdo->exec('ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0');
  $pdo->beginTransaction();
  $perms=['dashboard'=>['view'],'employees'=>['view','create','edit','delete'],'departments'=>['view','create','edit','delete'],'designations'=>['view','create','edit','delete'],'roles'=>['view','create','edit','delete'],'settings'=>['view','edit'],'documents'=>['view','create','edit','delete']];
  foreach($perms as $module=>$actions)foreach($actions as $action){$code=$module.'.'.$action;$pdo->prepare('INSERT IGNORE INTO permissions(code,label) VALUES(?,?)')->execute([$code,ucfirst($action).' '.str_replace('_',' ',$module)]);}
  foreach(['CEO','Director','HR','Manager','Team Leader','Employee'] as $name)$pdo->prepare('INSERT IGNORE INTO roles(name,slug,description) VALUES(?,?,?)')->execute([$name,strtolower(str_replace(' ','-',$name)),'Editable company role. No admin permissions granted by default.']);
  $roleId=(int)$pdo->query("SELECT id FROM roles WHERE slug='employee'")->fetchColumn();
  $pdo->exec("INSERT IGNORE INTO company_settings(id,name) VALUES(1,'Arkentech Solutions')");
  foreach($pdo->query("SELECT * FROM hr_records WHERE module='departments' ORDER BY id") as $r){$d=json_decode($r['data'],true)?:[];$pdo->prepare('INSERT IGNORE INTO departments(name,description,active,source_record_id) VALUES(?,?,?,?)')->execute([$r['title'],'Migrated company department',$r['status']==='Active',$r['id']]);}
  foreach($pdo->query("SELECT * FROM hr_records WHERE module='designations' ORDER BY id") as $r){$d=json_decode($r['data'],true)?:[];$dept=null;if(!empty($d['department'])){$pdo->prepare('INSERT IGNORE INTO departments(name) VALUES(?)')->execute([$d['department']]);$q=$pdo->prepare('SELECT id FROM departments WHERE name=?');$q->execute([$d['department']]);$dept=$q->fetchColumn();}$pdo->prepare('INSERT IGNORE INTO designations(name,department_id,active,source_record_id) VALUES(?,?,?,?)')->execute([$r['title'],$dept,$r['status']==='Active',$r['id']]);}
  foreach($pdo->query('SELECT * FROM users ORDER BY id') as $u){
   if(empty($u['username']))$pdo->prepare('UPDATE users SET username=? WHERE id=?')->execute([unique_username($pdo,$u['name']),$u['id']]);
   if($u['role']==='super_admin')continue;
   $q=$pdo->prepare("SELECT * FROM hr_records WHERE module='employment' AND employee_id=? ORDER BY id DESC LIMIT 1");$q->execute([$u['id']]);$old=$q->fetch(PDO::FETCH_ASSOC);$d=$old?json_decode($old['data'],true):[];$d=is_array($d)?$d:[];
   [$first,$middle,$last]=split_employee_name($u['name']);$department=null;$designation=null;
   if(!empty($d['department'])){$pdo->prepare('INSERT IGNORE INTO departments(name) VALUES(?)')->execute([$d['department']]);$q=$pdo->prepare('SELECT id FROM departments WHERE name=?');$q->execute([$d['department']]);$department=$q->fetchColumn();}
   if(!empty($d['designation'])){$q=$pdo->prepare('SELECT id FROM designations WHERE name=? AND department_id <=> ? LIMIT 1');$q->execute([$d['designation'],$department]);$designation=$q->fetchColumn();if(!$designation){$pdo->prepare('INSERT INTO designations(name,department_id) VALUES(?,?)')->execute([$d['designation'],$department]);$designation=$pdo->lastInsertId();}}
   $code=trim($d['employee_code']??'')?:null;if($code){$q=$pdo->prepare('SELECT user_id FROM employees WHERE employee_code=?');$q->execute([$code]);if($q->fetchColumn())$code=null;}
   $birth=null;$joining=null;try{$birth=fdate($d,'birth_date');}catch(InvalidArgumentException $e){}try{$joining=fdate($d,'joining_date');}catch(InvalidArgumentException $e){}
   $pdo->prepare('INSERT IGNORE INTO employees(user_id,employee_code,first_name,middle_name,last_name,mobile,birth_date,blood_group,emergency_phone,department_id,designation_id,joining_date,employment_status,import_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$u['id'],$code,$first,$middle,$last,$d['phone']??'',$birth,$d['blood_group']??'',$d['emergency_contact']??'',$department,$designation,$joining,$u['active']?'Active':'Inactive','Migrated existing employee data. Source records retained.']);
   $pdo->prepare('INSERT IGNORE INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$u['id'],$roleId]);
  }
  $pdo->exec("INSERT INTO hr_migrations(name) VALUES('004-foundation')");$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 finally{$pdo->query("SELECT RELEASE_LOCK('peopleflow_phase1_migration')");}
}
