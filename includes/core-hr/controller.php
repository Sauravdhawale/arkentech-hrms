<?php
require_once __DIR__.'/service.php';
$corePages=['shifts'=>['Shifts','shifts.view'],'roster'=>['Shift assignments','shifts.view'],'holidays'=>['Holidays','holidays.view'],'attendance'=>['Daily attendance','attendance.view'],'attendance_history'=>['Attendance history','attendance.view'],'monthly'=>['Monthly attendance report','attendance.reports'],'leaves'=>['Leave requests','leave.view'],'leave_history'=>['Leave history','leave.view'],'leave_policy'=>['Leave types','leave.view'],'balances'=>['Leave balances','leave.view']];
function chr_handle_post(PDO $db,array $user,string $action,array $in,array $files):string {
 if($action==='core_install'){if($user['role']!=='super_admin')throw new InvalidArgumentException('Super Admin access required.');chr_install($db);faudit($db,$user,'core.installed');return 'Core HR is ready. Existing records were preserved.';}
 if(!chr_ready($db))throw new InvalidArgumentException('Install Core HR first.');
 switch($action){
 case 'core_config':chr_save_config($db,$user,(string)($in['module']??''),$in);return 'Configuration saved.';
 case 'core_delete':chr_delete_config($db,$user,(string)($in['module']??''),(int)($in['id']??0));return 'Record deleted.';
 case 'core_attendance':chr_save_attendance($db,$user,$in);return 'Attendance saved and calculated.';
 case 'core_leave':chr_create_leave($db,$user,$in,$files['attachment']??[]);return 'Leave request submitted for approval.';
 case 'core_review':chr_review_leave($db,$user,(int)($in['id']??0),(string)($in['status']??''));return 'Leave decision saved.';
 default:throw new InvalidArgumentException('Unknown Core HR action.');
 }
}
function chr_export(PDO $db,array $user,string $page):void {
 need($db,$user,'attendance.reports');if(!chr_ready($db)){http_response_code(409);exit('Install Core HR first.');}
 try{[$from,$to]=chr_report_range($page,$_GET);$rows=chr_report($db,$from,$to,$_GET);if($page==='monthly')$rows=chr_monthly($rows);}
 catch(InvalidArgumentException $e){http_response_code(422);exit(h($e->getMessage()));}
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="attendance-'.$from.'.csv"');header('Cache-Control: no-store');$out=fopen('php://output','w');if($rows){$keys=array_keys($rows[0]);fputcsv($out,$keys,',','"','');foreach($rows as $row){$safe=[];foreach($keys as $key){$v=(string)($row[$key]??'');$safe[]=preg_match('/^[\s]*[=+@-]/u',$v)?"'".$v:$v;}fputcsv($out,$safe,',','"','');}}else fputcsv($out,['No matching attendance'],',','"','');fclose($out);exit;
}
function chr_report_range(string $page,array $in):array {
 if($page==='monthly'){$month=$in['month']??date('Y-m');if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))throw new InvalidArgumentException('Choose a valid month.');$from=chr_date($month.'-01');return [$from,date('Y-m-t',strtotime($from))];}
 if($page==='attendance_history')return [chr_date($in['from']??date('Y-m-01')),chr_date($in['to']??date('Y-m-d'))];$day=chr_date($in['date']??date('Y-m-d'));return [$day,$day];
}

require_once dirname(__DIR__).'/attendance/controller.php';
