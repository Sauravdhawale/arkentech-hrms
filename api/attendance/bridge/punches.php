<?php
require __DIR__.'/common.php';
bridge_run('POST',function(PDO $db,array $device):array{$body=bridge_body();if(($body['device_serial']??'')!==$device['values']['device_code'])throw new InvalidArgumentException('Token belongs to a different device.');if(!is_array($body['events']??null))throw new InvalidArgumentException('Events array required');return att_bridge_batch($db,$device,$body['events']);});
