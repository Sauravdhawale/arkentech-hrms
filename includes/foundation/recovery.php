<?php
function recovery_config():array {
 $file=dirname(__DIR__,2).'/config/mail.local.php';$c=is_file($file)?require $file:[];
 return is_array($c)?$c:[];
}
function recovery_send(string $email,string $token):bool {
 $c=recovery_config();$base=rtrim((string)($c['base_url']??'https://employeeportal.arkentechsolutions.com'),'/');
 if(!filter_var($base,FILTER_VALIDATE_URL)||parse_url($base,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Configure an HTTPS application URL.');
 $link=$base.'/reset-password.php?token='.rawurlencode($token);$body="A password reset was requested for your sHRMS account.\n\nOpen this link within 60 minutes:\n$link\n\nIf you did not request this, ignore this email.";
 if(getenv('APP_ENV')==='testing'&&getenv('DB_NAME')==='peopleflow_ci')return file_put_contents('/tmp/peopleflow-reset-test.json',json_encode(['email'=>$email,'token'=>$token]))!==false;
 $sender=(string)($c['from']??'');if(!filter_var($sender,FILTER_VALIDATE_EMAIL))return false;
 return mail($email,'Reset your sHRMS password',$body,['From'=>$sender,'Content-Type'=>'text/plain; charset=UTF-8']);
}
function request_recovery(PDO $pdo,string $identity,string $ip):void {
 $identity=strtolower(trim($identity));$key=hash('sha256','reset|'.$ip);
 $q=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempt_key=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)');$q->execute([$key]);if((int)$q->fetchColumn()>=5)return;
 $pdo->prepare('INSERT INTO login_attempts(attempt_key) VALUES(?)')->execute([$key]);
 $q=$pdo->prepare('SELECT id,email FROM users WHERE (email=? OR username=?) AND active=1 LIMIT 1');$q->execute([$identity,$identity]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u||!filter_var($u['email']??'',FILTER_VALIDATE_EMAIL))return;
 $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
 $pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))')->execute([$u['id'],$hash]);
 if(!recovery_send($u['email'],$token)){$pdo->prepare('DELETE FROM password_resets WHERE token_hash=?')->execute([$hash]);error_log('sHRMS password reset delivery failed; check private mail configuration.');}
}
function apply_recovery(PDO $pdo,string $token,string $password):void {
 if(!preg_match('/^[a-f0-9]{64}$/',$token)||strlen($password)<12||strlen($password)>72)throw new InvalidArgumentException('Use a valid reset link and a password of 12–72 characters.');
 $pdo->beginTransaction();try{
  $q=$pdo->prepare('SELECT pr.* FROM password_resets pr JOIN users u ON u.id=pr.user_id AND u.active=1 WHERE pr.token_hash=? AND pr.used_at IS NULL AND pr.expires_at>NOW() FOR UPDATE');$q->execute([hash('sha256',$token)]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('This reset link is expired or already used. Request a new one.');
  $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=0,session_version=session_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$r['user_id']]);
  $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$r['user_id']]);
  $pdo->prepare("INSERT INTO hr_audit(actor_id,action,record_id) VALUES(?,'password.reset',?)")->execute([$r['user_id'],$r['user_id']]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
