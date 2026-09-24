<?php
function save_employee(PDO $pdo,array $actor,array $in,array $files):int {
 $id=(int)($in['id']??0);$old=$id?foundation_employee($pdo,$id):null;if($id&&!$old)throw new InvalidArgumentException('Employee not found.');
 $q=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='super_admin'");$q->execute([$id]);if($q->fetchColumn())throw new InvalidArgumentException('Super Admin accounts are protected.');
 $first=ftext($in,'first_name',80,true);$middle=ftext($in,'middle_name',100);$last=ftext($in,'last_name',80,true);$name=trim(preg_replace('/\s+/',' ',"$first $middle $last"));if(strlen($name)>150)throw new InvalidArgumentException('Full name must be at most 150 characters.');
 $email=femail($in,'email');$personal=femail($in,'personal_email');$username=strtolower(ftext($in,'username',190));if($username!==''&&!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',$username))throw new InvalidArgumentException('Enter a valid username.');
 $department=(int)($in['department_id']??0);$designation=(int)($in['designation_id']??0);$role=(int)($in['role_id']??0);$manager=(int)($in['manager_id']??0)?:null;
 $q=$pdo->prepare('SELECT id FROM departments WHERE id=? AND active=1');$q->execute([$department]);if(!$q->fetchColumn())throw new InvalidArgumentException('Select an active department from Company settings.');
 $q=$pdo->prepare('SELECT id FROM designations WHERE id=? AND active=1 AND (department_id=? OR department_id IS NULL)');$q->execute([$designation,$department]);if(!$q->fetchColumn())throw new InvalidArgumentException('Select a designation applicable to this department.');
 $q=$pdo->prepare('SELECT id FROM roles WHERE id=? AND active=1');$q->execute([$role]);if(!$q->fetchColumn())throw new InvalidArgumentException('Select an active role.');
 if(!$old || (int)$old['role_id']!==$role){$default=(int)$pdo->query("SELECT id FROM roles WHERE slug='employee'")->fetchColumn();if(($old||$role!==$default)&&!can($pdo,$actor,'roles.edit'))throw new InvalidArgumentException('Role assignment requires roles.edit permission.');}
 if($manager){if($manager===$id)throw new InvalidArgumentException('An employee cannot report to themselves.');$q=$pdo->prepare('SELECT id FROM users WHERE id=? AND active=1');$q->execute([$manager]);if(!$q->fetchColumn())throw new InvalidArgumentException('Choose an active reporting manager.');$visited=[$id];$cursor=$manager;while($cursor){if(in_array($cursor,$visited,true))throw new InvalidArgumentException('Reporting hierarchy cannot contain a cycle.');$visited[]=$cursor;$q=$pdo->prepare('SELECT manager_id FROM employees WHERE user_id=?');$q->execute([$cursor]);$cursor=(int)$q->fetchColumn();}}
 $type=ftext($in,'employment_type',30,true);$status=ftext($in,'employment_status',30,true);$gender=ftext($in,'gender',30);
 if(!in_array($type,['Full Time','Part Time','Contract','Intern','Probation'],true)||!in_array($status,['Active','Inactive','Notice Period','Resigned','Terminated'],true)||!in_array($gender,['','Female','Male','Non-binary','Prefer not to say'],true))throw new InvalidArgumentException('Select valid employment and personal options.');
 $birth=fdate($in,'birth_date');$joining=fdate($in,'joining_date');if(!$joining)throw new InvalidArgumentException('Joining date is required.');if($birth&&$birth>date('Y-m-d'))throw new InvalidArgumentException('Date of birth cannot be in the future.');
 $photo=fimage($files['photo']??[]);$password=(string)($in['password']??'');if(!$id&&$password!==''&&(strlen($password)<12||strlen($password)>72))throw new InvalidArgumentException('Use an initial password of 12–72 characters or leave it blank for the import default.');
 $values=['employee_code'=>ftext($in,'employee_code',60)?:null,'first_name'=>$first,'middle_name'=>$middle,'last_name'=>$last,'personal_email'=>$personal,'birth_date'=>$birth,'joining_date'=>$joining,'department_id'=>$department,'designation_id'=>$designation,'manager_id'=>$manager,'employment_type'=>$type,'employment_status'=>$status,'gender'=>$gender];
 foreach(['mobile'=>40,'alternate_phone'=>40,'blood_group'=>10,'current_address'=>2000,'permanent_address'=>2000,'city'=>100,'state'=>100,'country'=>100,'pin_code'=>20,'emergency_name'=>150,'emergency_phone'=>40,'emergency_relationship'=>80] as $k=>$max)$values[$k]=ftext($in,$k,$max);
 $pdo->beginTransaction();try{
  $pdo->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();
  if($id){$q=$pdo->prepare('SELECT version FROM employees WHERE user_id=? FOR UPDATE');$q->execute([$id]);if((int)$q->fetchColumn()!==(int)($in['version']??0))throw new InvalidArgumentException('This profile changed. Reload it before saving.');}
  if($old&&(int)$old['department_id']!==$department&&function_exists('att_ready')&&att_ready($pdo))att_record_write($pdo,$actor,'department_history',['title'=>'Department change'],['date'=>date('Y-m-d'),'before'=>(int)$old['department_id'],'after'=>$department],$id);
  if($username==='')$username=$old['username']??unique_username($pdo,$name);
  if($id){$pdo->prepare('UPDATE users SET name=?,email=?,username=? WHERE id=?')->execute([$name,$email,$username,$id]);$sets=implode(',',array_map(fn($k)=>"$k=?",array_keys($values)));$pdo->prepare("UPDATE employees SET $sets,version=version+1 WHERE user_id=?")->execute([...array_values($values),$id]);}
  else{$pdo->prepare("INSERT INTO users(name,email,username,password_hash,role,must_change_password) VALUES(?,?,?,?,'employee',1)")->execute([$name,$email,$username,password_hash($password?:'User@123',PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();$cols=implode(',',array_keys($values));$marks=implode(',',array_fill(0,count($values),'?'));$pdo->prepare("INSERT INTO employees(user_id,$cols) VALUES(?,$marks)")->execute([$id,...array_values($values)]);}
  $pdo->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?) ON DUPLICATE KEY UPDATE role_id=VALUES(role_id)')->execute([$id,$role]);
  if(in_array($status,['Inactive','Resigned','Terminated'],true))$pdo->prepare('UPDATE users SET active=0,session_version=session_version+1 WHERE id=?')->execute([$id]);
  save_media($pdo,$actor,$id,'profile',$photo);faudit($pdo,$actor,$old?'employee.profile_updated':'employee.created',$id);$pdo->commit();return $id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function import_foundation(PDO $pdo,array $actor,array $rows):array {
 $created=0;$skipped=0;$pdo->beginTransaction();try{
  $pdo->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();$role=(int)$pdo->query("SELECT id FROM roles WHERE slug='employee'")->fetchColumn();if(!$role)throw new InvalidArgumentException('Create the Employee role before importing.');
  foreach($rows as $r){$q=$pdo->prepare('SELECT id FROM users WHERE LOWER(TRIM(name))=LOWER(?)');$q->execute([$r['name']]);if($q->fetchColumn()){$skipped++;continue;}
   $designation=null;if($r['designation']!==''){$q=$pdo->prepare('SELECT id FROM designations WHERE name=? AND department_id IS NULL LIMIT 1');$q->execute([$r['designation']]);$designation=$q->fetchColumn();if(!$designation){$pdo->prepare('INSERT INTO designations(name,description) VALUES(?,?)')->execute([$r['designation'],'Imported from employee workbook; assign a department in Company settings.']);$designation=$pdo->lastInsertId();}}
   $username=unique_username($pdo,$r['name']);$pdo->prepare("INSERT INTO users(name,email,username,password_hash,role,must_change_password) VALUES(?,NULL,?,?,'employee',1)")->execute([$r['name'],$username,password_hash('User@123',PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();[$first,$middle,$last]=split_employee_name($r['name']);
   $pdo->prepare('INSERT INTO employees(user_id,first_name,middle_name,last_name,designation_id,mobile,birth_date,blood_group,emergency_phone,import_notes) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$id,$first,$middle,$last,$designation,$r['phone'],$r['birth_date']?:null,$r['blood_group'],$r['emergency_contact'],'Physical Id card — Sheet1. ID card status: '.$r['source_status'].'. Employee ID intentionally left blank.']);
   $pdo->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$id,$role]);faudit($pdo,$actor,'employee.imported',$id);$created++;
  }$pdo->commit();return [$created,$skipped];
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function foundation_bulk_employees(PDO $db,array $actor,array $in):string {
 if(($actor['role']??'')!=='super_admin')throw new InvalidArgumentException('Only Super Admin can perform bulk employee actions.');
 $raw=$in['employee_ids']??[];
 if(!is_array($raw)||!$raw||count($raw)>100)throw new InvalidArgumentException('Select between 1 and 100 employees.');
 $ids=[];foreach($raw as $value){if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new InvalidArgumentException('Invalid employee selection.');$ids[]=(int)$value;}$ids=array_values(array_unique($ids));sort($ids);
 $operation=$in['operation']??'';if(!in_array($operation,['delete','assign'],true))throw new InvalidArgumentException('Choose a bulk action.');
 if(($in['confirmed']??'')!=='1')throw new InvalidArgumentException('Confirm the selected employee changes.');
 $department=(int)($in['bulk_department']??0);$designation=(int)($in['bulk_designation']??0);$role=(int)($in['bulk_role']??0);
 if($operation==='assign'&&!$department&&!$designation&&!$role)throw new InvalidArgumentException('Choose a department, designation or role to assign.');
 $db->beginTransaction();try {
  $db->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();
  $q=$db->prepare('SELECT e.*,u.role FROM employees e JOIN users u ON u.id=e.user_id WHERE e.user_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY e.user_id FOR UPDATE');$q->execute($ids);$employees=$q->fetchAll(PDO::FETCH_ASSOC);
  if(count($employees)!==count($ids))throw new InvalidArgumentException('An employee no longer exists. Refresh the directory.');
  foreach($employees as $e){$id=(int)$e['user_id'];if($e['deleted_at']||$e['role']==='super_admin'||$id===(int)$actor['id'])throw new InvalidArgumentException('Protected or archived accounts cannot be included.');if((int)($in['versions'][$id]??-1)!==(int)$e['version'])throw new InvalidArgumentException('An employee changed. Refresh the directory and select again.');}
  if($operation==='assign') {
   foreach(['departments'=>$department,'designations'=>$designation,'roles'=>$role] as $table=>$id)if($id){$q=$db->prepare("SELECT id FROM $table WHERE id=? AND active=1");$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Choose active assignments.');}
   foreach($employees as $e){$dep=$department?:$e['department_id'];$des=$designation?:$e['designation_id'];if($des){$q=$db->prepare('SELECT department_id FROM designations WHERE id=?');$q->execute([$des]);$bound=$q->fetchColumn();if($bound&&((int)$bound!==(int)$dep))throw new InvalidArgumentException('A designation belongs to another department. Choose a compatible designation for this selection.');}}
  }
  foreach($employees as $e){$id=(int)$e['user_id'];
   if($operation==='delete'){$db->prepare('UPDATE employees SET deleted_at=NOW(),version=version+1 WHERE user_id=?')->execute([$id]);$db->prepare('UPDATE users SET active=0,session_version=session_version+1 WHERE id=?')->execute([$id]);}
   else {
    if($department&&(int)$e['department_id']!==$department&&function_exists('att_ready')&&att_ready($db))att_record_write($db,$actor,'department_history',['title'=>'Department change'],['date'=>date('Y-m-d'),'before'=>(int)$e['department_id'],'after'=>$department],$id);
    $sets=['version=version+1'];$args=[];if($department){$sets[]='department_id=?';$args[]=$department;}if($designation){$sets[]='designation_id=?';$args[]=$designation;}$args[]=$id;$db->prepare('UPDATE employees SET '.implode(',',$sets).' WHERE user_id=?')->execute($args);
    if($role){$db->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?) ON DUPLICATE KEY UPDATE role_id=VALUES(role_id)')->execute([$id,$role]);$db->prepare('UPDATE users SET session_version=session_version+1 WHERE id=?')->execute([$id]);}
   }
   faudit($db,$actor,'employee.bulk_'.$operation,$id);
  }
  $db->commit();return count($ids).($operation==='delete'?' employees deleted from the directory. Logins disabled; historical records retained.':' employee assignments updated.');
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
