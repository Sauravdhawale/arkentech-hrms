<?php
require __DIR__.'/auth.php';
if (isset($_SESSION['user'])) { header('Location: index.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 csrf();
 try {
  $pdo=db(); $email=strtolower(trim($_POST['email'] ?? ''));
  $loginCols=$pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
  $hasUsername=in_array('username',$loginCols,true);
  $key=hash('sha256',$email.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
  $stmt=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempt_key=? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'); $stmt->execute([$key]);
  if ((int)$stmt->fetchColumn()>=5) { $error='Too many attempts. Please try again in 15 minutes.'; }
  else {
   $pdo->prepare('INSERT INTO login_attempts(attempt_key) VALUES (?)')->execute([$key]);
   $stmt=$pdo->prepare('SELECT * FROM users WHERE '.($hasUsername?'(email=? OR username=?)':'email=?').' AND active=1'); $stmt->execute($hasUsername?[$email,$email]:[$email]); $u=$stmt->fetch(PDO::FETCH_ASSOC);
   if ($u && password_verify($_POST['password'] ?? '',$u['password_hash']) && in_array($u['role'],['super_admin','employee'],true)) {
    session_regenerate_id(true); unset($u['password_hash']); $_SESSION['user']=$u; $_SESSION['csrf']=bin2hex(random_bytes(32));
    $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key=?')->execute([$key]); header('Location: index.php'); exit;
   } else { $error='Email or password is incorrect.'; }
  }
 } catch (Throwable $e) { $error='Sign-in is unavailable. Please contact your administrator.'; }
}

?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · Arkentech sHRMS</title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/login.css?v=<?=filemtime(__DIR__.'/assets/login.css')?>"><script src="assets/login.js?v=<?=filemtime(__DIR__.'/assets/login.js')?>" defer></script></head>
<body class="ark-login">
<main class="login-shell">
 <section class="login-panel" aria-labelledby="login-title">
  <div class="login-content">
  <a href="login.php" class="login-logo" aria-label="Arkentech Solutions"><img src="assets/arkentech-logo.webp" alt="Arkentech Solutions" width="1600" height="619"></a>
   <p class="login-eyebrow">EMPLOYEE PORTAL</p><h1 id="login-title">Welcome back.</h1><p class="login-intro">Sign in to your sHRMS workspace.</p>
   <?php if($error):?><div class="login-error" role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
   <form method="post">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8')?>">
    <label for="login-email">Email or username</label><div class="login-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg><input id="login-email" type="text" name="email" placeholder="Enter your email or username" autocomplete="username" required value="<?=htmlspecialchars((string)($_POST['email']??''),ENT_QUOTES,'UTF-8')?>"></div>
    <label for="login-password">Password</label><div class="login-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg><input id="login-password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required minlength="1"><button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" hidden><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
    <div class="login-help"><a href="forgot-password.php">Forgot password?</a></div>
    <button class="login-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
   </form>
   <div class="login-divider"><span>or</span></div>
   <button class="google-signin" type="button" disabled aria-describedby="google-status"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.39-.18-2.05H12v3.88h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.89-1.74 2.98-4.3 2.98-7.36Z"/><path fill="#34A853" d="M12 22c2.7 0 4.96-.9 6.62-2.41l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.6 0-4.8-1.76-5.59-4.12H3.07v2.59A10 10 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.41 13.92a6 6 0 0 1 0-3.84V7.49H3.07a10 10 0 0 0 0 9.02l3.34-2.59Z"/><path fill="#EA4335" d="M12 5.96c1.47 0 2.79.51 3.82 1.51l2.86-2.87A9.6 9.6 0 0 0 12 2a10 10 0 0 0-8.93 5.49l3.34 2.59C7.2 7.72 9.4 5.96 12 5.96Z"/></svg>Continue with Google</button>
   <p id="google-status" class="google-status">Google sign-in is not enabled yet.</p>
   <p class="login-access">Need access? Contact your administrator.</p>
  </div>
  <p class="login-footer"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg>Secure access for your workday</p>
 </section>
 <section class="login-visual" aria-labelledby="story-title">
  <div class="wave-scene" aria-hidden="true"><div class="wave-glow"></div><svg class="red-waves" viewBox="0 0 800 1000" preserveAspectRatio="xMidYMid slice"><defs><linearGradient id="ribbon" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#ff8070" stop-opacity=".08"/><stop offset=".45" stop-color="#e4443f" stop-opacity=".8"/><stop offset=".7" stop-color="#B12625"/><stop offset="1" stop-color="#580e16" stop-opacity=".12"/></linearGradient><linearGradient id="edge"><stop stop-color="#ffb7a2" stop-opacity="0"/><stop offset=".55" stop-color="#ffa88e" stop-opacity=".7"/><stop offset="1" stop-color="#ff9c8a" stop-opacity="0"/></linearGradient></defs><g class="wave-one"><path fill="url(#ribbon)" d="M-180 860C40 200 270 1140 450 610S710 250 990 410L990 800C720 630 650 260 470 780S120 410-180 1050Z"/><path fill="none" stroke="url(#edge)" stroke-width="1.5" d="M-180 860C40 200 270 1140 450 610S710 250 990 410"/></g><g class="wave-two"><path fill="url(#ribbon)" d="M-160 660C180 1060 310 470 570 780S940 440 1060 600L1000 1130H-160Z"/><path fill="none" stroke="url(#edge)" stroke-width="2" d="M-160 660C180 1060 310 470 570 780S940 440 1060 600"/></g><g class="wave-three"><path fill="none" stroke="url(#edge)" stroke-width="1" d="M-120 920C80 340 270 1090 460 600S750 290 1000 420M-120 938C80 358 270 1108 460 618S750 308 1000 438M-120 956C80 376 270 1126 460 636S750 326 1000 456"/></g></svg></div>
  <div class="story-copy"><p class="story-eyebrow">PEOPLE. PURPOSE. PROGRESS.</p><h2 id="story-title">Your people.<br> Your time.<br> <span>One workspace.</span></h2><p>Everything you need for a better workday.<br> Connected, simple and always within reach.</p><div class="story-tags"><span>Attendance</span><span>Leave</span><span>Payslips</span></div></div>
  <div class="story-footer"><div><strong>ARKENTECH SOLUTIONS</strong><small>Connected teams. Better workdays.</small></div><button class="motion-toggle" type="button" aria-pressed="false" hidden>Pause motion</button></div>
 </section>
</main>
</body></html>
