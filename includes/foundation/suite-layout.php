<?php
// Workspace has already authenticated, processed actions and checked Super Admin access.
if(!$admin||($user['role']??'')!=='super_admin'){http_response_code(403);exit('Super Admin access required.');}
require_once __DIR__.'/../core-hr/controller.php';
require_once __DIR__.'/../payroll/controller.php';
$installed=foundation_ready($pdo);$company=$pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC)?:['name'=>'Arkentech Solutions'];
$pages=['overview'=>['Dashboard','dashboard.view'],'employees'=>['People','employees.view'],'settings'=>['Company settings','settings.view'],'departments'=>['Departments','departments.view'],'designations'=>['Designations','designations.view'],'roles'=>['Roles','roles.view'],'account'=>['My account',null],'leave_routes'=>['Leave approval routes','settings.edit']]+$corePages+$payPages;
$pages[$page]=[$nav[$page],null];$recruitmentPages=[];
if(isset($_GET['saved'])&&!$notice)$notice='Saved successfully.';
$suiteLayout=true;
require __DIR__.'/layout.php';
