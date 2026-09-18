<?php
require __DIR__.'/auth.php';
require_once __DIR__.'/includes/setup-key.php';
$error='';$success=false;
$lockPath=__DIR__.'/config/peopleflow.setup.lock';
$disabled=is_file($lockPath);
try { if((int)db()->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn()>0)$disabled=true; } catch(Throwable $e) {}
if($_SERVER['REQUEST_METHOD']==='POST' && !$disabled) {
 csrf();
 if(!hash_equals(PEOPLEFLOW_SETUP_HASH,hash('sha256',(string)($_POST['setup_key'] ?? '')))) { http_response_code(403);$error='The setup key is incorrect.'; }
 elseif(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { $error='Open this page over HTTPS to continue.'; }
 else {
  $mutex=fopen(__DIR__.'/config/peopleflow.setup.mutex','c');
  if(!$mutex || !flock($mutex,LOCK_EX)) { $error='Unable to lock setup. Check config directory permissions.'; }
  else {
   try {
    if(is_file($lockPath))throw new RuntimeException('Setup is already complete.');
    $email=strtolower(trim((string)($_POST['email'] ?? '')));$name=trim((string)($_POST['name'] ?? ''));$password=(string)($_POST['password'] ?? '');
    if(!$name || strlen($name)>150 || strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 || strlen($password)>72 || $password!==($_POST['confirm_password'] ?? ''))throw new RuntimeException('Enter a valid name, email and matching passwords of 12–72 characters.');
    $configPath=__DIR__.'/config/peopleflow.local.php';
    $configured=is_file($configPath) || (getenv('DB_NAME') && getenv('DB_USER'));
    if($configured) { $pdo=db(); }
    else {
     $c=['host'=>trim((string)($_POST['db_host'] ?? 'localhost')),'database'=>trim((string)($_POST['db_name'] ?? '')),'username'=>trim((string)($_POST['db_user'] ?? '')),'password'=>(string)($_POST['db_password'] ?? '')];
     if(!preg_match('/^[a-zA-Z0-9._-]+$/',$c['host']) || !preg_match('/^[a-zA-Z0-9_]+$/',$c['database']) || !$c['username'])throw new RuntimeException('Enter the database details from Hostinger.');
     $pdo=new PDO('mysql:host='.$c['host'].';dbname='.$c['database'].';charset=utf8mb4',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    // Existing users and local configuration are never overwritten.
    $schema=str_replace('CREATE TABLE users','CREATE TABLE IF NOT EXISTS users',file_get_contents(__DIR__.'/database/schema.sql'));
    $schema=str_replace('CREATE TABLE login_attempts','CREATE TABLE IF NOT EXISTS login_attempts',$schema);
    foreach(explode(';',$schema.';'.file_get_contents(__DIR__.'/database/002-workspaces.sql')) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
    if((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn()>0)throw new RuntimeException('A Super Admin already exists. Sign in with that account.');
    if(!$configured) {
     $body="<?php\nif (!defined('PEOPLEFLOW_INTERNAL')) { http_response_code(404); exit; }\nreturn ".var_export($c,true).";\n";
     $f=fopen($configPath,'x');if(!$f)throw new RuntimeException('Unable to save configuration.');
     $ok=fwrite($f,$body);fclose($f);chmod($configPath,0600);if($ok!==strlen($body)){unlink($configPath);throw new RuntimeException('Unable to save configuration.');}
    }
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'super_admin')")->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    $id=$pdo->lastInsertId();$pdo->prepare("INSERT INTO hr_audit(actor_id,action,record_id) VALUES(?,'super_admin.created',?)")->execute([$id,$id]);$pdo->commit();
    file_put_contents($lockPath,'Setup completed '.gmdate('c'),LOCK_EX);chmod($lockPath,0600);
    $success=true;$disabled=true;session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));
   } catch(RuntimeException $e) { if(isset($pdo) && $pdo->inTransaction())$pdo->rollBack();$error=$e instanceof PDOException?'Database setup failed. Check the database details, permissions and existing table structure.':$e->getMessage(); }
   finally {flock($mutex,LOCK_UN);fclose($mutex);}
  }
 }
}
function sh($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set up PeopleFlow</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/workspace.css"></head><body><main class="panel" style="max-width:720px;margin:40px auto;padding:32px"><a class="brand" href="login.php"><i>p.</i> peopleflow<span>ARKENTECH</span></a><h1><?= $disabled?'Workspace setup complete':'Create your workspace' ?></h1><?php if($error):?><div class="error" role="alert"><?=sh($error)?></div><?php endif ?><?php if($disabled):?><p style="margin:24px 0"><?= $success?'Your Super Admin account is created. Sign in with the email and password you just entered.':'Setup is locked because an administrator account or setup lock already exists.' ?></p><a class="primary" href="login.php">Continue to sign in →</a><?php else:?><p>Connect your Hostinger database and create the first Super Admin. Existing API and configuration files are preserved.</p><form method="post" class="work-form"><input type="hidden" name="csrf" value="<?=sh($_SESSION['csrf'])?>"><label>Private setup key<input type="password" name="setup_key" required autocomplete="off"></label><h2>Database connection</h2><p>If PeopleFlow is already configured, its current database connection will be used.</p><label>Database host<input name="db_host" value="localhost"></label><label>Database name<input name="db_name" placeholder="Your full Hostinger database name" autocomplete="off"></label><label>Database username<input name="db_user" autocomplete="off"></label><label>Database password<input name="db_password" type="password" autocomplete="off"></label><h2>Your Super Admin account</h2><label>Full name<input name="name" required maxlength="150" autocomplete="name"></label><label>Work email<input name="email" type="email" required maxlength="190" autocomplete="username"></label><label>Choose password<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label>Confirm password<input name="confirm_password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><button class="primary">Create Super Admin & set up database →</button></form><?php endif ?></main></body></html>
