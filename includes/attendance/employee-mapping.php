<?php
/** Company employee codes keep their prefix; the terminal uses the numeric part. */
function att_employee_biometric_id(string $code):?string {
 if(!preg_match('/^AT([0-9]+)$/iD',trim($code),$m))return null;
 $id=ltrim($m[1],'0');return $id===''?null:$id;
}
/** Called inside employee creation so employee, shift and mapping save atomically. */
function att_map_new_employee(PDO $db,array $actor,int $employee,int $deviceId):string {
 att_require_biometric($db);
 if(!can($db,$actor,'employees.create')||!can($db,$actor,'biometric.manage'))throw new InvalidArgumentException('Automatic mapping requires employee creation and biometric management permissions.');
 if(!$db->inTransaction())throw new LogicException('Automatic mapping requires the employee transaction.');
 chr_lock($db);
 $person=foundation_employee($db,$employee);
 if(!$person||!in_array($person['employment_status'],['Active','Notice Period'],true))throw new InvalidArgumentException('Automatic biometric mapping requires an active employee.');
 $bio=att_employee_biometric_id((string)$person['employee_code']);
 if($bio===null)throw new InvalidArgumentException('Enter an Employee ID such as AT00341 for automatic mapping, or untick automatic mapping.');
 $device=att_device($db,$deviceId);
 if($device['status']!=='Enabled')throw new InvalidArgumentException('Select an enabled biometric device.');
 $same=null;
 foreach(chr_rows($db,'mapping') as $map){
  if(($map['values']['device_code']??'')!==$device['values']['device_code'])continue;
  $existing=(string)($map['values']['biometric_id']??'');
  $numeric=ctype_digit($existing)?ltrim($existing,'0'):$existing;
  if($numeric===$bio){
   if((int)$map['employee_id']!==$employee||$map['status']!=='Active'||$existing!==$bio||$same!==null)throw new InvalidArgumentException('Biometric ID '.$bio.' already has a mapping on this device. Review Employee Mapping; no existing mapping was changed.');
   $same=$map;
  }elseif((int)$map['employee_id']===$employee)throw new InvalidArgumentException('This employee already has a different mapping on this device. Review Employee Mapping.');
 }
 if(!$same){
  att_record_write($db,$actor,'mapping',['title'=>$person['employee_code'].' · automatic mapping'],['device_code'=>$device['values']['device_code'],'biometric_id'=>$bio,'valid_from'=>$person['joining_date'],'mapping_source'=>'employee_code'],$employee,'Active');
  faudit($db,$actor,'biometric.employee_auto_mapped',$employee);
 }
 $device['actor_id']=$actor['id'];att_retry_device_punches($db,$device,$bio);
 return $bio;
}
