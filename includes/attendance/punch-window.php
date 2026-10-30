<?php
/** Resolve the nearest effective shift instance, including both sides of midnight. */
function att_punch_shift(PDO $db,int $employee,string $timestamp):array {
 $at=new DateTimeImmutable($timestamp);$config=att_config($db);$before=(int)$config['punch_before_minutes'];$after=(int)$config['punch_after_minutes'];$candidates=[];
 $context=att_resolution_context($db);$person=chr_employee($db,$employee);
 foreach([-1,0,1] as $offset){
  $day=$at->modify(sprintf('%+d days',$offset))->format('Y-m-d');
  $shift=att_resolve($context,$person,$day);
  // Calculated attendance keeps its original effective policy, even after settings change.
  $q=$db->prepare('SELECT check_in,check_out,shift_id,shift_snapshot FROM hr_attendance WHERE employee_id=? AND attendance_date=?');$q->execute([$employee,$day]);$saved=$q->fetch(PDO::FETCH_ASSOC);
  $snapshot=$saved?json_decode($saved['shift_snapshot'],true):null;
  if(is_array($snapshot)&&isset($snapshot['start'],$snapshot['end']))$shift=['id'=>$saved['shift_id'],'values'=>$snapshot];
  if(!$shift||!isset($shift['values']['start'],$shift['values']['end']))continue;
  [$start,$end]=chr_shift_bounds($day,$shift['values']);
  // A saved workday can continue into overtime. The intake buffer is not an
  // automatic checkout, and a real late OUT must still close the same workday.
  // Stop assigning punches to it at the next scheduled start (or the existing
  // 36-hour attendance validation bound), never by inventing an OUT event.
  $continuation=false;
  if(!empty($saved['check_in'])&&$at>$end){
   $until=(new DateTimeImmutable($saved['check_in']))->modify('+36 hours');
   foreach([1,2] as $nextOffset){
    $nextDay=(new DateTimeImmutable($day))->modify('+'.$nextOffset.' days')->format('Y-m-d');
    $nextShift=att_resolve($context,$person,$nextDay);
    if(!$nextShift||!isset($nextShift['values']['start'],$nextShift['values']['end']))continue;
    [$nextStart]=chr_shift_bounds($nextDay,$nextShift['values']);
    if($nextStart>$end&&$nextStart<$until)$until=$nextStart;
   }
   $continuation=$at<$until;
  }
  if(!$continuation&&($at<$start->modify('-'.$before.' minutes')||$at>$end->modify('+'.$after.' minutes')))continue;
  $distance=$at<$start?$start->getTimestamp()-$at->getTimestamp():($at>$end?$at->getTimestamp()-$end->getTimestamp():0);
  // Prefer an ongoing workday over the next early-entry buffer, but a closed
  // workday must not steal a new day's early check-in.
  $candidates[]=['day'=>$day,'shift'=>$shift,'distance'=>$distance,'priority'=>$distance===0?0:($continuation&&empty($saved['check_out'])?1:2)];
 }
 if(!$candidates)throw new InvalidArgumentException('No valid shift window for this punch. Check effective shift assignment and before/after buffers.');
 usort($candidates,fn($a,$b)=>($a['priority']<=>$b['priority'])?:($a['distance']<=>$b['distance']));
 if(isset($candidates[1])&&$candidates[0]['priority']===$candidates[1]['priority']&&$candidates[0]['distance']===$candidates[1]['distance'])throw new InvalidArgumentException('Ambiguous overlapping shift windows. Review assignment dates and punch buffers.');
 return $candidates[0];
}

