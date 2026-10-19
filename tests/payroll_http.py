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
 pages=['payroll_dashboard','payroll_payslips','salary_components','payslip_settings']
 for page in pages:
  _,h=get(a,'super-admin.php?page='+page+'&month=2025-02')
  assert all(x not in h for x in ['Fatal error','Warning:','Parse error']),page
  assert 'Payroll' in h,page
 _,h=get(a,'super-admin.php?page=payroll_payslips&month=2025-02');assert 'Paid' in h and 'Payroll Test' in h
 for tab in ['general','rules','attendance','statutory','payslip']:
  _,h=get(a,'super-admin.php?page=salary_components&tab='+tab)
  assert all(x not in h for x in ['Fatal error','Warning:','Parse error']),tab
 # Invalid settings dates must preserve the entered values and unchecked boxes.
 _,settings_html=get(a,'super-admin.php?page=salary_components&tab=general')
 previous=re.search(r'name="previous_id" value="([^"]+)"',settings_html).group(1)
 _,failed_settings=post(a,'super-admin.php?page=salary_components&tab=general',{'csrf':token(settings_html),'action':'pay_config','module':'pay_settings','previous_id':previous,'title':'CI keep my settings','effective_from':'2020-01-01','active':'1','schedule_time':'14:45','closing_day':'2','proration':'Calendar Days','lop_basis':'GROSS','lop_days':'Absent + unpaid leave','ot_method':'Fixed Hourly Rate','ot_rate':'0','ot_basis':'GROSS','hours_per_day':'8','rounding':'Paise','currency':'INR','currency_position':'Before'})
 assert 'alert error' in failed_settings and 'CI keep my settings' in failed_settings and 'value="14:45"' in failed_settings
 for field in ['auto_payroll','auto_generate','approval_required','auto_payslip']:
  control=re.search(r'<input[^>]*name="'+field+r'"[^>]*>',failed_settings).group(0)
  assert 'checked' not in control,'Failed save restored checked '+field
 _,h=get(a,'payroll-slip.php?entry='+str(f['entry']));assert 'Payroll Test' in h and 'Net pay' in h
 with a.open(base+'payroll-slip.php?entry='+str(f['entry'])+'&download=pdf') as r:
  assert r.headers['Content-Type'].startswith('application/pdf') and r.read().startswith(b'%PDF-1.4')
 _,h=get(a,'payroll-test-preview.php');assert 'TEST COPY' in h and 'NOT FOR PAYMENT' in h and 'Payroll Test' in h
 owner=login('payroll.ci','Payroll-CI-2026!')
 _,draft=get(a,'payroll-slip.php?entry='+str(f['draft_entry']));assert 'DRAFT' in draft and 'Payroll Test' in draft
 try:get(owner,'payroll-slip.php?entry='+str(f['draft_entry']));raise AssertionError('Employee accessed unpublished draft')
 except urllib.error.HTTPError as e:assert e.code==404
 _,own=get(owner,'payroll-slip.php?entry='+str(f['entry']));assert 'Payroll Test' in own and 'Net pay' in own
 _,own=get(owner,'employee.php?page=payroll');assert 'Published payslips' in own and 'Current salary' in own
 _,own=get(owner,'employee.php?page=profile&tab=payroll');assert 'Current salary' in own
 _,h=get(a,'super-admin.php?page=employee-view&id='+str(f['employee'])+'&tab=payroll');assert 'Current salary' in h and 'Set / revise salary' in h
 viewer=login('test.two' ,'User@123')
 try:get(viewer,'payroll-test-preview.php');raise AssertionError('Unauthorized test salary preview')
 except urllib.error.HTTPError as e:assert e.code==403
 for page in pages:
  try:get(viewer,'super-admin.php?page='+page);raise AssertionError('Unauthorized payroll page '+page)
  except urllib.error.HTTPError as e:assert e.code==403
 try:get(viewer,'payroll-slip.php?entry='+str(f['entry']));raise AssertionError('Payslip IDOR')
 except urllib.error.HTTPError as e:assert e.code==404
 _,h=get(viewer,'employee.php?page=my_salary&employee_id='+str(f['employee']));assert 'Payroll Test' not in h and '30000.00' not in h
 _,h=get(viewer,'super-admin.php?page=overview')
 _,h=post(viewer,'super-admin.php?page=overview',{'csrf':token(h),'action':'pay_create','month':'2025-04'})
 assert 'Payroll permission required' in h
 try:post(a,'super-admin.php?page=payroll_payslips',{'csrf':'bad','action':'pay_create','month':'2025-04'});raise AssertionError('CSRF bypass')
 except urllib.error.HTTPError as e:assert e.code==403
 print('PASS: Payroll pages, report CSV, PDF, published snapshot, permission checks, IDOR and CSRF.')
finally:
 server.terminate();server.wait(timeout=10);log.close()





