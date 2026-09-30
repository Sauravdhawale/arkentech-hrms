<?php
/** Caller owns the transaction. Never disable foreign keys or commit partial cleanup. */
function foundation_permanently_delete_employee(PDO $db,array $actor,int $id):void {
 if(($actor['role']??'')!=='super_admin'||$id===(int)$actor['id']||!$db->inTransaction())throw new InvalidArgumentException('Only Super Admin can permanently delete another employee.');
 $q=$db->prepare('SELECT u.role,e.version FROM users u JOIN employees e ON e.user_id=u.id WHERE u.id=? FOR UPDATE');$q->execute([$id]);$target=$q->fetch(PDO::FETCH_ASSOC);
 if(!$target||$target['role']!=='employee')throw new InvalidArgumentException('Employee not found or account protected.');
 $tables=array_flip($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
 $run=function(string $table,string $sql)use($db,$tables,$id){if(isset($tables[$table])){$q=$db->prepare($sql);$q->execute([$id]);}};
 if(isset($tables['hr_payroll_entries'])){$q=$db->prepare('SELECT id FROM hr_payroll_entries WHERE employee_id=? LIMIT 1');$q->execute([$id]);if($q->fetchColumn())throw new InvalidArgumentException('Permanent deletion blocked: this employee has payroll entries. Resolve payroll records first.');}
 $run('hr_leave_days','DELETE FROM hr_leave_days WHERE request_id IN (SELECT id FROM hr_requests WHERE user_id=?)');
 $run('hr_leave_details','DELETE FROM hr_leave_details WHERE request_id IN (SELECT id FROM hr_requests WHERE user_id=?)');
 $run('hr_requests','DELETE FROM hr_requests WHERE user_id=?');
 $run('hr_salary_assignments','DELETE FROM hr_salary_assignments WHERE employee_id=?');
 $run('hr_manual_punch_events','DELETE FROM hr_manual_punch_events WHERE employee_id=?');
 $run('hr_attendance','DELETE FROM hr_attendance WHERE employee_id=?');
 $run('hr_acknowledgements','DELETE FROM hr_acknowledgements WHERE user_id=?');
 $run('hr_acknowledgements','DELETE FROM hr_acknowledgements WHERE record_id IN (SELECT id FROM hr_records WHERE employee_id=?)');
 $run('hr_files','DELETE FROM hr_files WHERE record_id IN (SELECT id FROM hr_records WHERE employee_id=?)');
 $run('hr_records','DELETE FROM hr_records WHERE employee_id=?');
 // Keep immutable device receipts/deduplication keys; remove the employee association.
 $run('hr_punch_processing',"UPDATE hr_punch_processing SET employee_id=NULL,attendance_date=NULL,status='Unmapped',error_message='Employee permanently deleted.',processed_at=NULL WHERE employee_id=?");
 $run('hr_media','DELETE FROM hr_media WHERE user_id=?');
 $run('password_resets','DELETE FROM password_resets WHERE user_id=?');
 $run('user_roles','DELETE FROM user_roles WHERE user_id=?');
 $run('hr_audit','DELETE FROM hr_audit WHERE actor_id=?');
 $run('departments','UPDATE departments SET head_id=NULL WHERE head_id=?');
 $run('employees','UPDATE employees SET manager_id=NULL WHERE manager_id=?');
 $run('employees','DELETE FROM employees WHERE user_id=?');
 $run('users',"DELETE FROM users WHERE id=? AND role='employee'");
 faudit($db,$actor,'employee.permanently_deleted',$id);
}
