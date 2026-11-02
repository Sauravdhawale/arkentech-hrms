<?php
require_once __DIR__.'/cloud.php';
$cloudDay=date('Y-m-d');
?>
<link rel="stylesheet" href="assets/biometric-cloud.css?v=<?=filemtime(dirname(__DIR__,2).'/assets/biometric-cloud.css')?>">
<section class="card cloud-intro">
 <div><h2>Cloud delivery · ADMS</h2><p>See punches delivered from the cloud receiver to HRMS, separately from the office laptop.</p><p class="hint">Checked <?=h(date('d M Y · h:i:s A T'))?> · Refresh to check for new deliveries.</p></div>
 <a class="button primary" href="?page=cloud_biometric">Refresh cloud status</a>
</section>
<?php foreach($devices as $device):
$report=att_cloud_report($pdo,$device['values']['device_code'],$cloudDay);$summary=$report['summary'];
$last=$summary['last_received_epoch'];$age=$last===null?null:time()-(int)$last;$recent=$age!==null&&$age>=0&&$age<300;
?>
<section class="card biometric-card cloud-device">
 <header class="biometric-card-heading"><div><p class="biometric-kicker">CLOUD BIOMETRIC</p><h2><?=h($device['title'])?></h2><p class="biometric-meta"><?=h($device['values']['device_code'])?> · <?=h($device['values']['location']??'')?></p></div><?php fbadge($device['status'],$device['status']==='Enabled');?></header>
 <div class="biometric-health">
  <div><span class="biometric-label">Cloud → HRMS delivery</span><strong class="biometric-state <?=$recent?'is-connected':'is-pending'?>"><?=$recent?'Recent cloud delivery':($last?'No recent cloud delivery':'No cloud delivery recorded')?></strong><small><?=h($last?'Last cloud-tagged punch saved: '.att_server_time_label($last):'Waiting for the first cloud-tagged punch')?></small></div>
  <div><span class="biometric-label">Machine → Cloud receiver</span><strong class="biometric-state is-pending">Not reported to HRMS</strong><small>Receiver heartbeat, queue and forwarding ON/OFF must be checked on the VPS. No recent punches alone does not prove the receiver is offline.</small></div>
 </div>
 <div class="cloud-metrics">
  <div><span>Cloud punches dated today</span><strong><?=h($summary['day_punches'])?></strong><small><?=h($cloudDay)?> · company time</small></div>
  <div><span>Latest cloud punch time</span><strong><?=h($summary['last_punch']?att_time_label($summary['last_punch'],true,true):'No punches yet')?></strong><small>Time recorded by the machine</small></div>
  <div><span>Cloud-tagged punches saved</span><strong><?=h($summary['total'])?></strong><small>All dates · unique stored records</small></div>
 </div>
 <?php if(!(int)$summary['day_punches']):?><p class="alert">No cloud-tagged punches dated today have reached this view. Check the machine’s ADMS connection, the cloud capture service and the forwarding queue.</p><?php endif;?>
 <h3>Latest cloud deliveries</h3><p class="hint">Latest 50 saved records across all dates. “Received by HRMS” confirms delivery; processing status explains whether attendance was updated.</p>
 <div class="table-scroll cloud-records" tabindex="0" role="region" aria-label="Cloud punch deliveries"><table class="directory"><thead><tr><th>Employee</th><th>Biometric ID</th><th>Punch time</th><th>Received by HRMS</th><th>Processing status</th><th>Details</th></tr></thead><tbody>
 <?php foreach($report['rows'] as $row):?><tr><td><?=h($row['name']??'Not mapped')?></td><td><?=h($row['biometric_id'])?></td><td><?=h(att_time_label($row['punched_at'],true,true))?></td><td><?=h(att_server_time_label($row['received_epoch']))?></td><td><?=h($row['processing_status'])?></td><td><?=h($row['error_message']??'')?></td></tr><?php endforeach;?>
 <?php if(!$report['rows']):?><tr><td colspan="6">No cloud-tagged punches saved for this device yet.</td></tr><?php endif;?></tbody></table></div>
 <p class="hint">This view identifies the existing ADMS forwarder’s <code>adms:</code> event keys. Duplicates first saved by the laptop may not appear here. For all device punches, use <a href="?page=punch_log&amp;device_code=<?=h(rawurlencode($device['values']['device_code']))?>">Check-In / Check-Out Log</a>.</p>
</section>
<?php endforeach;if(!$devices):?><section class="card"><p>No biometric devices registered.</p><a class="button" href="?page=devices">Manage devices</a></section><?php endif;?>
<details class="card cloud-help"><summary>Cloud connection troubleshooting</summary><p>The laptop bridge and ADMS use different connections. A green API heartbeat only shows that HRMS received a request; it does not prove the machine or cloud capture is reachable.</p><ol><li>Confirm the machine is connected to the office network and its ADMS address/port still match the VPS receiver.</li><li>On the VPS, check both services and the forwarding queue using the read-only commands below.</li><li>Compare the pending queue and last acknowledgement with this page. Check Sync Logs and punch processing for any errors.</li></ol><pre>systemctl is-active shrms-adms-capture shrms-adms-forwarder
shrms-adms-forwarder status
journalctl -u shrms-adms-forwarder -n 20 --no-pager</pre><p class="hint">Keep existing punch data and queues. Do not reset the device or rotate its token to troubleshoot this screen.</p></details>
