<?php
require_once dirname(__DIR__).'/ess/punch-status.php';
/** A re-entry opens attendance again. Only an OUT event can close it. */
function att_biometric_bounds(array $punches):array {
 $sequence=ess_punch_sequence($punches);$first=null;$lastOut=null;
 foreach($sequence['events'] as $event){
  if($event['state']==='in'&&$first===null)$first=date('Y-m-d H:i:s',$event['at']);
  if($first!==null&&$event['state']==='out')$lastOut=date('Y-m-d H:i:s',$event['at']);
 }
 return [$first,$sequence['state']==='out'?$lastOut:null];
}
/** Read-only compatibility for records saved by the old first/last-punch rule. */
function att_biometric_record(array $record,array $punches):array {
 if(($record['source']??'')!=='Biometric'||!$punches)return $record;
 [$in,$out]=att_biometric_bounds($punches);
 if(!$in||($in===$record['check_in']&&$out===$record['check_out']))return $record;
 try{$metrics=chr_calculate($record['attendance_date'],$in,$out,json_decode($record['shift_snapshot'],true),true);}
 catch(InvalidArgumentException $e){return array_replace($record,['status'=>'Review needed']);}
 return array_replace($record,$metrics,['check_in'=>$in,'check_out'=>$out]);
}
