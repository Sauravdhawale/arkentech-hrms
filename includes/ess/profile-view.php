<?php
$photo=$pdo->prepare("SELECT id FROM hr_media WHERE user_id=? AND purpose='profile' LIMIT 1");$photo->execute([$owner]);$hasPhoto=(bool)$photo->fetchColumn();
$initials=strtoupper(substr($employee['first_name']??'',0,1).substr($employee['last_name']??'',0,1));
?>
<section class="panel profile-summary">
 <div class="profile-identity"><?php if($hasPhoto):?><img class="ess-avatar" src="media.php?employee=<?=$owner?>" alt="My profile photo"><?php else:?><span class="ess-avatar profile-initials" aria-hidden="true"><?=h($initials)?></span><?php endif;?>
 <div><span class="eyebrow">EMPLOYEE PROFILE</span><h2><?=h($user['name'])?></h2><p><?=h($employee['designation_name']?:'Designation not assigned')?></p><span class="badge"><?=h($employee['employee_code']?:'ID not assigned')?></span></div></div>
 <dl class="profile-contact"><div><dt>Work email</dt><dd><?=h($employee['email']?:'Not added')?></dd></div><div><dt>Department</dt><dd><?=h($employee['department_name']?:'Not assigned')?></dd></div><div><dt>Joined</dt><dd><?=h($employee['joining_date']?:'Not entered')?></dd></div><div><dt>Employment status</dt><dd><span class="badge"><?=h($employee['employment_status'])?></span></dd></div></dl>
</section>
<nav class="ess-tabs" aria-label="My profile sections"><a href="?page=profile" aria-current="page">Overview</a><a href="?page=my_shift">My shift</a><a href="?page=documents">Documents</a><a href="?page=my_salary">Salary</a><a href="?page=reviews">Appraisals</a></nav>
<div class="profile-grid">
<section class="panel"><h2>Employment details</h2><dl class="ess-details"><?php foreach(['employee_code'=>'Employee ID','department_name'=>'Department','designation_name'=>'Designation','employment_type'=>'Employment type','joining_date'=>'Joining date'] as $key=>$label):?><div><dt><?=h($label)?></dt><dd><?=h($employee[$key]?:'Not entered')?></dd></div><?php endforeach;?></dl><p class="ess-hint">Employment details are managed by HR.</p></section>
<?php require __DIR__.'/personal-cards.php';?>
</div>
<section class="panel profile-editor"><div class="ess-section-heading"><div><h2>Personal & emergency contact</h2><p>Keep your contact details up to date.</p></div><span class="badge">Personal information</span></div>
<form method="post" enctype="multipart/form-data" class="work-form ess-profile-form"><?php token();?><input type="hidden" name="action" value="ess_profile"><input type="hidden" name="version" value="<?=(int)$employee['version']?>">
<label class="ess-full">Profile photo<input type="file" name="photo" accept=".jpg,.jpeg,.png"><small>JPEG or PNG · maximum 2 MB</small></label>
<?php foreach(['personal_email'=>'Personal email','mobile'=>'Mobile number','current_address'=>'Current address','permanent_address'=>'Permanent address','emergency_name'=>'Emergency contact name','emergency_phone'=>'Emergency phone','emergency_relationship'=>'Relationship'] as $key=>$label):?><label><?=h($label)?><?php if(str_contains($key,'address')):?><textarea name="<?=h($key)?>" maxlength="2000" rows="3"><?=h($employee[$key]??'')?></textarea><?php else:?><input name="<?=h($key)?>" value="<?=h($employee[$key]??'')?>" type="<?=$key==='personal_email'?'email':(in_array($key,['mobile','emergency_phone'],true)?'tel':'text')?>"><?php endif;?></label><?php endforeach;?><div class="ess-full ess-form-footer"><span>Changes apply to your personal profile.</span><button class="primary">Save my profile</button></div></form></section>
