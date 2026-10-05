<?php
// Only role-checked entry points may load this file.
if (!isset($user)) { http_response_code(404); exit; }
$admin=$user['role']==='super_admin';
$passwordSetup=!$admin&&!empty($user['must_change_password']);
if($passwordSetup&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')!=='change_password'){http_response_code(403);exit('Complete your password setup before using employee features.');}
$nav=$admin?['overview'=>'Overview','employees'=>'People','attendance'=>'Attendance','devices'=>'Biometric devices','shifts'=>'Shifts & rules','regularisation'=>'Regularisation','leaves'=>'Leave management','holidays'=>'Holidays','payroll'=>'Payroll','recruitment'=>'Recruitment','onboarding'=>'Onboarding','performance'=>'Performance & PMS','announcements'=>'Announcements','helpdesk'=>'Helpdesk','reports'=>'Reports','audit'=>'Audit log','offboarding'=>'Offboarding','settings'=>'Company settings','system'=>'System configuration']:['overview'=>'My dashboard','profile'=>'My profile','attendance'=>'My attendance','regularisation'=>'Regularisation','leaves'=>'My leave','holidays'=>'Holiday calendar','payroll'=>'My payslips','onboarding'=>'My onboarding','performance'=>'My performance','announcements'=>'Company notices','helpdesk'=>'My requests','security'=>'Login & security'];
require_once __DIR__.'/suite-definitions.php';
foreach(suite_definitions() as $key=>$def)if($admin||$def[2]!=='admin')$nav[$key]=$def[0];
$nav['attendance']='Attendance records';$nav['monthly']='Monthly attendance';
if($admin){$nav['raw_logs']='Raw biometric logs';$nav['sync']='Sync history';$nav['device_attendance']='Device punch attendance';$nav['device_monthly']='Device punch summary';}
if(!$admin)$nav+=['my_shift'=>'My Shift','attendance_sheet'=>'Attendance Sheet','leave_history'=>'Leave History','inbox'=>'Inbox','notifications'=>'Notifications','my_salary'=>'My Salary','approvals'=>'Leave Approvals'];
$page=$_GET['page'] ?? 'overview';
if(!$admin&&!$passwordSetup&&$page==='profile'){$profileTab=(string)($_GET['tab']??'overview');$page=['overview'=>'profile','shift'=>'my_shift','documents'=>'documents','security'=>'security'][$profileTab]??'profile';}
if (!isset($nav[$page])) { http_response_code(403); exit('This section is not available for your role.'); }
$pdo=db(); $notice=''; $error=''; $ready=true;
try { $pdo->query('SELECT id FROM hr_requests LIMIT 1'); $pdo->query('SELECT id FROM hr_announcements LIMIT 1'); $pdo->query('SELECT id FROM hr_audit LIMIT 1'); } catch(Throwable $e) { $ready=false; }
function audit_action(PDO $pdo,int $actor,string $action,int $record): void { $pdo->prepare('INSERT INTO hr_audit(actor_id,action,record_id) VALUES(?,?,?)')->execute([$actor,$action,$record]); }
function field_text(string $key,int $max,bool $required=true): string { $s=trim((string)($_POST[$key] ?? '')); if (($required && $s==='') || strlen($s)>$max) throw new InvalidArgumentException('Please complete all fields within their character limits.'); return $s; }
require_once __DIR__.'/core-hr/service.php';if(!att_biometric_enabled($pdo))foreach(['devices','mapping','sync','raw_logs','device_attendance','device_monthly'] as $biometricPage)unset($nav[$biometricPage]);
$coreReady=chr_ready($pdo);if($coreReady){$companyZone=$pdo->query('SELECT timezone FROM company_settings WHERE id=1')->fetchColumn();if($companyZone)date_default_timezone_set($companyZone);}
if(!$admin&&$coreReady&&!in_array($page,['overview','profile','my_shift','documents','attendance','attendance_sheet','monthly','regularisation','leaves','balances','leave_history','my_salary','payroll','performance','reviews','tasks','helpdesk','inbox','notifications','security','announcements'],true)&&!($page==='approvals'&&can($pdo,$user,'leave.approve'))){http_response_code(403);exit('This section is not available in the employee workspace.');}
require __DIR__.'/suite.php';
require_once __DIR__.'/leave-balances.php';
$coreReady=chr_ready($pdo);
require_once __DIR__.'/ess/service.php';
if(isset($_GET['punch_status'])){
 header('Content-Type: application/json');header('Cache-Control: no-store, private');
 if($admin||$passwordSetup||!$coreReady){http_response_code(403);echo json_encode(['error'=>'Status unavailable']);exit;}
 require_once __DIR__.'/ess/punch-status.php';
 try{echo json_encode(ess_punch_status($pdo,(int)$user['id']));}
 catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Status unavailable']);}
 exit;
}

if(!$admin&&$coreReady&&$page==='approvals'&&!can($pdo,$user,'leave.approve')){http_response_code(403);exit('Approval permission required.');}
if(!$admin&&$coreReady&&$_SERVER['REQUEST_METHOD']==='POST'&&str_starts_with((string)($_POST['action']??''),'ess_')){$suiteHandledPost=true;csrf();try{ess_post($pdo,$user,$page,$_POST,$_FILES);header('Location: employee.php?page='.urlencode($page).'&saved=1');exit;}catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){$error='Unable to save. Please contact HR.';error_log('ESS: '.get_class($e));}}

$coreLeaveTypes=['CL','SL','PL','LWP'];if($coreReady){$coreLeaveTypes=[];$policies=$admin?array_values(array_filter(chr_rows($pdo,'leave_policy'),fn($policy)=>$policy['status']==='Published')):chr_eligible_leave_policies($pdo,(int)$user['id'],date('Y-m-d'));foreach($policies as $policy)$coreLeaveTypes[]=$policy['values']['type'];$tz=$pdo->query('SELECT timezone FROM company_settings WHERE id=1')->fetchColumn();if($tz)date_default_timezone_set($tz);}
if($coreReady&&!$admin&&$page==='leaves'&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='request'){
 $suiteHandledPost=true;csrf();try{chr_create_leave($pdo,$user,$_POST,$_FILES['attachment']??[],true);header('Location: employee.php?page=leaves&saved=1');exit;}catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){$error='Unable to save leave. Check the server log.';error_log('Core leave submission failed: '.get_class($e));}
}
if ($_SERVER['REQUEST_METHOD']==='POST' && empty($suiteHandledPost)) {
 csrf();
 try {
  if (!$ready) throw new InvalidArgumentException('The workspace database migration must be installed first.');
  $action=$_POST['action'] ?? '';
  $pdo->beginTransaction();
  if ($action==='deactivate' && $admin && $page==='offboarding') {
   $id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT);
   if(!$id || $id<1)throw new InvalidArgumentException('Choose an employee.');
   $q=$pdo->prepare("UPDATE users SET active=0 WHERE id=? AND role='employee' AND active=1");$q->execute([$id]);
   if($q->rowCount()!==1)throw new InvalidArgumentException('This employee is already inactive or unavailable.');
   audit_action($pdo,(int)$user['id'],'employee.deactivated',$id);
  } elseif ($action==='change_password' && in_array($page,['system','security'],true)) {
   $old=(string)($_POST['current_password'] ?? '');$new=(string)($_POST['new_password'] ?? '');
   if(strlen($new)<12 || strlen($new)>72 || $new!==($_POST['confirm_password'] ?? ''))throw new InvalidArgumentException('Use matching passwords of 12–72 characters.');
   $q=$pdo->prepare('SELECT password_hash FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);
   if(!password_verify($old,$q->fetchColumn()))throw new InvalidArgumentException('Current password is incorrect.');
   if($new===$old)throw new InvalidArgumentException('Choose a new password different from your current password.');
   $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);
   if(array_key_exists('session_version',$user)){$pdo->prepare('UPDATE users SET session_version=session_version+1 WHERE id=?')->execute([$user['id']]);$_SESSION['user']['session_version']=(int)$user['session_version']+1;}
   if(array_key_exists('must_change_password',$user)){$pdo->prepare('UPDATE users SET must_change_password=0 WHERE id=?')->execute([$user['id']]);$_SESSION['user']['must_change_password']=0;}
   audit_action($pdo,(int)$user['id'],'password.changed',(int)$user['id']);session_regenerate_id(true);
  } elseif ($action==='create_employee' && $admin && $page==='employees') {
   $name=field_text('name',150); $email=strtolower(field_text('email',190)); $password=(string)($_POST['password'] ?? '');
   if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 || strlen($password)>72) throw new InvalidArgumentException('Use a valid email and a password of 12–72 characters.');
   $pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'employee')")->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
   audit_action($pdo,(int)$user['id'],'employee.created',(int)$pdo->lastInsertId());
  } elseif ($action==='announcement' && $admin && $page==='announcements') {
   $pdo->prepare('INSERT INTO hr_announcements(title,body,author_id) VALUES(?,?,?)')->execute([field_text('title',180),field_text('details',5000),$user['id']]);
   audit_action($pdo,(int)$user['id'],'announcement.published',(int)$pdo->lastInsertId());
  } elseif ($action==='request' && !$admin && in_array($page,['leaves','regularisation','helpdesk'],true)) {
   $kind=['leaves'=>'leave','regularisation'=>'regularisation','helpdesk'=>'helpdesk'][$page];
   $category=field_text('category',40); $subject=field_text('subject',180); $details=field_text('details',5000); $start=null; $end=null;
   $allowed=$kind==='leave'?$coreLeaveTypes:($kind==='regularisation'?['Missed punch','Incorrect punch','Shift correction']:['HR','Payroll','IT','Other']);
   if (!in_array($category,$allowed,true)) throw new InvalidArgumentException('Choose a valid category.');
   if ($kind!=='helpdesk') {
    $start=field_text('start_date',10); $end=field_text('end_date',10);
    foreach([$start,$end] as $date) { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); if (!$d || $d->format('Y-m-d')!==$date) throw new InvalidArgumentException('Choose valid dates.'); }
    if ((new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days>366) throw new InvalidArgumentException('Requests may cover at most 366 days.');
    if ($end<$start) throw new InvalidArgumentException('End date cannot be before start date.');
   }
   $pdo->prepare('INSERT INTO hr_requests(user_id,kind,category,subject,details,start_date,end_date) VALUES(?,?,?,?,?,?,?)')->execute([$user['id'],$kind,$category,$subject,$details,$start,$end]);
   audit_action($pdo,(int)$user['id'],$kind.'.submitted',(int)$pdo->lastInsertId());
  } elseif (in_array($action,['review','cancel'],true) && in_array($page,['leaves','regularisation','helpdesk'],true)) {
   $kind=['leaves'=>'leave','regularisation'=>'regularisation','helpdesk'=>'helpdesk'][$page];
   $id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT); if (!$id || $id<1) throw new InvalidArgumentException('Invalid request.');
   $q=$pdo->prepare('SELECT * FROM hr_requests WHERE id=? AND kind=? FOR UPDATE');$q->execute([$id,$kind]);$r=$q->fetch(PDO::FETCH_ASSOC);
   if (!$r || $r['status']!=='Pending') throw new InvalidArgumentException('This request is no longer pending.');
   if ($action==='review' && $admin) {
    $status=$_POST['status'] ?? ''; $allowed=$kind==='helpdesk'?['Resolved','Rejected']:['Approved','Rejected'];
    if (!in_array($status,$allowed,true)) throw new InvalidArgumentException('Invalid status.');
   } elseif ($action==='cancel' && !$admin && (int)$r['user_id']===(int)$user['id']) { $status='Cancelled'; }
   else { throw new InvalidArgumentException('You cannot change this request.'); }
   if($kind==='leave'&&$status==='Approved'){if(!$suiteReady)throw new InvalidArgumentException('Install HR modules and configure leave entitlements first.');validate_leave_approval($pdo,$r);}
   $pdo->prepare('UPDATE hr_requests SET status=?,reviewer_id=? WHERE id=?')->execute([$status,$user['id'],$id]); audit_action($pdo,(int)$user['id'],$kind.'.'.strtolower($status),$id);
  } else { throw new InvalidArgumentException('This action is not available for your role.'); }
  $pdo->commit();$destination=($passwordSetup&&$action==='change_password')?'overview':$page; header('Location: '.($admin?'super-admin.php':'employee.php').'?page='.urlencode($destination).'&saved=1'); exit;
 } catch(InvalidArgumentException $e) { if($pdo->inTransaction())$pdo->rollBack(); $error=$e->getMessage(); }
 catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); $error='Unable to save. Check that the email is unique and the database is configured.'; }
}
function token(): void { echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function empty_module(string $title,string $description,array $items): void { echo '<section class="panel"><span class="eyebrow">'.h($title).'</span><h2 style="margin:12px 0">'.h($description).'</h2><p>Not configured yet. No live records are available in this module.</p><div class="module-items">';foreach($items as $item)echo '<div>'.h($item).'<span>Not connected</span></div>';echo '</div></section>'; }
if($admin&&foundation_ready($pdo)){require __DIR__.'/foundation/suite-layout.php';return;}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($nav[$page])?> · PeopleFlow</title><link rel="stylesheet" href="assets/style.css?v=<?=filemtime(__DIR__.'/../assets/style.css')?>"><link rel="stylesheet" href="assets/workspace.css?v=<?=filemtime(__DIR__.'/../assets/workspace.css')?>"><link rel="stylesheet" href="assets/ess.css?v=<?=filemtime(__DIR__.'/../assets/ess.css')?>"><link rel="stylesheet" href="assets/tables.css?v=<?=filemtime(__DIR__.'/../assets/tables.css')?>"><script src="assets/tables.js?v=<?=filemtime(__DIR__.'/../assets/tables.js')?>" defer></script><?php require __DIR__.'/theme-assets.php';?></head><body class="shrms-ui has-app-shell <?=$admin?'admin-workspace':'employee-ui ess-page-'.h($page)?>"><aside id="navigation"><a class="brand" href="index.php"><b>p.</b><span>peopleflow<small>ARKENTECH HRMS</small></span></a><p class="nav-label"><?= $admin?'COMPANY WORKSPACE':'MY WORKSPACE' ?></p><nav class="grouped-nav"><?php
$groups=['Overview'=>['overview'],'People'=>[$admin?'employees':'profile']];
foreach(suite_definitions() as $key=>$def)if(isset($nav[$key]))$groups[$def[1]][]=$key;
$groups['Attendance']=array_merge(['attendance','monthly','regularisation'],$admin?['raw_logs','sync','device_attendance','device_monthly']:[],$groups['Attendance']??[]);
$groups['Leave']=array_merge(['leaves'],$groups['Leave']??[]);
if($admin)$groups['Offboarding'][]='offboarding';
$groups['Communication']=['announcements','helpdesk'];
if($admin)$groups['Reports']=['reports','audit'];
$groups['System']=[$admin?'system':'security'];
if(!$admin&&$coreReady){$groups=['Dashboard'=>['overview'],'Inbox'=>['inbox'],'Attendance'=>['attendance'],'Leave'=>['leaves'],'Payroll'=>['my_salary','payroll'],'Performance & PMS'=>['performance','reviews'],'Tasks'=>['tasks'],'Help Desk'=>['helpdesk']];$nav=array_merge($nav,['overview'=>'Dashboard','profile'=>'My Profile','documents'=>'My Documents','attendance'=>'Attendance','regularisation'=>'Attendance Requests','leaves'=>'Leave','balances'=>'Leave Balance','payroll'=>'My Payslips','security'=>'Change Password']);if(can($pdo,$user,'leave.approve'))$groups['Team Workspace']=['approvals'];}
if($passwordSetup){$groups=['Complete account setup'=>['security']];$nav['security']='Complete account setup';}
foreach($groups as $label=>$pages): if(count($pages)===1):$key=$pages[0];?><a class="<?= $key===$page?'active':'' ?>" href="?page=<?=h($key)?>"><?=h($label)?></a><?php else:?><details <?=in_array($page,$pages,true)?'open':''?>><summary class="<?=in_array($page,$pages,true)?'active':''?>"><?=h($label)?></summary><div><?php foreach($pages as $key):?><a class="<?=$key===$page?'active':''?>" href="?page=<?=h($key)?>"><?=h($nav[$key])?></a><?php endforeach ?></div></details><?php endif;endforeach ?></nav><div class="aside-bottom" <?=(!$admin&&$coreReady&&!$passwordSetup)?'hidden':''?>><button id="theme" type="button">◐ Switch appearance</button><form action="logout.php" method="post"><?php token(); ?><button class="wide-button">Sign out ↪</button></form></div></aside><div class="main"><header><button id="menu" class="mobile-menu" aria-label="Toggle navigation" aria-expanded="false">☰</button><span><?= $admin?'Company workspace':'Employee workspace' ?> / <?=h($nav[$page])?></span><?php if(!$admin&&$coreReady&&!$passwordSetup)require __DIR__.'/ess/header.php';else echo '<span>'.h($user['name']).'</span>';?></header><main id="content"><div class="page-title"><div><span class="eyebrow"><?= $admin?'YOUR PEOPLE, IN ONE PLACE':'YOUR WORKDAY, SIMPLIFIED' ?></span><h1><?=h($nav[$page])?></h1><p><?= $admin?'Manage your team and review employee requests.':'Your personal records, updates and requests.' ?></p></div><span class="badge"><?= $admin?'Super Admin':'Employee' ?></span></div><?php if(!$ready): ?><div class="error" role="alert">Workspace setup is incomplete. Ask your administrator to import database/002-workspaces.sql.</div><?php endif ?><?php if($error): ?><div class="error" role="alert"><?=h($error)?></div><?php endif ?><?php if(isset($_GET['saved'])): ?><div class="success" role="status">Saved successfully.</div><?php endif ?>
<?php require __DIR__.'/workspace-content.php';?></main><footer>PeopleFlow by Arkentech<span><?= $admin?'Company workspace':'Employee workspace' ?></span></footer></div><script src="assets/workspace.js?v=<?=filemtime(__DIR__.'/../assets/workspace.js')?>"></script><script src="assets/ess.js?v=5" defer></script></body></html>




