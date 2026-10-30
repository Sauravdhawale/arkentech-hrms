<?php
if(!att_ready($pdo)){echo '<p>Attendance setup is incomplete.</p>';return;}
$live=att_live_people($pdo,$_GET);$live['as_of']=date(DATE_ATOM);$live['timezone']=date_default_timezone_get();$live['can_manage']=can($pdo,$user,'attendance.manage');
?>
<link rel="stylesheet" href="assets/attendance-live.css?v=<?=filemtime(dirname(__DIR__,2).'/assets/attendance-live.css')?>">
<script src="assets/attendance-live.js?v=<?=filemtime(dirname(__DIR__,2).'/assets/attendance-live.js')?>" defer></script>
<section class="att-live" data-attendance-live>
 <div class="att-live-heading"><div><h2>Live now</h2><p>See who is checked in across current shifts, including night shifts that started yesterday.</p></div><div class="att-live-actions"><a class="button" href="?page=attendance&amp;date=<?=date('Y-m-d')?>">Attendance by date</a><?php if($live['can_manage']):?><a class="button" href="?page=manual_attendance">Manual / historical entry</a><?php endif;?></div></div>
 <form method="get" class="card att-live-filters" data-live-filters><?php chr_hidden('page','attendance');chr_select('employee_id','Employee',$_GET['employee_id']??'',[''=>'All employees']+$employeeOptions);chr_select('department_id','Department',$_GET['department_id']??'',[''=>'All departments']+$departmentOptions);field('search','Search name or employee ID',$_GET);?><button class="button primary">Apply filters</button><a class="button" href="?page=attendance">Reset</a></form>
 <div class="att-live-counts" data-live-counts></div>
 <section class="card att-live-card"><div class="att-live-toolbar"><div><strong>Employee status</strong><p data-live-updated role="status">Loading current status…</p></div><button class="button" type="button" data-live-refresh>Refresh now</button></div>
 <p class="att-live-note">Unknown device directions are labelled <strong>Inferred</strong>. Each distinct unknown-direction punch alternates IN / OUT, even seconds apart. Retransmitted copies of the same punch count once. Shift worked counts only time inside the scheduled shift. Overtime includes before-shift work and after-shift work minus late arrival. All counters exclude checked-out time and are live estimates, not final payroll totals.</p>
 <div class="table-scroll att-live-scroll" tabindex="0" role="region" aria-label="Live employee attendance"><table><thead><tr><th>Employee</th><th>Current status</th><th>Current shift</th><th>Latest punch</th><th>Shift worked</th><th>Overtime</th><th>Break</th><?php if($live['can_manage']):?><th>Admin action</th><?php endif;?></tr></thead><tbody data-live-rows></tbody></table></div>
 <p data-live-limit hidden>Showing the first 200 employees. Narrow the employee, department or name filter to see others.</p>
 </section>
 <noscript><p>Enable JavaScript for live status, or use <a href="?page=attendance&amp;date=<?=date('Y-m-d')?>">Attendance by date</a>.</p></noscript>
</section>
<?php if($live['can_manage']):?>
<dialog id="admin-live-punch" class="attendance-dialog" aria-labelledby="admin-live-title"><form method="post" class="form-grid" data-live-punch-form>
 <h2 id="admin-live-title" class="full">Record employee punch</h2><p class="full" data-live-person></p>
 <?php ftoken();chr_hidden('action','att_live_punch');chr_hidden('request_key',bin2hex(random_bytes(32)));chr_hidden('employee_id','');chr_hidden('punch_action','');chr_hidden('presence_revision','');?>
 <p class="full">This records the selected action at the <strong>current company time</strong>. The effective shift date is selected automatically, including after midnight.</p>
 <?php field('notes','Reason',[],'text',true,3000);?>
 <p class="full">Saved as Admin Manual with your audit record. Existing raw device punches are retained. Manual attendance totals are protected from automatic biometric recalculation; later device punches still update live presence.</p>
 <div class="full form-actions"><button class="button" type="button" data-close-dialog>Cancel</button><button class="button primary" data-live-save>Save punch</button></div>
</form></dialog>
<?php endif;?>
<script type="application/json" id="attendance-live-data"><?=json_encode($live,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>

