<?php
require __DIR__.'/core_hr_calculations.php';
$s=['id'=>10,'title'=>'Day','values'=>['start'=>'09:00','end'=>'18:00','required_hours'=>8,'half_day_hours'=>4,'break_minutes'=>60,'grace'=>10,'early_grace'=>5,'overtime_rule'=>'After both']];$other=$s;$other['id']=11;$other['title']='Other';
$a=fn($id,$scope,$shift)=>['id'=>$id,'status'=>'Active','values'=>['scope'=>$scope,'department_id'=>2,'shift_id'=>$shift,'from'=>'2026-01-01','to'=>null]];
$ctx=['shifts'=>[10=>$s,11=>$other],'assignments'=>[],'defaults'=>[$a(1,'Company',10),$a(2,'Department',11)],'department_history'=>[],'policies'=>[]];$e=['user_id'=>1,'department_id'=>2];
expect(att_resolve($ctx,$e,'2026-01-05')['assignment_source']==='Department','Department default');$ctx['assignments'][1]=[$a(3,'Employee',10)];expect(att_resolve($ctx,$e,'2026-01-05')['assignment_source']==='Employee','Employee override');$ctx['assignments'][1]=[];$e['department_id']=3;expect(att_resolve($ctx,$e,'2026-01-05')['assignment_source']==='Company','Company fallback');
$ctx['department_history'][1]=[['values'=>['date'=>'2026-02-01','before'=>2,'after'=>3]]];expect(att_resolve($ctx,$e,'2026-01-05')['assignment_source']==='Department','Historical department');
$ctx['defaults'][]=$a(4,'Company',10);invalid(fn()=>att_resolve($ctx,$e,'2026-03-01'),'Overlapping defaults rejected');array_pop($ctx['defaults']);
$policy=['policy_id'=>1,'minimum_hours'=>2,'half_day_hours'=>4,'required_hours'=>8];
foreach([['12:00','Absent'],['14:00','Half Day'],['18:00','Present']] as [$end,$status]){$r=chr_calculate('2026-01-05','2026-01-05 09:00:00','2026-01-05 '.$end.':00',array_merge($s['values'],$policy));expect($r['status']===$status,'Policy duration '.$status);}
echo "PASS: employee/department/company precedence, dated department history, overlap detection and policy thresholds.\n";
