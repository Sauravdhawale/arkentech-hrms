<?php
/** Explicit POST setup. No employee assignment, payroll recalculation or existing revision changes. */
function pay_install_starter(PDO $db,array $actor):int {
 pay_allow($db,$actor,'payroll.settings.manage');
 if(!pay_ready($db))throw new InvalidArgumentException('Enable payroll first.');
 $db->beginTransaction();try{
  chr_lock($db);$from=date('Y-m-01');$marker='Arkentech Annexure starter v1';
  $save=function(string $module,string $title,array $v)use($db,$actor,$from,$marker):array{
   foreach(chr_rows($db,$module) as $r)if(($r['values']['code']??'DEFAULT')===($v['code']??'DEFAULT')){
    if(!in_array($module,['pay_settings','pay_payslip'],true)&&($r['values']['description']??'')!==$marker)throw new InvalidArgumentException('Starter code already exists: '.$v['code'].'. Review the existing configuration before setup.');
    return $r;
   }
   $v+=['code'=>'DEFAULT','effective_from'=>$from,'effective_to'=>null,'description'=>$marker];
   $db->prepare("INSERT INTO hr_records(module,title,status,data,created_by) VALUES(?,?,'Active',?,?)")->execute([$module,$title,pay_json($v),$actor['id']]);$id=(int)$db->lastInsertId();faudit($db,$actor,'payroll.starter.created',$id);
   return ['id'=>$id,'version'=>1,'title'=>$title,'status'=>'Active','values'=>$v];
  };
  $base=['category'=>'Earning','calculation'=>'Percentage','section'=>'Fixed','basis'=>'GROSS','value'=>'0.00','formula'=>'','taxable'=>false,'proratable'=>true,'include_gross'=>true,'include_ctc'=>true,'visible'=>true];
  $definitions=[
   ['ARK_BASIC','Basic Salary',['value'=>'50.00']],
   ['ARK_HRA','House Rent Allowance',['basis'=>'ARK_BASIC','value'=>'50.00']],
   ['ARK_BONUS','Bonus',['basis'=>'ARK_BASIC','value'=>'8.33']],
   ['ARK_SPECIAL','Special Allowance',['calculation'=>'Balance']],
   ['ARK_VARIABLE','Variable Pay',['calculation'=>'Manual','section'=>'Variable']],
   ['ARK_PF','PF (manual)',['category'=>'Deduction','calculation'=>'Manual','include_gross'=>false,'include_ctc'=>false]],
   ['ARK_LOAN','Loan Recovery',['category'=>'Deduction','calculation'=>'Manual','include_gross'=>false,'include_ctc'=>false]],
   ['ARK_ADVANCE','Salary Advance Recovery',['category'=>'Deduction','calculation'=>'Manual','include_gross'=>false,'include_ctc'=>false]]
  ];$parts=[];
  foreach($definitions as [$code,$name,$overrides]){$r=$save('pay_component',$name,array_replace($base,['code'=>$code,'name'=>$name],$overrides));if($r['status']!=='Active'||$r['values']['effective_from']>$from||(!empty($r['values']['effective_to'])&&$r['values']['effective_to']<$from))throw new InvalidArgumentException('A starter component is inactive or outside its effective period. Review its configuration.');$parts[]=$r['values']+['record_id'=>(int)$r['id'],'record_version'=>(int)$r['version'];}
  $structure=['code'=>'ARK_ANNEXURE_A','components'=>$parts,'variable_enabled'=>true,'variable_cap'=>5];
  pay_breakup($structure,1900000,['ARK_VARIABLE'=>'0','ARK_PF'=>'0','ARK_LOAN'=>'0','ARK_ADVANCE'=>'0']);
  $r=$save('pay_structure','Arkentech Annexure A',$structure);
  $save('pay_settings','Payroll Rules',pay_setting_defaults());
  $save('pay_payslip','Annexure A Payslip',array_replace(pay_slip_defaults(),['template_mode'=>'html','html_template'=>pay_template_starters()['Annexure A']]));
  $db->commit();return (int)$r['id'];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
