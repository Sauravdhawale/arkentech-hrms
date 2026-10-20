<?php
/** Recoverable authentication/database outage; never expose SQL or replay a submitted form. */
function workspace_unavailable(Throwable $error):never {
 $reference=bin2hex(random_bytes(4));
 $state=preg_replace('/[^A-Za-z0-9]/','',(string)$error->getCode());
 $driver=$error instanceof PDOException?(int)($error->errorInfo[1]??0):0;
 error_log('HRMS workspace unavailable reference='.$reference.' type='.get_class($error).' state='.$state.' driver='.$driver);
 http_response_code(503);header('Retry-After: 30');header('Content-Type: text/html; charset=UTF-8');
 $script=basename((string)($_SERVER['SCRIPT_NAME']??'index.php'));
 if(!preg_match('/^[A-Za-z0-9_-]+\.php$/D',$script))$script='index.php';
 $retry=htmlspecialchars($script.($_GET?'?'.http_build_query($_GET):''),ENT_QUOTES,'UTF-8');
 ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Temporarily unavailable · Employee Portal</title><link rel="stylesheet" href="assets/workspace-unavailable.css"></head><body><main><span class="label">EMPLOYEE PORTAL</span><h1>Workspace temporarily unavailable</h1><p>We could not connect to the database. Wait a moment, then try opening this page again.</p><p>Your request was not processed. If you were filling a form, return to it and check your entries before saving again.</p><div class="actions"><a href="<?=$retry?>">Try opening this page again</a><a class="secondary" href="index.php">Back to dashboard</a></div><p class="note">If this continues, share reference <strong><?=htmlspecialchars($reference,ENT_QUOTES,'UTF-8')?></strong> with your administrator.</p></main></body></html><?php exit;
}
