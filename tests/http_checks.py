"""HTTP regression checks against the isolated CI database, never production."""
import os, re, subprocess, time, urllib.request, urllib.parse, http.cookiejar
assert os.environ.get('DB_NAME') == 'peopleflow_ci'
base='http://127.0.0.1:8087/'
log=open('/tmp/peopleflow-http-test.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8087','-t','.'],stdout=log,stderr=log)
def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(c,path):
 with c.open(base+path) as r: return r.geturl(),r.read().decode()
def post(c,path,values):
 with c.open(base+path,urllib.parse.urlencode(values).encode()) as r: return r.geturl(),r.read().decode()
def token(html): return re.search(r'name="csrf" value="([^"]+)"',html).group(1)
def login(username):
 c=client();_,h=get(c,'login.php');url,h=post(c,'login.php',{'csrf':token(h),'email':username,'password':'User@123'});assert 'login.php' not in url,(username,h[:200]);return c,url,h
try:
 for _ in range(50):
  try:get(client(),'login.php');break
  except OSError:time.sleep(.1)
 admin,_,_=login('test.admin')
 pages=['overview','employees','employment','devices','mapping','shifts','roster','holidays','balances','leave_policy','restricted','salary','contracts','advances','components','payroll','jobs','recruitment','interviews','offers','onboarding','exit_tasks','performance','reviews','pip','assets','access','policies','tasks','documents','expenses','settings','departments','designations','attendance','monthly','raw_logs','sync','reports','leaves','regularisation','helpdesk','announcements','offboarding','system']
 for page in pages:
  _,h=get(admin,'super-admin.php?page='+page)
  assert 'Fatal error' not in h and 'Parse error' not in h,page
 emp,_,_=login('test.one');_,h=get(emp,'employee.php?page=employment');assert 'Private One' in h and 'Private Two' not in h
 _,h=get(emp,'employee.php?page=payroll');assert 'Visible payslip' in h and 'Hidden draft' not in h
 _,h=get(emp,'employee.php?page=tasks');url,h=post(emp,'employee.php?page=tasks',{'csrf':token(h),'action':'suite_save','employee_id':'3','title':'Forbidden write','status':'Completed'});assert 'requires administrator access' in h
 _,h=get(admin,'super-admin.php?page=tasks&new=1');post(admin,'super-admin.php?page=tasks',{'csrf':token(h),'action':'suite_save','employee_id':'2','title':'HTTP assigned task','status':'To do','due':'2026-10-01','priority':'Normal','instructions':'A synthetic test task'})
 _,h=get(emp,'employee.php?page=tasks');assert 'HTTP assigned task' in h
 print('PASS: admin sections render, username login, employee record isolation, payroll visibility, rejected unauthorized write and persisted task.')
finally:
 server.terminate();server.wait(timeout=10);log.close()
 if __import__('sys').exc_info()[0]: print(open('/tmp/peopleflow-http-test.log').read())
