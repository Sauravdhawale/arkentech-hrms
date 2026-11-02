<?php
/** Read-only evidence from cloud-tagged punches already saved in HRMS. */
function att_cloud_report(PDO $db,string $serial,string $day):array {
 $day=chr_date($day);$until=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
 $q=$db->prepare("SELECT COUNT(*) total,MAX(UNIX_TIMESTAMP(p.received_at)) last_received_epoch,MAX(p.punched_at) last_punch,
  COALESCE(SUM(p.punched_at>=? AND p.punched_at<?),0) day_punches
  FROM hr_punches p WHERE p.device_code=? AND p.event_key LIKE 'adms:%'");
 $q->execute([$day,$until,$serial]);$summary=$q->fetch(PDO::FETCH_ASSOC);
 $q=$db->prepare("SELECT p.biometric_id,p.punched_at,UNIX_TIMESTAMP(p.received_at) received_epoch,
  COALESCE(x.status,'Legacy raw') processing_status,x.error_message,u.name
  FROM hr_punches p LEFT JOIN hr_punch_processing x ON x.punch_id=p.id LEFT JOIN users u ON u.id=x.employee_id
  WHERE p.device_code=? AND p.event_key LIKE 'adms:%' ORDER BY p.received_at DESC,p.id DESC LIMIT 50");
 $q->execute([$serial]);return ['summary'=>$summary,'rows'=>$q->fetchAll(PDO::FETCH_ASSOC)];
}
