<?php
require __DIR__.'/includes/access.php';
$user=require_user();
header('Location: '.($user['role']==='super_admin'?'super-admin.php':'employee.php')); exit;
