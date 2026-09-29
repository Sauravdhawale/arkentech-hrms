<?php
/** Explicit, audited setup; retains IDs and assignment snapshots, never resets schedules. */
function att_setup_company_shifts(PDO $db,array $actor):void {
 if(!can($db,$actor,'shifts.manage'))throw new InvalidArgumentException('Shift management is not permitted.');
 $db->beginTransaction();try{chr_lock($db);
 foreach([['Day Shift','DAY','09:00','18:00',7.5],['Night Shift','NIGHT','18:00','09:00',13.5]] as [$title,$code,$start,$end,$hours]){
  $matches=array_values(array_filter(chr_rows($db,'shifts'),fn($r)=>strcasecmp(trim($r['title']),$title)===0||strcasecmp($r['values']['code']??'',$code)===0));
  if(count($matches)>1)throw new InvalidArgumentException('Multiple '.$title.' candidates exist. Review existing shifts before setup.');
  $old=$matches[0]??null;$v=$old['values']??[];
  $input=array_merge($v,['id'=>$old['id']??0,'version'=>$old['version']??0,'title'=>$title,'code'=>$v['code']??$code,'start'=>$start,'end'=>$end,'break_minutes'=>90,'required_hours'=>$hours,'half_day_hours'=>$v['half_day_hours']??($hours/2),'grace'=>$v['grace']??0,'early_grace'=>$v['early_grace']??0,'overtime_rule'=>$v['overtime_rule']??'After both','week_off'=>[6,7],'active'=>1]);
  chr_save_config($db,$actor,'shifts',$input);
 }
 $db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
