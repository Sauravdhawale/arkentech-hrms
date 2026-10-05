<?php
if(($user['role']??'')!=='super_admin')return;
$items=['recruitment_dashboard'=>'Dashboard','recruitment_jobs'=>'Job Openings','recruitment_candidates'=>'Candidates','recruitment_applications'=>'Applications','recruitment_interviews'=>'Interviews','recruitment_offers'=>'Offers'];
$active=isset($items[$page]);
?>
<div class="nav-module <?=$active?'open':''?>">
 <button class="nav-module-title" type="button" aria-expanded="<?=$active?'true':'false'?>"><span aria-hidden="true">⌕</span>Recruitment <span aria-hidden="true">⌄</span></button>
 <div class="nav-module-items"><?php foreach($items as $key=>$label)fnav($key,$label);?></div>
</div>
