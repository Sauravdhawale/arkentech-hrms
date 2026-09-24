"""Run only against the disposable GitHub Actions database, never production."""
import os, subprocess
from pathlib import Path
assert os.environ.get('CI') == 'true'
DB='u491689847_employeeportal'
root=Path(__file__).resolve().parents[1]
args=['mysql','--protocol=TCP','-h127.0.0.1','-P3306','-uroot','--batch','--skip-column-names']
env=os.environ|{'MYSQL_PWD':'ci-only-password'}
def sql(text, database=True, okay=True):
 p=subprocess.run(args+([DB] if database else []),input=text,text=True,capture_output=True,env=env)
 if okay and p.returncode:
  raise AssertionError('Database import test failed: '+p.stderr[-1200:])
 return p

def fixture():
 sql('DROP DATABASE IF EXISTS '+DB+'; CREATE DATABASE '+DB+' CHARACTER SET utf8mb4;',False)
 for file in ['schema.sql','002-workspaces.sql','003-hr-suite.sql','004-foundation.sql','005-core-hr.sql']:
  sql((root/'database'/file).read_text())
 sql("""ALTER TABLE users MODIFY email VARCHAR(190) NULL, ADD username VARCHAR(190) NULL UNIQUE, ADD must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
 INSERT INTO users(id,name,email,username,password_hash,role) VALUES(1,'CI Admin','ci@example.invalid','admin','preserve-admin','super_admin'),(3,'Saurav Raju Dhawale',NULL,'saurav.dhawale','preserve-three','employee'),(4,'Prashant Ganesh Shinde',NULL,'prashant.shinde','preserve-four','employee');
 INSERT INTO employees(user_id,employee_code,first_name,middle_name,last_name,mobile,import_notes) VALUES(3,'AT00177','Saurav','Raju','Dhawale','do-not-change','existing profile'),(4,'AT00223','Prashant','Ganesh','Shinde','do-not-change','existing profile');
 INSERT INTO roles(id,name,slug) VALUES(1,'Employee','employee');
 INSERT INTO user_roles VALUES(3,1),(4,1);
 INSERT INTO hr_records(module,title,status,data,created_by) VALUES('devices','CI eSSL','Enabled','{"device_code":"CQQC231360514"}',1);
 INSERT INTO hr_punches(device_code,biometric_id,event_key,punched_at,direction) VALUES('CQQC231360514','177','preserve-punch','2026-02-01 09:00:00','in');
 INSERT INTO hr_attendance(employee_id,attendance_date,check_in,shift_snapshot,status,source,notes,updated_by) VALUES(3,'2026-02-01','2026-02-01 09:00:00','null','Present','Manual','preserve attendance',1);
 """)

def value(q):return sql(q).stdout.strip()
source=(root/'employee-import/Arkentech_Employees_Database_Import.sql').read_text()
fixture()
assert '105\t2\t107' in sql(source).stdout
assert value('SELECT COUNT(*) FROM employees')=='107'
assert value('SELECT COUNT(*) FROM users')=='108'
assert value("SELECT COUNT(*) FROM users WHERE id IN (1,3,4) AND password_hash LIKE 'preserve-%'")=='3'
assert value("SELECT COUNT(*) FROM employees WHERE mobile='do-not-change' AND import_notes='existing profile'")=='2'
assert value("SELECT COUNT(*) FROM hr_records WHERE module='mapping'")=='107'
assert value('SELECT COUNT(*) FROM user_roles')=='107'
assert value("SELECT COUNT(*) FROM users WHERE must_change_password=1")=='105'
assert value("SELECT COUNT(*) FROM hr_attendance WHERE source='Manual' AND notes='preserve attendance'")=='1'
assert value("SELECT COUNT(*) FROM hr_punches WHERE event_key='preserve-punch'")=='1'
assert '0\t107\t0' in sql(source).stdout
# Verify generated bcrypt hashes through the same PHP verifier used by HRMS.
hash_value=value("SELECT password_hash FROM users WHERE username='mehul.purohit'")
p=subprocess.run(['php','-r',"exit(password_verify('User@123',trim(stream_get_contents(STDIN)))?0:1);"],input=hash_value,text=True)
assert p.returncode==0
# A conflict late in the batch must roll back all newly added employees and mappings.
fixture()
sql("INSERT INTO hr_records(module,employee_id,title,status,data,created_by) VALUES('mapping',3,'Conflict','Active','{\"device_code\":\"CQQC231360514\",\"biometric_id\":\"343\"}',1)")
p=sql(source,okay=False)
assert p.returncode!=0 and 'Conflicting eSSL mapping' in p.stderr
assert value('SELECT COUNT(*) FROM employees')=='2'
assert value('SELECT COUNT(*) FROM users')=='3'
assert value("SELECT COUNT(*) FROM hr_records WHERE module='mapping'")=='1'
# Existing unmapped account names are not silently duplicated.
fixture()
sql("INSERT INTO users(name,username,password_hash,role) VALUES('Mehul Purohit','legacy.mehul','preserve-existing','employee')")
assert sql(source,okay=False).returncode!=0
assert value('SELECT COUNT(*) FROM employees')=='2'
print('PASS: import counts, existing profile/password preservation, mappings, password verification, rerun safety, conflict rollback and existing-name protection.')
