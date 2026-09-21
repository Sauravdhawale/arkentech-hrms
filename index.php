<?php
require __DIR__.'/includes/access.php';
$user=require_user();
require_once __DIR__.'/includes/foundation/core.php';
header('Location: '.(can(db(),$user,'dashboard.view')?'super-admin.php':'employee.php')); exit;
