<?php
// Isolated presentation fixture: never serve sample employee data from the application.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function att_ready($pdo): bool { return false; }
function ess_balances($pdo,$owner,$year): array {
 return array_map(fn($name,$days)=>['type'=>$name,'available'=>$days],
 ['Privilege leave','Casual leave','Sick leave','Compensatory off','Bereavement leave','Leave without pay'],[11,6,4,0,0,0]);
}
function ess_events($pdo,$employee): array { return []; }
function chr_rows($pdo,$key): array { return [['values'=>['birthdays_enabled'=>true]]]; }
class DashboardFixtureStatement {
 function execute($params): void {}
 function fetchAll($mode): array { return []; }
}
class DashboardFixtureConnection {
 function prepare($sql): DashboardFixtureStatement { return new DashboardFixtureStatement; }
}
$pdo=new DashboardFixtureConnection;
$user=['name'=>'Sample Employee']; $employee=[]; $owner=1;
$month='2026-11'; $from='2026-11-01'; $to='2026-11-30';
$rows=[['status'=>'P','late_minutes'=>5,'early_minutes'=>0],['status'=>'P','late_minutes'=>0,'early_minutes'=>0]];
$tasks=[['status'=>'Pending'],['status'=>'Completed']];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Employee dashboard fixture</title></head>
<body class="shrms-ui has-app-shell employee-ui ess-page-overview">
<aside><a class="brand" href="#">PeopleFlow</a><nav><a href="#">Dashboard</a><a href="#">Attendance</a><a href="#">Leave</a></nav></aside>
<div class="main"><header>Employee workspace / Dashboard</header><main id="content">
<div class="page-title"><div><span class="eyebrow">YOUR WORKDAY, SIMPLIFIED</span><h1>Dashboard</h1><p>Your personal records, updates and requests.</p></div></div>
<?php
require __DIR__.'/../includes/ess/workday-card.php';
require __DIR__.'/../includes/ess/dashboard-panels.php';
require __DIR__.'/../includes/ess/personal-cards.php';
?>
<dialog id="correction-dialog"><h2>Request a correction</h2><button type="button" data-dialog-close>Close</button></dialog>
</main></div></body></html>
