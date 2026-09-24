"""Payroll HTTP permission, route, snapshot and download checks against the disposable CI database."""
import os,re,subprocess,time,urllib.request,urllib.parse,urllib.error,http.cookiejar,json
assert os.environ.get('DB_NAME')=='peopleflow_ci'
base='http://127.0.0.1:8093/'
log=open('/tmp/payroll-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8093','-t','.'],stdout=log,stderr=log)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(c,path):
 with c.open(base+path) as r:return r.geturl(),r.read().decode()
def post(c,path,v):
 with c.open(base+path,urllib.parse.urlencode(v,doseq=True).encode()) as r:return r.geturl(),r.read().decode()
def token(h):return re.search(r'name="csrf" value="([^"]+)"',h).group(1)
def login(name,password):
 c=client();_,h=get(c,'login.php');url,h=post(c,'login.php',{'csrf':token(h),'email':name,'password':password});assert 'login.php' not in url;return c
try:
 for _ in range(50):
  try:get(client(),'login.php');break
  except OSError:time.sleep(.1)
 a=login('test.admin','Admin-Changed-2026!')
 f=json.load(open('/tmp/payroll-fixture.json'))
 pages=['payroll_dashboard','employee_salary','payroll_processing','payroll_payslips','payroll_history','payroll_reports','salary_components','salary_structures','statutory_settings','payroll_settings','payslip_settings']
 for page in pages:
  _,h=get(a,'super-admin.php?page='+page+'&month=2025-02')
  assert all(x not in h for x in ['Fatal error','Warning:','Parse error']),page
  assert 'Payroll' in h,page
 _,h=get(a,'super-admin.php?page=payroll_processing&run='+str(f['run']));assert 'Paid' in h and 'Payroll Test' in h
 _,h=get(a,'payroll-slip.php?entry='+str(f['entry']));assert 'Payroll Test' in h and 'Net pay' in h
 with a.open(base+'payroll-slip.php?entry='+str(f['entry'])+'&download=pdf') as r:
  assert r.headers['Content-Type'].startswith('application/pdf') and r.read().startswith(b'%PDF-1.4')
 with a.open(base+'super-admin.php?page=payroll_reports&month=2025-02&export=csv') as r:
  assert 'text/csv' in r.headers['Content-Type'];assert 'PAY-CI-001' in r.read().decode()
 viewer=login('test.two','User@123')
 for page in pages:
  try:get(viewer,'super-admin.php?page='+page);raise AssertionError('Unauthorized payroll page '+page)
  except urllib.error.HTTPError as e:assert e.code==403
 try:get(viewer,'payroll-slip.php?entry='+str(f['entry']));raise AssertionError('Payslip IDOR')
 except urllib.error.HTTPError as e:assert e.code==404
 _,h=get(viewer,'super-admin.php?page=overview')
 _,h=post(viewer,'super-admin.php?page=overview',{'csrf':token(h),'action':'pay_create','month':'2025-04'})
 assert 'Payroll permission required' in h
 try:post(a,'super-admin.php?page=payroll_processing',{'csrf':'bad','action':'pay_create','month':'2025-04'});raise AssertionError('CSRF bypass')
 except urllib.error.HTTPError as e:assert e.code==403
 print('PASS: Payroll pages, report CSV, PDF, published snapshot, permission checks, IDOR and CSRF.')
finally:
 server.terminate();server.wait(timeout=10);log.close()
