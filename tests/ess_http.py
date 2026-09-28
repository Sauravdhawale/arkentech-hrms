"""Employee workspace smoke and direct-route isolation against disposable CI data."""
import os,re,subprocess,time,urllib.request,urllib.parse,urllib.error,http.cookiejar
assert os.environ.get('DB_NAME')=='peopleflow_ci'
base='http://127.0.0.1:8097/'
log=open('/tmp/ess-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8097','-t','.'],stdout=log,stderr=log)
def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(c,path):
 with c.open(base+path) as r:return r.read().decode()
def post(c,path,data):
 with c.open(base+path,urllib.parse.urlencode(data).encode()) as r:return r.read().decode()
def token(h):return re.search(r'name="csrf" value="([^"]+)"',h).group(1)
try:
 time.sleep(1)
 c=client();h=get(c,'login.php');post(c,'login.php',{'csrf':token(h),'email':'ess.applicant','password':'User@123'})
 for page in ['overview','profile','my_shift','attendance','attendance_sheet','balances','leave_history','inbox','notifications','my_salary','tasks','documents','performance','reviews','helpdesk','leaves','payroll']:
  h=get(c,'employee.php?page='+page)
  assert 'Employee workspace' in h,(page,h[-1000:])
  assert 'Fatal error' not in h and 'Warning:' not in h,page
 for page in ['roles','departments','designations','approvals','devices']:
  try:get(c,'employee.php?page='+page);raise AssertionError('Unscoped route '+page)
  except urllib.error.HTTPError as e:assert e.code==403
 h=get(c,'employee.php?page=profile');assert 'ESS-APPLICANT' in h and 'ESS-LEAD' not in h
 print('PASS: employee portal pages, private profile and forbidden routes.')
finally:
 server.terminate();server.wait();log.close()
