<?php
$filesQuery=$pdo->prepare("SELECT f.id,f.filename,r.title,r.status FROM hr_files f JOIN hr_records r ON r.id=f.record_id WHERE r.employee_id=? AND r.module='documents' ORDER BY f.id DESC LIMIT 5");$filesQuery->execute([$owner]);$submittedFiles=$filesQuery->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="panel"><span class="ess-card-icon" aria-hidden="true">▤</span><h2>My submitted documents</h2><?php if(!$submittedFiles):?><p>No documents submitted yet.</p><?php else:?><ul class="ess-document-list"><?php foreach($submittedFiles as $file):?><li><a href="document.php?id=<?=(int)$file['id']?>"><?=h($file['filename'])?></a><small><?=h($file['title'])?> · <?=h($file['status'])?></small></li><?php endforeach;?></ul><?php endif;?><a class="ess-text-link" href="?page=documents">View all documents →</a></section>

