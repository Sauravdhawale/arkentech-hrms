<?php
// Approval routes are stored in existing versioned HR records; no employee data is copied.
function ess_route(PDO $db,int $employee):array {
 $q=$db->prepare("SELECT * FROM hr_records WHERE module='leave_route' AND employee_id=? AND status='Active' ORDER BY id DESC LIMIT 1");$q->execute([$employee]);$r=$q->fetch(PDO::FETCH_ASSOC);
 if($r){$v=json_decode($r['data'],true);$ids=array_map('intval',$v['approvers']??[]);$mode=$v['mode']??'sequential';}
 else{$q=$db->prepare('SELECT manager_id FROM employees WHERE user_id=? AND deleted_at IS NULL');$q->execute([$employee]);$manager=(int)$q->fetchColumn();$ids=$manager?[$manager]:[];$mode='any';}
 $people=[];foreach(array_unique($ids) as $id){$q=$db->prepare('SELECT id,name,role FROM users WHERE id=? AND active=1');$q->execute([$id]);$a=$q->fetch(PDO::FETCH_ASSOC);if(!$a||$id===$employee||!can($db,$a,'leave.approve'))throw new InvalidArgumentException('The assigned leave approver is unavailable or lacks approval permission. Ask HR to update the route.');$people[]=$a;}
 // Preserve the established Super Admin review queue when no reporting route exists.
 if(!$people){$q=$db->prepare("SELECT id,name,role FROM users WHERE role='super_admin' AND active=1 AND id<>? ORDER BY id");$q->execute([$employee]);$people=$q->fetchAll(PDO::FETCH_ASSOC);$mode='any';}
 if(!$people)throw new InvalidArgumentException('No eligible leave approver is configured. Contact HR.');return ['mode'=>$mode,'approvers'=>$people,'decisions'=>[],'stage'=>0];
}
function ess_snapshot_route(PDO $db,array $actor,int $request,int $employee):void {
 $route=ess_route($db,$employee);$db->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('leave_workflow',?,?,'Active',?,?)")->execute([$employee,'Leave #'.$request,json_encode($route+['request_id'=>$request]),$actor['id']]);
}
function ess_workflow(PDO $db,int $request):?array {
 $q=$db->prepare("SELECT * FROM hr_records WHERE module='leave_workflow' AND title=? ORDER BY id DESC LIMIT 1");$q->execute(['Leave #'.$request]);$r=$q->fetch(PDO::FETCH_ASSOC);if($r)$r['values']=json_decode($r['data'],true);return $r?:null;
}
function ess_can_review(PDO $db,array $actor,array $request):bool {
 if((int)$request['user_id']===(int)$actor['id']||!can($db,$actor,'leave.approve'))return false;
 $w=ess_workflow($db,(int)$request['id']);if(!$w)return $actor['role']==='super_admin';$v=$w['values'];
 if($actor['role']==='super_admin')return true;
 $ids=$v['mode']==='any'?array_column($v['approvers'],'id'):[$v['approvers'][$v['stage']]['id']??0];return in_array((int)$actor['id'],array_map('intval',$ids),true);
}
function ess_review_step(PDO $db,array $actor,array $request,string $status,string $reason):bool {
 if(!ess_can_review($db,$actor,$request))throw new InvalidArgumentException('This request is outside your assigned approval scope.');
 if($status==='Rejected'&&trim($reason)==='')throw new InvalidArgumentException('A rejection reason is required.');$w=ess_workflow($db,(int)$request['id']);if(!$w)return true;$v=$w['values'];$eligible=$v['mode']==='any'?array_column($v['approvers'],'id'):[$v['approvers'][$v['stage']]['id']??0];$override=!in_array((int)$actor['id'],array_map('intval',$eligible),true);
 if(($override||$status==='Rejected')&&trim($reason)==='')throw new InvalidArgumentException('A reason is required for rejection or an administrator override.');
 $v['decisions'][]=['actor_id'=>(int)$actor['id'],'name'=>$actor['name']??('User #'.$actor['id']),'status'=>$status,'reason'=>$reason,'at'=>date('Y-m-d H:i:s'),'override'=>$override];
 $final=$override||$status!=='Approved'||$v['mode']==='any'||$v['stage']>=count($v['approvers'])-1;if(!$final)$v['stage']++;
 $db->prepare('UPDATE hr_records SET data=?,status=?,version=version+1 WHERE id=?')->execute([json_encode($v),$final?'Completed':'Active',$w['id']]);return $final;
}
function ess_save_route(PDO $db,array $actor,array $in):void {
 if($actor['role']!=='super_admin')throw new InvalidArgumentException('Only Super Admin may configure leave approval routes.');
 $employee=(int)($in['employee_id']??0);chr_employee($db,$employee);$mode=(string)($in['mode']??'');if(!in_array($mode,['any','sequential'],true))throw new InvalidArgumentException('Choose a valid approval mode.');
 $ids=array_values(array_filter(array_map('intval',(array)($in['approvers']??[]))));if(!$ids||count($ids)>3||count(array_unique($ids))!==count($ids))throw new InvalidArgumentException('Choose one to three different approvers.');
 foreach($ids as $id){$q=$db->prepare('SELECT id,name,role FROM users WHERE id=? AND active=1');$q->execute([$id]);$person=$q->fetch(PDO::FETCH_ASSOC);if(!$person||$id===$employee||!can($db,$person,'leave.approve'))throw new InvalidArgumentException('Approvers must be active, have Leave Approve permission, and cannot be the applicant.');}
 $db->beginTransaction();try{chr_lock($db);$q=$db->prepare("SELECT id,version FROM hr_records WHERE module='leave_route' AND employee_id=? AND status='Active' ORDER BY id DESC LIMIT 1 FOR UPDATE");$q->execute([$employee]);$old=$q->fetch(PDO::FETCH_ASSOC);$data=json_encode(['mode'=>$mode,'approvers'=>$ids]);if($old){if((int)($in['version']??0)!==(int)$old['version'])throw new InvalidArgumentException('Route changed. Reload before saving.');$db->prepare('UPDATE hr_records SET data=?,version=version+1 WHERE id=?')->execute([$data,$old['id']]);}else{if((int)($in['version']??0)!==0)throw new InvalidArgumentException('Reload the route.');$db->prepare("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('leave_route',?,'Leave approval route','Active',?,?)")->execute([$employee,$data,$actor['id']]);}faudit($db,$actor,'leave.route.updated',$employee);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function ess_route_member(PDO $db,array $actor,array $request):bool {
 if((int)$request['user_id']===(int)$actor['id']||$actor['role']==='super_admin')return true;
 if(!can($db,$actor,'leave.approve'))return false;$w=ess_workflow($db,(int)$request['id']);return $w&&in_array((int)$actor['id'],array_map('intval',array_column($w['values']['approvers'],'id')),true);
}
