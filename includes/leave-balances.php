<?php
require_once __DIR__.'/core-hr/service.php';
function leave_used(PDO $pdo,int $employee,string $type,int $year,int $exclude=0): int|float {
 if(chr_ready($pdo))return chr_used($pdo,$employee,$type,$year,$exclude);
 $q=$pdo->prepare("SELECT start_date,end_date FROM hr_requests WHERE user_id=? AND kind='leave' AND category=? AND status='Approved' AND id<>? AND end_date>=? AND start_date<=?");$q->execute([$employee,$type,$exclude,"$year-01-01","$year-12-31"]);$dates=[];
 foreach($q as $r){$start=new DateTimeImmutable(max($r['start_date'],"$year-01-01"));$end=new DateTimeImmutable(min($r['end_date'],"$year-12-31"));for($d=$start;$d<=$end;$d=$d->modify('+1 day'))$dates[$d->format('Y-m-d')]=true;}
 return count($dates);
}
function leave_entitlement(PDO $pdo,int $employee,string $type,int $year): ?float {
 $q=$pdo->prepare("SELECT data FROM hr_records WHERE module='balances' AND employee_id=? AND status='Active' ORDER BY id DESC");$q->execute([$employee]);foreach($q as $r){$d=json_decode($r['data'],true);if((int)($d['year']??0)===$year)return (float)($d[$type]??0);}return null;
}
function validate_leave_approval(PDO $pdo,array $r): void {
 if(chr_ready($pdo)){chr_validate_leave($pdo,$r);return;}
 // Serialize approvals for this employee to prevent simultaneous over-allocation.
 $q=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$r['user_id']]);
 $q=$pdo->prepare("SELECT id FROM hr_requests WHERE kind='leave' AND user_id=? AND status='Approved' AND id<>? AND start_date<=? AND end_date>=? LIMIT 1");$q->execute([$r['user_id'],$r['id'],$r['end_date'],$r['start_date']]);if($q->fetchColumn())throw new InvalidArgumentException('This employee already has approved leave overlapping these dates.');
 if($r['category']==='LWP')return;
 $years=[];for($d=new DateTimeImmutable($r['start_date']);$d<=new DateTimeImmutable($r['end_date']);$d=$d->modify('+1 day')){$year=(int)$d->format('Y');$years[$year]=($years[$year]??0)+1;}
 foreach($years as $year=>$days){$entitlement=leave_entitlement($pdo,(int)$r['user_id'],$r['category'],$year);if($entitlement===null)throw new InvalidArgumentException('Set leave entitlements for '.$year.' before approving paid leave.');if(leave_used($pdo,(int)$r['user_id'],$r['category'],$year,(int)$r['id'])+$days>$entitlement)throw new InvalidArgumentException('Insufficient '.$r['category'].' balance for '.$year.'.');}
}
