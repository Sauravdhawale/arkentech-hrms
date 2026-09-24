<?php
function att_active_employee(PDO $db,int $id):array {
 $e=chr_employee($db,$id);$q=$db->prepare('SELECT active FROM users WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn()||!empty($e['deleted_at']))throw new InvalidArgumentException('Choose an active employee.');return $e;
}
function att_manual_punch(PDO $db,array $actor,array $in):int {
 if(!can($db,$actor,'attendance.manage'))throw new InvalidArgumentException('Attendance editing is not permitted.');
 $employee=(int)($in['employee_id']??0);att_active_employee($db,$employee);$day=chr_date((string)($in['attendance_date']??''));$at=chr_datetime((string)($in['punched_at']??''));$action=$in['punch_action']??'';$reason=ftext($in,'notes',3000,true);
 if(!in_array($action,['Check In','Check Out'],true))throw new InvalidArgumentException('Choose Check In or Check Out.');
 $nonce=(string)($in['request_key']??'');if(!preg_match('/^[a-f0-9]{64}$/D',$nonce))throw new InvalidArgumentException('Reload the punch form.');$key=hash('sha256',$actor['id'].':'.$nonce);
 $db->beginTransaction();try{chr_lock($db);$q=$db->prepare('SELECT id FROM hr_manual_punch_events WHERE request_key=?');$q->execute([$key]);if($q->fetchColumn())throw new InvalidArgumentException('This punch was already saved.');
 $q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=? FOR UPDATE');$q->execute([$employee,$day]);$old=$q->fetch(PDO::FETCH_ASSOC);
 if($action==='Check In'&&$old)throw new InvalidArgumentException('A check-in already exists. Use the audited correction form.');
 if($action==='Check Out'&&(!$old||$old['check_out']))throw new InvalidArgumentException('Check-out requires an existing open check-in.');
 $id=chr_save_attendance($db,$actor,['id'=>$old['id']??0,'version'=>$old['version']??0,'employee_id'=>$employee,'attendance_date'=>$day,'check_in'=>$old['check_in']??$at,'check_out'=>$action==='Check Out'?$at:null,'source'=>'Admin Manual','notes'=>$reason]);
 $db->prepare('INSERT INTO hr_manual_punch_events(employee_id,attendance_date,action,punched_at,source,actor_id,notes,request_key) VALUES(?,?,?,?,?,?,?,?)')->execute([$employee,$day,$action,$at,'Admin Manual',$actor['id'],$reason,$key]);$db->commit();return $id;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function att_import_rows(PDO $db,array $rows):array {
 $result=[];$seen=[];foreach($rows as $i=>$r){$item=['line'=>$i+2,'input'=>$r,'data'=>null,'error'=>''];try{
 $code=trim((string)($r['employee_id']??''));$q=$db->prepare('SELECT user_id FROM employees WHERE employee_code=? AND deleted_at IS NULL');$q->execute([$code]);$id=(int)$q->fetchColumn();if(!$id)throw new InvalidArgumentException('Employee ID not found.');att_active_employee($db,$id);
 $day=chr_date(trim((string)($r['date']??'')));$key=$id.':'.$day;if(isset($seen[$key]))throw new InvalidArgumentException('Duplicate employee/date in this file.');$seen[$key]=true;
 $q=$db->prepare('SELECT id FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$id,$day]);if($q->fetchColumn())throw new InvalidArgumentException('Attendance already exists; use the correction form.');
 if(empty($r['check_in'])&&($r['status']??'')==='Absent'){$item['data']=['absence'=>true,'employee_id'=>$id,'attendance_date'=>$day,'notes'=>ftext($r,'notes',3000,true)];$result[]=$item;continue;}
 $shift=chr_assignment($db,$id,$day);$in=trim((string)($r['check_in']??''));$out=trim((string)($r['check_out']??''));$clock='/^\d{2}:\d{2}(:\d{2})?$/D';if(preg_match($clock,$in))$in=$day.' '.$in;$in=chr_datetime($in);
 if($out!==''&&preg_match($clock,$out)){$outDay=$day;if(substr($out,0,5)<=substr($in,11,5)){if(!$shift||$shift['values']['end']>$shift['values']['start'])throw new InvalidArgumentException('Earlier check-out requires an assigned overnight shift.');$outDay=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');}$out=$outDay.' '.$out;}$out=$out===''?null:chr_datetime($out);
 if($in>date('Y-m-d H:i:s')||($out&&$out>date('Y-m-d H:i:s')))throw new InvalidArgumentException('Future punches are not allowed.');$metrics=chr_calculate($day,$in,$out,$shift['values']??null);
 if(!empty($r['shift'])&&trim($r['shift'])!==($shift['title']??''))throw new InvalidArgumentException('Shift differs from effective assignment.');if(!empty($r['status'])&&trim($r['status'])!==$metrics['status'])throw new InvalidArgumentException('Status differs from calculated status: '.$metrics['status']);
 $item['data']=['employee_id'=>$id,'attendance_date'=>$day,'check_in'=>$in,'check_out'=>$out,'source'=>'CSV Import','notes'=>ftext($r,'notes',3000)];
 }catch(InvalidArgumentException $e){$item['error']=$e->getMessage();}$result[]=$item;}return $result;
}
function att_import_preview(PDO $db,array $actor,array $file):void {
 if(!can($db,$actor,'attendance.import')||!can($db,$actor,'attendance.manage'))throw new InvalidArgumentException('Attendance import and editing permissions are required.');
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>2097152||!is_uploaded_file($file['tmp_name']??''))throw new InvalidArgumentException('Upload a CSV or XLSX file up to 2 MB.');
 $extension=strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION));
 if($extension==='xlsx'){
  require_once __DIR__.'/xlsx.php';$rows=att_xlsx_rows($file['tmp_name']);
  $_SESSION['attendance_import']=['actor'=>$actor['id'],'expires'=>time()+1800,'nonce'=>bin2hex(random_bytes(32)),'rows'=>$rows,'preview'=>att_import_rows($db,$rows)];
  return;
 }
 if($extension!=='csv')throw new InvalidArgumentException('Choose a CSV or XLSX file.');
 $handle=fopen($file['tmp_name'],'r');try{$header=fgetcsv($handle,0,',','"','');if(!$header)throw new InvalidArgumentException('Empty CSV file.');$header=array_map(fn($v)=>strtolower(trim(ltrim($v,"\xEF\xBB\xBF"))),$header);if(count($header)!==count(array_unique($header))||array_diff(['employee_id','date','check_in','check_out'],$header))throw new InvalidArgumentException('Required CSV columns: employee_id,date,check_in,check_out.');$rows=[];while(($row=fgetcsv($handle,0,',','"',''))!==false){if($row===[null])continue;if(count($rows)>=1000)throw new InvalidArgumentException('Maximum 1,000 rows per import.');if(count($row)!==count($header))throw new InvalidArgumentException('CSV column count differs on line '.(count($rows)+2));$rows[]=array_combine($header,$row);}if(!$rows)throw new InvalidArgumentException('No attendance rows found.');
 $_SESSION['attendance_import']=['actor'=>$actor['id'],'expires'=>time()+1800,'nonce'=>bin2hex(random_bytes(32)),'rows'=>$rows,'preview'=>att_import_rows($db,$rows)];
 }finally{fclose($handle);}
}
function att_import_commit(PDO $db,array $actor,array $in):int {
 if(!can($db,$actor,'attendance.import')||!can($db,$actor,'attendance.manage'))throw new InvalidArgumentException('Attendance import and editing permissions are required.');$batch=$_SESSION['attendance_import']??null;
 if(!$batch||(int)$batch['actor']!==(int)$actor['id']||$batch['expires']<time()||!hash_equals($batch['nonce'],(string)($in['import_nonce']??'')))throw new InvalidArgumentException('Import preview expired. Upload the CSV again.');
 $db->beginTransaction();try{chr_lock($db);$rows=att_import_rows($db,$batch['rows']);foreach($rows as $r)if($r['error']!=='')throw new InvalidArgumentException('Import not saved. Line '.$r['line'].': '.$r['error']);foreach($rows as $r){if(!empty($r['data']['absence']))att_save_absence($db,$actor,$r['data']);else chr_save_attendance($db,$actor,$r['data']);}faudit($db,$actor,'attendance.csv.imported');$db->commit();unset($_SESSION['attendance_import']);return count($rows);}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function att_save_absence(PDO $db,array $actor,array $in):int {
 if(!can($db,$actor,'attendance.manage'))throw new InvalidArgumentException('Attendance editing is not permitted.');$employee=(int)($in['employee_id']??0);att_active_employee($db,$employee);$day=chr_date((string)($in['attendance_date']??''));if($day>date('Y-m-d'))throw new InvalidArgumentException('Future absence cannot be marked.');$notes=ftext($in,'notes',3000,true);
 $owns=!$db->inTransaction();if($owns)$db->beginTransaction();try{chr_lock($db);$q=$db->prepare('SELECT id FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$employee,$day]);if($q->fetchColumn())throw new InvalidArgumentException('Punches exist. Review the attendance correction instead.');foreach(chr_rows($db,'attendance_status') as $r)if((int)$r['employee_id']===$employee&&($r['values']['date']??'')===$day&&$r['status']==='Active')throw new InvalidArgumentException('Absence is already recorded.');$context=chr_report_context($db,$day,$day);$status=chr_day($context,chr_employee($db,$employee),$day);if(in_array($status['status'],['Holiday','Week Off','Leave','Half Day Leave','Not started','Not active'],true))throw new InvalidArgumentException('Review the employee schedule or approved leave before recording absence.');$id=att_record_write($db,$actor,'attendance_status',['title'=>'Manual absence '.$day],['date'=>$day,'status'=>'Absent','notes'=>$notes,'source'=>'Admin Manual'],$employee);if($owns)$db->commit();return $id;}catch(Throwable $e){if($owns&&$db->inTransaction())$db->rollBack();throw $e;}
}
