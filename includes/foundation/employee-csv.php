<?php
function employee_csv_read(PDO $db,string $path):array {
 $f=fopen($path,'r');if(!$f)throw new InvalidArgumentException('Cannot read CSV.');
 try{$header=fgetcsv($f,0,',','"','');if(!$header)throw new InvalidArgumentException('CSV is empty.');$header=array_map(fn($s)=>strtolower(trim((string)$s,"\xEF\xBB\xBF \t\r\n")),$header);
 $columns=['first_name','last_name','employee_code','email','username','department','designation','joining_date','mobile'];
 if(count(array_unique($header))!==count($header)||array_diff($columns,$header)||array_diff($header,$columns))throw new InvalidArgumentException('Use the downloaded CSV template with its original column headings.');
 $rows=[];$seen=[];$line=1;$role=(int)$db->query("SELECT id FROM roles WHERE slug='employee' AND active=1")->fetchColumn();if(!$role)throw new InvalidArgumentException('An active Employee role is required.');
 while(($cells=fgetcsv($f,0,',','"',''))!==false){$line++;if(count($cells)===1&&trim((string)$cells[0])==='')continue;if(count($rows)>=100)throw new InvalidArgumentException('Upload at most 100 employees per CSV.');if(count($cells)!==count($header))throw new InvalidArgumentException('CSV row '.$line.': wrong number of columns.');$r=array_combine($header,array_map(fn($s)=>trim((string)$s),$cells));
 try{$r['first_name']=ftext($r,'first_name',80,true);$r['last_name']=ftext($r,'last_name',80,true);$r['employee_code']=ftext($r,'employee_code',60);$r['email']=femail($r,'email');$r['mobile']=ftext($r,'mobile',40);$r['username']=strtolower(ftext($r,'username',190));if($r['username']!==''&&!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',$r['username']))throw new InvalidArgumentException('Invalid username.');if(!fdate($r,'joining_date'))throw new InvalidArgumentException('Joining date is required (YYYY-MM-DD).');if(strlen($r['first_name'].' '.$r['last_name'])>150)throw new InvalidArgumentException('Full name is too long.');
 $q=$db->prepare('SELECT id FROM departments WHERE LOWER(TRIM(name))=LOWER(?) AND active=1');$q->execute([$r['department']]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);if(count($ids)!==1)throw new InvalidArgumentException('Department must match one active department in Company Settings.');$r['department_id']=(int)$ids[0];
 $q=$db->prepare('SELECT id FROM designations WHERE LOWER(TRIM(name))=LOWER(?) AND department_id=? AND active=1');$q->execute([$r['designation'],$r['department_id']]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);if(count($ids)!==1)throw new InvalidArgumentException('Designation must belong to the selected department.');$r['designation_id']=(int)$ids[0];
 }catch(InvalidArgumentException $e){throw new InvalidArgumentException('CSV row '.$line.': '.$e->getMessage());}
 $r+=['role_id'=>$role,'employment_type'=>'Full Time','employment_status'=>'Active'];$r['_skip']=employee_csv_exists($db,$r);foreach(['employee_code','email','username'] as $key){$value=strtolower($r[$key]??'');if($value==='')continue;$k=$key.':'.$value;if(isset($seen[$k]))$r['_skip']=true;$seen[$k]=true;}$rows[]=$r;
 }if(!$rows)throw new InvalidArgumentException('Add employee rows below the CSV headings.');return $rows;
 }finally{fclose($f);}
}
function employee_csv_exists(PDO $db,array $r):bool {
 $q=$db->prepare('SELECT u.id FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE (?<>\'\' AND LOWER(u.username)=LOWER(?)) OR (?<>\'\' AND LOWER(u.email)=LOWER(?)) OR (?<>\'\' AND LOWER(e.employee_code)=LOWER(?)) LIMIT 1');$args=[];foreach(['username','email','employee_code'] as $k){$args[]=$r[$k]??'';$args[]=$r[$k]??'';}$q->execute($args);return (bool)$q->fetchColumn();
}
function employee_csv_commit(PDO $db,array $actor,array $rows):array {
 $db->beginTransaction();try{$db->query("SELECT id FROM users WHERE role='super_admin' ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();$created=0;$skipped=0;foreach($rows as $r){if(!empty($r['_skip'])||employee_csv_exists($db,$r)){$skipped++;continue;}save_employee($db,$actor,$r,[]);$created++;}$db->commit();return [$created,$skipped];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
