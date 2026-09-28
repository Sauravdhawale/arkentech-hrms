<?php
$balanceSearch=trim((string)($_GET['search']??''));
$balanceEmployees=array_filter($employeeOptions,fn($label)=>$balanceSearch===''||stripos($label,$balanceSearch)!==false);
$balancePages=max(1,(int)ceil(count($balanceEmployees)/25));
$balancePage=min($balancePages,max(1,(int)($_GET['p']??1)));
$balanceEmployees=array_slice($balanceEmployees,($balancePage-1)*25,25,true);
?>
<section class="card balance-card">
<h2>Yearly leave balances · <?=h($year)?></h2>
<p class="hint">Remaining days by leave type. Approved leave is deducted; pending requests are not.</p>
<form method="get" class="toolbar balance-filters">
<?php chr_hidden('page','balances');field('year','Year',['year'=>$year],'number',true);field('search','Search employee name or ID',['search'=>$balanceSearch],'text'); ?>
<button class="button">Apply filters</button><a class="button" href="?page=balances&amp;year=<?=(int)$year?>">Reset</a>
</form>
<div class="table-scroll balance-table" tabindex="0" aria-label="Yearly employee leave balances">
<table class="directory"><thead><tr>
<th scope="col" style="min-width:220px;position:sticky;left:0;top:0;z-index:3;background:var(--card,#fff)">Employee / ID</th>
<?php foreach($leaveOptions as $code=>$name):?><th scope="col" style="min-width:170px;position:sticky;top:0;z-index:2;background:var(--card,#fff)"><?=h($name)?></th><?php endforeach;?>
</tr></thead><tbody>
<?php if(!$balanceEmployees):?><tr><td colspan="<?=1+count($leaveOptions)?>">No matching employees.</td></tr><?php endif;?>
<?php foreach($balanceEmployees as $id=>$label):?><tr>
<th scope="row" style="position:sticky;left:0;z-index:1;background:var(--card,#fff);text-transform:none;letter-spacing:normal"><?=h($label)?></th>
<?php foreach($leaveOptions as $code=>$name):$allocated=chr_entitlement($pdo,(int)$id,$code,$year);$used=chr_used($pdo,(int)$id,$code,$year);?>
<td><strong><?=h($allocated===null?'Not set':$allocated-$used)?></strong><?php if($allocated!==null):?> days<?php endif;?><div class="hint">Allocated: <?=h($allocated??'Not set')?> · Used: <?=h($used)?></div></td>
<?php endforeach;?></tr><?php endforeach;?>
</tbody></table></div>
<?php if(!$leaveOptions):?><p>No leave types configured. Add leave types to see their yearly balances here.</p><?php endif;?>
<nav class="pagination balance-pagination" aria-label="Employee balance pages">
<?php for($i=max(1,$balancePage-2);$i<=min($balancePages,$balancePage+2);$i++):?>
<a class="button" <?=$i===$balancePage?'aria-current="page"':''?> href="?<?=h(http_build_query(['page'=>'balances','year'=>$year,'search'=>$balanceSearch,'p'=>$i]))?>"><?=$i?></a>
<?php endfor;?></nav></section>
