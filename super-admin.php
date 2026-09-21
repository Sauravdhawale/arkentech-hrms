<?php
require __DIR__.'/includes/access.php';
$user=require_user();
require_once __DIR__.'/includes/foundation/core.php';
$foundationPages=['overview','employees','employee-add','employee-edit','employee-view','settings','departments','designations','roles','account','system'];
if(in_array($_GET['page']??'overview',$foundationPages,true))require __DIR__.'/includes/foundation/controller.php';
else{if($user['role']!=='super_admin'){http_response_code(403);exit('Super Admin access required.');}require __DIR__.'/includes/workspace.php';}
