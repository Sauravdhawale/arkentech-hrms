<?php
$orgAccess=true;try{org_authorize($pdo,$user);}catch(InvalidArgumentException $e){$orgAccess=false;}
if(!$orgAccess)return;
$orgPreview=$_SESSION['organization_preview']??null;if($orgPreview&&$orgPreview['actor']!==(int)$user['id'])$orgPreview=null;
$orgReport=null;if(isset($_GET['organization_report'])){$q=$pdo->prepare("SELECT data FROM hr_records WHERE id=? AND module='organization_update'");$q->execute([(int)$_GET['organization_report']]);$raw=$q->fetchColumn();if($raw)$orgReport=json_decode($raw,true);}
?><details class="card" <?=($orgPreview||$orgReport)?'open':''?> style="margin-bottom:24px"><summary>Arkentech departments & designations</summary>
<p>Add the requested organization catalog, reuse matching records and normalize known spellings. Employee assignments, existing IDs and permission roles are preserved. Conflicts and inactive records are left for review.</p>
<form method="post"><?php ftoken();?><input type="hidden" name="action" value="organization_preview"><button class="button">Preview organization update</button></form>
<?php $orgData=$orgReport?:($orgPreview['plan']??null);if($orgData):?>
<h3><?=$orgReport?'Applied update report':'Proposed update'?></h3>
<?php if($orgReport):?><p><?=h($orgData['applied_at'])?> · <?=(int)$orgData['changed']?> records changed. Employee department/designation IDs preserved.</p><?php endif;?>
<?php if($orgData['unassigned_employees']):?><p class="hint"><?=(int)$orgData['unassigned_employees']?> existing employees have a designation but no department. Their assignments will remain unchanged; review them separately in People.</p><?php endif;?>
<?php if($orgData['conflicts']):?><div class="alert error"><strong>Records requiring review</strong><ul><?php foreach($orgData['conflicts'] as $conflict):?><li><?=h($conflict)?></li><?php endforeach;?></ul><p>These records are skipped. No employee is moved and no existing record is merged or deleted.</p></div><?php endif;?>
<?php foreach(['departments'=>'Departments','designations'=>'Designations'] as $kind=>$label):?>
<h3><?=h($label)?> (<?=count($orgData[$kind])?> requested)</h3><div class="table-scroll" style="max-height:380px"><table class="directory"><thead><tr><th>Name</th><th>Department</th><th>ID</th><th>Existing name</th><th>Action</th></tr></thead><tbody><?php foreach($orgData[$kind] as $item):?><tr><td><?=h($item['name'])?></td><td><?=h($item['department']??$item['name'])?></td><td><?=h($item['id']??'New')?></td><td><?=h($item['before']??'—')?></td><td><?=h($item['action'])?></td></tr><?php endforeach;?></tbody></table></div><?php endforeach;?>
<?php if(!$orgReport&&$orgPreview):?><form method="post" class="form-actions"><?php ftoken();?><input type="hidden" name="action" value="organization_apply"><input type="hidden" name="fingerprint" value="<?=h($orgPreview['plan']['fingerprint'])?>"><button class="button primary">Apply safe changes</button></form><?php endif;endif;?></details>
