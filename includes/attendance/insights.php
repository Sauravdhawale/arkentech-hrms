<?php
// Presentation-only aggregation of the same rows used by the attendance reports.
function att_insights(array $rows):array {
 $result=['arrivals'=>0,'on_time'=>0,'late'=>0,'absent'=>0,'worked'=>0,'overtime'=>0,'departments'=>[],'employees'=>[],'days'=>[]];
 foreach($rows as $r){
  if(in_array($r['status'],['Not started','Not active'],true))continue;
  $dept=$r['department']?:'Unassigned';$id=(int)$r['employee_id'];$date=$r['date'];
  $result['departments'][$dept]??=['label'=>$dept,'arrivals'=>0,'present'=>0,'absent'=>0,'late'=>0,'late_minutes'=>0];
  $result['employees'][$id]??=['label'=>$r['name'],'code'=>$r['employee_code'],'department'=>$dept,'late'=>0,'late_minutes'=>0];
  $result['days'][$date]??=['present'=>0,'absent'=>0,'leave'=>0];
  $arrived=!empty($r['check_in']);$late=$arrived&&(int)$r['late_minutes']>0;
  $result['arrivals']+=(int)$arrived;$result['on_time']+=(int)($arrived&&!$late);$result['late']+=(int)$late;
  $result['absent']+=(float)$r['absent'];$result['worked']+=(int)$r['working_minutes'];$result['overtime']+=(int)$r['overtime_minutes'];
  $result['departments'][$dept]['arrivals']+=(int)$arrived;
  $result['departments'][$dept]['present']+=(float)$r['present'];$result['departments'][$dept]['absent']+=(float)$r['absent'];
  $result['departments'][$dept]['late']+=(int)$late;$result['departments'][$dept]['late_minutes']+=(int)$r['late_minutes'];
  $result['employees'][$id]['late']+=(int)$late;$result['employees'][$id]['late_minutes']+=(int)$r['late_minutes'];
  foreach(['present','absent','leave'] as $key)$result['days'][$date][$key]+=(float)$r[$key];
 }
 foreach($result['departments'] as &$department){$decided=$department['present']+$department['absent'];$department['rate']=$decided>0?round(100*$department['present']/$decided,1):null;$department['late_rate']=$department['arrivals']>0?round(100*$department['late']/$department['arrivals'],1):null;}unset($department);
 ksort($result['days']);return $result;
}
function att_insight_bars(array $items,string $metric,string $suffix='',?float $scale=null):void {
 if(!$items){echo '<p class="att-empty">No matching data for this period.</p>';return;}
 $max=$scale??max(1,...array_map(fn($item)=>(float)($item[$metric]??0),$items));
 echo '<ol class="att-bars">';foreach($items as $item){$value=$item[$metric]??null;$width=$value===null?0:min(100,max(0,100*(float)$value/max(1,$max)));?>
 <li><div class="att-bar-label"><span><?=h($item['label'])?><?php if(isset($item['code'])):?><small><?=h($item['code'])?> · <?=h($item['department'])?></small><?php endif;?></span><strong><?=h($value===null?'—':$value.$suffix)?></strong></div><div class="att-bar-track" aria-hidden="true"><span style="width:<?=round($width,2)?>%"></span></div><?php if($metric==='late'&&isset($item['late_minutes'])):?><small><?=h(chr_hours($item['late_minutes']))?> late in total</small><?php endif;?></li>
 <?php }echo '</ol>';
}
