<?php
// Read-only device events: never infer IN/OUT or change processing results here.
try {
 $from=chr_date((string)($_GET['from']??date('Y-m-d')));
 $to=chr_date((string)($_GET['to']??$from));
 if($from>$to)throw new InvalidArgumentException('End date must follow start date.');
 $until=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
 $type=(string)($_GET['punch_type']??'');$status=(string)($_GET['processing_status']??'');
 $types=[''=>'All punch types','in'=>'Check In','out'=>'Check Out','unknown'=>'Unknown / Raw Punch'];
 $statuses=[''=>'All processing statuses','Processed'=>'Processed','Pending'=>'Pending','Unmapped'=>'Unmapped','Failed'=>'Failed'];
 if(!array_key_exists($type,$types)||!array_key_exists($status,$statuses))throw new InvalidArgumentException('Choose a valid punch type and processing status.');
 $state="CASE WHEN x.status='Processed' THEN 'Processed' WHEN x.status='Needs mapping' THEN 'Unmapped' WHEN x.status IS NULL OR x.status IN ('Pending','Manual protected') THEN 'Pending' ELSE 'Failed' END";
 $where=['p.punched_at>=?','p.punched_at<?'];$params=[$from,$until];
 foreach(['employee_id'=>'x.employee_id','department_id'=>'e.department_id','device_code'=>'p.device_code'] as $key=>$column){
  if(($_GET[$key]??'')!==''){$where[]=$column.'=?';$params[]=(string)$_GET[$key];}
 }
 if($type!==''){$where[]='p.direction=?';$params[]=$type;}
 if($status!==''){$where[]=$state.'=?';$params[]=$status;}
 $number=max(1,min(100000,(int)($_GET['p']??1)));$offset=($number-1)*100;
 $q=$pdo->prepare("SELECT p.*,x.employee_id,x.attendance_date,x.status original_status,x.error_message,u.name,e.employee_code,d.name department,a.shift_snapshot,s.title shift_title,".$state." display_status
 FROM hr_punches p LEFT JOIN hr_punch_processing x ON x.punch_id=p.id
 LEFT JOIN users u ON u.id=x.employee_id LEFT JOIN employees e ON e.user_id=x.employee_id
 LEFT JOIN departments d ON d.id=e.department_id
 LEFT JOIN hr_attendance a ON a.employee_id=x.employee_id AND a.attendance_date=x.attendance_date
 LEFT JOIN hr_records s ON s.id=a.shift_id AND s.module='shifts'
 WHERE ".implode(' AND ',$where)." ORDER BY p.punched_at DESC,p.id DESC LIMIT 101 OFFSET ".$offset);
 $q->execute($params);$punchRows=$q->fetchAll(PDO::FETCH_ASSOC);$hasNext=count($punchRows)>100;$punchRows=array_slice($punchRows,0,100);
} catch(InvalidArgumentException $e){echo '<div class="alert error">'.h($e->getMessage()).'</div>';return;}
$deviceChoices=[''=>'All devices'];$devicesByCode=[];foreach($devices as $device){$code=$device['values']['device_code'];$deviceChoices[$code]=$device['title'].' · '.$code;$devicesByCode[$code]=$device;}
?>
<form class="card form-grid" method="get">
<?php chr_hidden('page',$page);chr_select('employee_id','Employee',$_GET['employee_id']??'',[''=>'All employees']+$historyEmployeeOptions);chr_select('department_id','Department',$_GET['department_id']??'',[''=>'All departments']+$departmentOptions);chr_select('device_code','Device',$_GET['device_code']??'',$deviceChoices);field('from','From',['from'=>$from],'date',true);field('to','To',['to'=>$to],'date',true);chr_select('punch_type','Punch Type',$type,$types);chr_select('processing_status','Processing Status',$status,$statuses);?>
<div><button class="button primary">Apply filters</button> <a class="button" href="?page=<?=h($page)?>">Reset</a></div></form>
<section class="card"><p>Individual device punches, newest punch time first. Times use <?=h(date_default_timezone_get())?>. Unknown / Raw Punch means the device did not supply a check-in or check-out direction. Department is the employee’s current department.</p>
<div class="table-scroll attendance-table-scroll" tabindex="0" role="region" aria-label="Biometric punch activity"><table class="directory" data-freeze-first="true"><thead><tr>
<?php foreach(['Employee','Employee ID','Department','Date','Punch Time','Punch Type','Biometric User ID','Device','Device Location','Shift','Processing Status'] as $label):?><th><?=h($label)?></th><?php endforeach;?>
</tr></thead><tbody>
<?php if(!$punchRows):?><tr><td colspan="11">No device punches in this date range. Check Sync Logs for connection details, or select an earlier range for historical punches.</td></tr><?php endif;
foreach($punchRows as $r):$device=$devicesByCode[$r['device_code']]??null;$snapshot=json_decode($r['shift_snapshot']??'null',true);?>
<tr><td><?=h($r['name']?:'Not linked')?></td><td><?=h($r['employee_code']?:'—')?></td><td><?=h($r['department']?:'—')?></td><td><?=h(substr($r['punched_at'],0,10))?></td><td><?=h(substr($r['punched_at'],11))?></td><td><?=h($types[$r['direction']]??'Unknown / Raw Punch')?></td><td><?=h($r['biometric_id'])?></td><td><?=h($device['title']??$r['device_code'])?><small><?=h($r['device_code'])?></small></td><td><?=h($device['values']['location']??'—')?></td><td><?=h($r['shift_title']?:'—')?><?php if(isset($snapshot['start'],$snapshot['end'])):?><small><?=h($snapshot['start'].' – '.$snapshot['end'])?></small><?php endif;?></td><td><?=h($r['display_status'])?><small class="punch-detail"><?=h($r['original_status']??'Not yet processed')?><?=!empty($r['error_message'])?' · '.h($r['error_message']):''?></small></td></tr>
<?php endforeach;?></tbody></table></div>
<nav class="pagination" aria-label="Punch log pages"><?php if($number>1):?><a class="button" href="?<?=h(http_build_query(array_merge($_GET,['page'=>$page,'p'=>$number-1])))?>">Previous</a><?php endif;?><span>Page <?=$number?></span><?php if($hasNext):?><a class="button" href="?<?=h(http_build_query(array_merge($_GET,['page'=>$page,'p'=>$number+1])))?>">Next</a><?php endif;?></nav></section>
