<?php
require_once __DIR__.'/core.php';
/** Curated aliases only. No fuzzy matching or employee/role reassignment. */
function org_catalog():array {
 return [
 'Management'=>['CEO','Managing Director','Director','General Manager','Senior Manager','Manager','Senior Team Leader','Team Leader'],
 'Operations'=>['Senior Operations Manager','Assistant Manager','Senior Team Leader – Operations','Team Leader – Operations'],
 'BCL'=>['Team Lead – BCL','Senior BCL Executive','BCL Executive'],
 'Database Administration (DBA)'=>['Database Administrator Manager','Senior Team Lead – Database Administrator','Team Leader – Database Administrator','Database Administrator'],
 'Quality'=>['Quality Manager','Senior Quality Team Lead','Quality Analyst'],
 'Research'=>['Research Manager','Team Leader – Research','Research Analyst'],
 'Demand Generation'=>['Demand Generation Manager','Team Leader – Demand Generation','Demand Generation Executive'],
 'Email Marketing'=>['Email Marketing Manager','Team Lead – Email Marketing','Email Marketing Executive'],
 'Marketing'=>['Marketing Manager','Team Lead – Marketing','Marketing Executive','Marketing Intern'],
 'Web Development'=>['Web Development Manager','Senior Web Developer','Web Developer','Web Developer Intern'],
 'Business Development / Sales'=>['Sales Manager','Business Development Manager','Sales & Account Manager','Business Development Representative','Sales Development Representative'],
 'Client Success'=>['Client Success Manager','Senior Client Success Executive','Client Success Executive'],
 'Human Resources'=>['HR Manager','Senior HR Executive','HR Executive'],
 'IT'=>['IT Manager','IT Support Engineer'],
 'Finance & Accounts'=>['Finance Manager','Accountant'],
 'Administration'=>['Administration Manager','Office Administrator','Office Boy']
 ];
}
function org_key(string $name):string {
 $name=strtolower(trim($name));$name=str_replace(['–','—','-','_','.','/','(',')'], ' ', $name);$name=str_replace('&',' and ',$name);
 $name=preg_replace('/\\bsr\\b/','senior',$name);$name=preg_replace('/\\bteam lead\\b/','team leader',$name);
 return trim(preg_replace('/\\s+/u',' ',$name));
}
function org_name(string $kind,string $name):string {
 $aliases=$kind==='departments'?[
  'Marketing'=>['Digital Marketing'],
  'Operations'=>['Operation'],
  'Quality'=>['QA','Quality Assurance'],
  'Demand Generation'=>['Demand Gen'],
  'Administration'=>['Admin'],
  'Human Resources'=>['HR','Human Resource'],
  'Database Administration (DBA)'=>['DBA','Database Administration'],
  'Finance & Accounts'=>['Finance and Accounts','Finance & Accounting','Accounts','Finance'],
  'Business Development / Sales'=>['Business Development and Sales','Business Development','Sales'],
  'IT'=>['Information Technology']
 ]:[
  'HR Manager'=>['Human Resources Manager'],
  'Senior HR Executive'=>['Senior Human Resources Executive'],
  'HR Executive'=>['Human Resources Executive'],
  'Assistant Manager'=>['Assitant Manager'],
  'Web Developer Intern'=>['Web Developer Inern'],
  'Marketing Manager'=>['Digital Marketing Manager'],
  'Team Lead – Marketing'=>['Team Lead – Digital Marketing','Team Leader – Digital Marketing'],
  'Marketing Executive'=>['Digital Marketing Executive'],
  'Marketing Intern'=>['Digital Marketing Intern']
 ];
 $names=$kind==='departments'?array_keys(org_catalog()):array_merge(...array_values(org_catalog()));
 foreach($names as $canonical)foreach(array_merge([$canonical],$aliases[$canonical]??[]) as $alias)if(org_key($name)===org_key($alias))return $canonical;
 return trim($name);
}
function org_snapshot(PDO $db):array {
 return ['departments'=>$db->query('SELECT * FROM departments ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'designations'=>$db->query('SELECT * FROM designations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'employees'=>$db->query('SELECT user_id,department_id,designation_id,version,deleted_at FROM employees ORDER BY user_id')->fetchAll(PDO::FETCH_ASSOC)];
}
function org_fingerprint(array $snapshot):string{return hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR));}
/** Pure plan. Conflicted records are skipped; no merging, deletion or ID changes. */
function org_plan(array $snapshot):array {
 $plan=['fingerprint'=>org_fingerprint($snapshot),'departments'=>[],'designations'=>[],'conflicts'=>[],'unassigned_employees'=>0];
 $deps=$snapshot['departments'];$designations=$snapshot['designations'];$employees=$snapshot['employees'];
 foreach($employees as $e)if(!$e['department_id']&&$e['designation_id'])$plan['unassigned_employees']++;
 foreach(org_catalog() as $department=>$titles){
  $matches=array_values(array_filter($deps,fn($r)=>org_key(org_name('departments',$r['name']))===org_key($department)));
  $item=['name'=>$department,'id'=>null,'action'=>'add','before'=>null];
  if(count($matches)>1){$item['action']='conflict';$plan['conflicts'][]=$department.': multiple matching department IDs '.implode(', ',array_column($matches,'id')).'. No merge or rename performed.';}
  elseif($matches){$r=$matches[0];$item['id']=(int)$r['id'];$item['before']=$r['name'];$item['action']=$r['active']?($r['name']===$department?'reuse':'rename'):'conflict';if(!$r['active'])$plan['conflicts'][]=$department.': existing department #'.$r['id'].' is inactive; it was not reactivated.';}
  $plan['departments'][]=$item;
  foreach($titles as $title){
   $entry=['name'=>$title,'department'=>$department,'department_id'=>$item['id'],'id'=>null,'before'=>null,'before_department_id'=>null,'action'=>'add'];
   if($item['action']==='conflict'){$entry['action']='conflict';$plan['designations'][]=$entry;continue;}
   $matches=array_values(array_filter($designations,fn($r)=>org_key(org_name('designations',$r['name']))===org_key($title)));
   // Identical titles explicitly scoped to other departments may be legitimate.
   $scoped=array_values(array_filter($matches,fn($r)=>$item['id']!==null&&(int)$r['department_id']===$item['id']));
   $unbound=array_values(array_filter($matches,fn($r)=>$r['department_id']===null));
   $candidates=$scoped?array_merge($scoped,$unbound):($unbound?:$matches);
   if(count($candidates)>1){$entry['action']='conflict';$plan['conflicts'][]=$title.': multiple matching designation IDs '.implode(', ',array_column($candidates,'id')).'. Existing records retained.';}
   elseif($candidates){
    $r=$candidates[0];$entry['id']=(int)$r['id'];$entry['before']=$r['name'];$entry['before_department_id']=$r['department_id']===null?null:(int)$r['department_id'];
    $incompatible=array_filter($employees,fn($e)=>(int)$e['designation_id']===(int)$r['id']&&$e['department_id']!==null&&($item['id']===null||(int)$e['department_id']!==$item['id']));
    if(!$r['active']||$incompatible){$entry['action']='conflict';$plan['conflicts'][]=$title.': designation #'.$r['id'].(!$r['active']?' is inactive.':(' is used by '.count($incompatible).' employee(s) linked to another department.')).' No assignment was changed.';}
    else{$rename=$r['name']!==$title;$link=$item['id']===null||(int)$r['department_id']!==$item['id'];$entry['action']=$rename&&$link?'rename + link':($rename?'rename':($link?'link':'reuse'));}
   }
   $plan['designations'][]=$entry;
  }
 }
 return $plan;
}
function org_authorize(PDO $db,array $actor):void {
 foreach(['departments.view','departments.create','departments.edit','designations.view','designations.create','designations.edit'] as $permission)if(!can($db,$actor,$permission))throw new InvalidArgumentException('Organization update requires department and designation view, create and edit permissions.');
}
function org_apply(PDO $db,array $actor,string $fingerprint):array {
 org_authorize($db,$actor);$db->beginTransaction();
 try{
  // Same lock used by existing employee, settings and import writes.
  $db->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();
  $snapshot=org_snapshot($db);$plan=org_plan($snapshot);
  if(!hash_equals($plan['fingerprint'],$fingerprint))throw new InvalidArgumentException('Organization records or employee assignments changed. Preview again before applying.');
  $ids=[];$changes=0;
  foreach($plan['departments'] as &$item){
   if($item['action']==='conflict')continue;
   if($item['action']==='add'){$db->prepare('INSERT INTO departments(name) VALUES(?)')->execute([$item['name']]);$item['id']=(int)$db->lastInsertId();$changes++;}
   elseif($item['action']==='rename'){$db->prepare('UPDATE departments SET name=? WHERE id=?')->execute([$item['name'],$item['id']]);$changes++;}
   $ids[$item['name']]=$item['id'];if($item['action']!=='reuse')faudit($db,$actor,'organization.department.'.$item['action'],$item['id']);
  }unset($item);
  foreach($plan['designations'] as &$item){
   if($item['action']==='conflict')continue;$department=$ids[$item['department']];$item['department_id']=$department;
   if($item['action']==='add'){$db->prepare('INSERT INTO designations(name,department_id) VALUES(?,?)')->execute([$item['name'],$department]);$item['id']=(int)$db->lastInsertId();$changes++;}
   elseif($item['action']!=='reuse'){$db->prepare('UPDATE designations SET name=?,department_id=? WHERE id=?')->execute([$item['name'],$department,$item['id']]);$changes++;}
   if($item['action']!=='reuse')faudit($db,$actor,'organization.designation.'.str_replace(' + ','_',$item['action']),$item['id']);
  }unset($item);
  $after=org_snapshot($db);if($after['employees']!==$snapshot['employees'])throw new RuntimeException('Employee assignment preservation check failed.');
  $plan['changed']=$changes;$plan['employee_assignments_preserved']=true;$plan['applied_at']=date('c');$plan['remaining']=org_plan($after)['conflicts'];
  // Store the exact runtime outcome in the existing generic-record/audit system.
  $db->prepare("INSERT INTO hr_records(module,title,status,data,created_by) VALUES('organization_update',?,'Applied',?,?)")->execute(['Department and designation catalog',json_encode($plan,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$actor['id']]);
  $plan['report_id']=(int)$db->lastInsertId();faudit($db,$actor,'organization.catalog.applied',$plan['report_id']);$db->commit();return $plan;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function org_duplicate_check(PDO $db,string $kind,string $name,int $id,?int $department=null):void {
 $key=org_key(org_name($kind,$name));
 foreach($db->query('SELECT * FROM '.$kind) as $r){
  if((int)$r['id']===$id)continue;
  if($kind==='designations'&&$r['department_id']!==null&&$department!==null&&(int)$r['department_id']!==$department)continue;
  if(org_key(org_name($kind,$r['name']))===$key)throw new InvalidArgumentException('An equivalent '.$kind.' record already exists (#'.$r['id'].': '.$r['name'].'). Reuse it instead of creating a duplicate.');
 }
}
function org_designation_allowed(array $designation,int $department,?array $old):bool {
 if(!$designation['active'])return false;
 if($designation['department_id']!==null)return (int)$designation['department_id']===$department;
 // Preserve an existing unbound assignment only when both selected IDs stay unchanged.
 return $old&&(int)$old['designation_id']===(int)$designation['id']&&(int)$old['department_id']===$department;
}
