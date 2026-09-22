<?php
require __DIR__.'/common.php';
bridge_run('GET',function(PDO $db,array $device):array{$db->prepare('INSERT INTO hr_bridge_health(device_id,last_seen) VALUES(?,NOW()) ON DUPLICATE KEY UPDATE last_seen=NOW()')->execute([$device['id']]);$q=$db->prepare('SELECT sync_requested_at,test_requested_at FROM hr_bridge_health WHERE device_id=?');$q->execute([$device['id']]);return ['ok'=>true,'device_serial'=>$device['values']['device_code'],'server_time'=>date(DATE_ATOM),'commands'=>$q->fetch(PDO::FETCH_ASSOC)];});
