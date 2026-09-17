<?php
require __DIR__.'/auth.php';
if (isset($_SESSION['user'])) { header('Location: index.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 csrf();
 try {
  $pdo=db(); $email=strtolower(trim($_POST['email'] ?? ''));
  $key=hash('sha256',$email.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
  $stmt=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempt_key=? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'); $stmt->execute([$key]);
  if ((int)$stmt->fetchColumn()>=5) { $error='Too many attempts. Please try again in 15 minutes.'; }
  else {
   $pdo->prepare('INSERT INTO login_attempts(attempt_key) VALUES (?)')->execute([$key]);
   $stmt=$pdo->prepare('SELECT id,name,email,password_hash,role FROM users WHERE email=? AND active=1'); $stmt->execute([$email]); $u=$stmt->fetch(PDO::FETCH_ASSOC);
   if ($u && password_verify($_POST['password'] ?? '',$u['password_hash']) && in_array($u['role'],['super_admin','employee'],true)) {
    session_regenerate_id(true); unset($u['password_hash']); $_SESSION['user']=$u; $_SESSION['csrf']=bin2hex(random_bytes(32));
    $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key=?')->execute([$key]); header('Location: index.php'); exit;
   } else { $error='Email or password is incorrect.'; }
  }
 } catch (Throwable $e) { $error='Sign-in is unavailable. Please contact your administrator.'; }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · PeopleFlow</title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/style.css"></head><body class="login"><section class="login-story"><a class="brand" href="login.php"><i>p.</i> peopleflow<span>ARKENTECH</span></a><div><span class="eyebrow">GOOD WORK STARTS WITH PEOPLE</span><h1>A little more flow.<br>A lot more possibility.</h1><p>One place for your people, their time,<br>and everything that helps them thrive.</p><div class="login-art"><div class="art-card">Your people. Connected.<strong>Let's grow together ↗</strong><div class="faces">◉ ◉ ◉ ◉</div></div><div class="art-stamp">Made for<br><b>better days ✳</b></div></div></div><small>ARKENTECH SOLUTIONS · PEOPLE & CULTURE</small></section><main class="login-form"><div><span class="eyebrow">YOUR WORKDAY, SIMPLIFIED</span><h1>Welcome back.</h1><p>Sign in to your PeopleFlow workspace.</p><?php if($error): ?><div class="error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif ?><form method="post"><input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>"><label>Work email<input type="email" name="email" placeholder="you@arkentechsolutions.com" autocomplete="username" required></label><label>Password<input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required minlength="1"></label><button class="primary" type="submit">Sign in to workspace ↗</button></form><p class="help">Need access or a password reset? Contact your HR administrator.</p><small>Secure access · Super Admin & Employee</small></div></main></body></html>
