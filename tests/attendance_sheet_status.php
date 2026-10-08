<?php
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='peopleflow_ci')exit('Disposable CI database only.');
require dirname(__DIR__).'/auth.php';require dirname(__DIR__).'/includes/core-hr/controller.php';
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function blocked(callable $fn,string $message):void{$failed=false;try{$fn();}catch(InvalidArgumentException $e){$failed=true;}check($failed,$message);}
$db=db();$admin=['id'=>1,'role'=>'super_admin'];$viewer=['id'=>3,'role'=>'employee'];
$fixture=json_decode(file_get_contents('/tmp/core-hr-fixture.json'),true);$employee=(int)$fixture['employee'];
function values(PDO $db,int $employee,string $day,string $status):array{return ['employee_id'=>$employee,'attendance_date'=>$day,'sheet_status'=>$status,'notes'=>'CI sheet correction','fingerprint'=>att_sheet_fingerprint($db,$employee,$day)];}
$day='2026-02-02';$q=$db->prepare('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$employee,$day]);$before=$q->fetch(PDO::FETCH_ASSOC);check((bool)$before,'Missing punch fixture');
$input=values($db,$employee,$day,'Absent');blocked(fn()=>att_sheet_save($db,$viewer,$input),'Permission bypass');
att_sheet_save($db,$admin,$input);$r=chr_report($db,$day,$day,['employee_id'=>$employee])[0];check($r['status']==='Absent'&&$r['absent']===1,'Direct absence not reflected in report');
$q->execute([$employee,$day]);check($q->fetch(PDO::FETCH_ASSOC)===$before,'Original punches were altered');blocked(fn()=>att_sheet_save($db,$admin,$input),'Stale status write accepted');
att_sheet_save($db,$admin,values($db,$employee,$day,'Half Day'));$r=chr_report($db,$day,$day,['employee_id'=>$employee])[0];check($r['present']===0.5&&$r['absent']===0.5,'Half day totals incorrect');
att_sheet_save($db,$admin,values($db,$employee,$day,'Automatic'));check(chr_report($db,$day,$day,['employee_id'=>$employee])[0]['status']===$before['status'],'Automatic status did not restore underlying attendance');
blocked(fn()=>att_sheet_save($db,$admin,values($db,$employee,date('Y-m-d',strtotime('+1 day')),'Present')),'Future attendance accepted');
foreach(['CI_SHEET_A','CI_SHEET_B'] as $code)chr_save_config($db,$admin,'leave_policy',['title'=>$code,'type'=>$code,'active'=>1,'paid'=>1,'annual_days'=>10,'allow_negative'=>1,'carry_forward'=>0,'backdated_leave_allowed'=>1,'backdated_limit'=>365,'requires_approval'=>1]);
$leaveDay='2026-02-18';
att_sheet_save($db,$admin,values($db,$employee,$leaveDay,'leave:CI_SHEET_A'));$first=att_sheet_leaves($db,$employee,$leaveDay)[0];check($first['status']==='Approved','Authorized leave not approved');
$r=chr_report($db,$leaveDay,$leaveDay,['employee_id'=>$employee])[0];check($r['leave_codes']==='CI_SHEET_A'&&$r['paid_leave']==1,'Leave code / paid leave missing');
$invalid=values($db,$employee,$leaveDay,'leave:INVALID')+['replace_leave'=>1];blocked(fn()=>att_sheet_save($db,$admin,$invalid),'Invalid leave accepted');check(att_sheet_leaves($db,$employee,$leaveDay)[0]['id']===$first['id'],'Failed replacement cancelled old leave');
$replace=values($db,$employee,$leaveDay,'leave:CI_SHEET_B');blocked(fn()=>att_sheet_save($db,$admin,$replace),'Unconfirmed replacement accepted');
att_sheet_save($db,$admin,$replace+['replace_leave'=>1]);check(chr_used($db,$employee,'CI_SHEET_A',2026)===0.0&&chr_used($db,$employee,'CI_SHEET_B',2026)===1.0,'Replacement usage incorrect');
att_sheet_save($db,$admin,values($db,$employee,$leaveDay,'Automatic')+['replace_leave'=>1]);check(!att_sheet_leaves($db,$employee,$leaveDay),'Restore did not cancel single-day leave');
echo "PASS: direct sheet statuses, optimistic locking, permissions, punch preservation, leave replacement, rollback and usage accounting.\n";
