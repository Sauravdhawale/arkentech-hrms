<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/auth.php';
$name=$argv[1] ?? ''; $email=strtolower($argv[2] ?? ''); $role=$argv[3] ?? 'employee';
if (!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || !in_array($role,['employee','super_admin'],true)) { exit("Usage: php bin/create-user.php 'Full Name' email@example.com employee|super_admin\nSupply a password on standard input.\n"); }
$password=rtrim(stream_get_contents(STDIN),"\r\n");
if (strlen($password)<12) { exit("Password must be at least 12 characters.\n"); }
db()->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
echo "User created.\n";
