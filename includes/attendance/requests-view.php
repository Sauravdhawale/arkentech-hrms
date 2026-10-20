<?php
$requestStatus=(string)($_GET['status']??'Pending');
$statuses=[''=>'All requests','Pending'=>'Pending review','Approved'=>'Validated','Rejected'=>'Rejected','Cancelled'=>'Cancelled'];
if(!array_key_exists($requestStatus,$statuses))$requestStatus='Pending';
$requestEmployee=(int)($_GET['employee_id']??0);$where=["r.kind='regularisation'"];$args=[];
if($requestStatus!==''){$where[]='r.status=?';$args[]=$requestStatus;}
if($requestEmployee){$where[]='r.user_id=?';$args[]=$requestEmployee;}
$base=' FROM hr_requests r JOIN users u ON u.id=r.user_id WHERE '.implode(' AND ',$where);
$q=$pdo->prepare('SELECT COUNT(*)'.$base);$q->execute($args);$requestTotal=(int)$q->fetchColumn();
$requestPages=max(1,(int)ceil($requestTotal/25));$requestPage=min($requestPages,max(1,(int)($_GET['p']??1)));
$q=$pdo->prepare('SELECT r.*,u.name'.$base.' ORDER BY r.id DESC LIMIT 25 OFFSET '.(($requestPage-1)*25));$q->execute($args);$requests=$q->fetchAll(PDO::FETCH_ASSOC);
?>
<form method="get" class="card request-filters" aria-label="Filter attendance requests">
<?php chr_hidden('page','attendance_requests');chr_select('status','Request status',$requestStatus,$statuses);chr_select('employee_id','Employee',$requestEmployee,[''=>'All employees']+$historyEmployeeOptions);?>
<button class="button primary">Apply filters</button></form>
<section class="card"><div class="card-heading"><div><h2>Attendance corrections <span class="badge"><?=$requestTotal?></span></h2><p>Review one request, check the times, then validate or reject it. Validation saves the corrected attendance; rejection leaves attendance unchanged.</p></div></div>
<?php if(!$requests):?><div class="empty-state"><h3><?=$requestStatus==='Pending'?'No requests waiting for review':'No matching requests'?></h3><p>Employee correction requests appear here after submission. Choose All requests to see past decisions.</p></div><?php endif;?>
<div class="request-list">
<?php foreach($requests as $request):$requestLabel=$request['status']==='Approved'?'Validated':$request['status'];$singleDay=$request['start_date']===$request['end_date'];$requestError=$error&&($_POST['action']??'')==='att_review_request'&&(int)($_POST['request_id']??0)===(int)$request['id'];?>
<details class="request-item" <?=$requestError?'open':''?>><summary><span class="request-person"><strong><?=h($request['name'])?></strong><small><?=h($request['start_date'].($singleDay?'':' → '.$request['end_date']))?> · <?=h($request['subject'])?></small></span><?php fbadge($requestLabel,$request['status']==='Approved');?></summary>
<div class="request-body"><p><strong>Employee’s reason</strong><br><?=nl2br(h($request['details']))?></p>
<?php if(!$singleDay):?><p class="alert">This covers more than one day. Each correction must be reviewed separately; ask the employee to submit one request per day.</p><?php endif;?>
<?php if($request['status']==='Pending'&&can($pdo,$user,'attendance.approve')):
$q=$pdo->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$request['user_id'],$request['start_date']]);$current=$q->fetch(PDO::FETCH_ASSOC)?:[];$v=$requestError?$_POST:($current+['attendance_date'=>$request['start_date']]);?>
<form method="post" class="form-grid"><?php ftoken();chr_hidden('action','att_review_request');chr_hidden('request_id',$request['id']);chr_hidden('attendance_id',$current['id']??0);chr_hidden('attendance_version',$current['version']??0);choose('decision','Decision',$requestError?$_POST:[],['Validated','Rejected']);field('attendance_date','Correction date',$v,'date',true);field('check_in','Correct check-in',$v,'datetime-local');field('check_out','Correct check-out',$v,'datetime-local');field('notes','Review reason',$requestError?$_POST:[],'text',true,3000);?>
<p class="full hint">Times use <?=h(date_default_timezone_get())?>. For an overnight shift, check-out may be on the following day. A review reason is required for either decision.</p>
<div class="form-actions"><button class="button primary">Save decision</button></div></form>
<?php endif;?></div></details><?php endforeach;?></div>
<?php if($requestPages>1):?><nav class="request-pagination" aria-label="Attendance request pages"><?php $requestQuery=['page'=>'attendance_requests','status'=>$requestStatus,'employee_id'=>$requestEmployee];if($requestPage>1):?><a class="button" href="?<?=h(http_build_query($requestQuery+['p'=>$requestPage-1]))?>">Previous</a><?php endif;?><span>Page <?=$requestPage?> of <?=$requestPages?></span><?php if($requestPage<$requestPages):?><a class="button" href="?<?=h(http_build_query($requestQuery+['p'=>$requestPage+1]))?>">Next</a><?php endif;?></nav><?php endif;?></section>
