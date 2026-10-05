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

 h=get(c,'employee.php?page=overview')
 assert 'Your monthly overview' in h and 'ess-balance-grid' in h and 'ess-event-list' in h
 sidebar=h.split('<nav class="grouped-nav">',1)[1].split('</nav>',1)[0]
 assert '?page=profile' not in sidebar and '?page=security' not in sidebar
 assert '?page=attendance_sheet' not in sidebar and '?page=balances' not in sidebar
 assert 'Profile & account' in h and 'data-ess-theme' in h and 'data-dialog-open="correction-dialog"' in h
 h=get(c,'employee.php?page=attendance&month=2030-02')
 assert 'February 2030' in h and 'Late arrivals' in h and 'Early departures' in h and 'id="correction-dialog"' in h
 h=post(c,'employee.php?page=regularisation',{'csrf':token(h),'action':'request','category':'Missed punch','subject':'ESS popup correction','details':'Please check the missing punch','start_date':'2030-02-06','end_date':'2030-02-06'})
 h=get(c,'employee.php?page=attendance&month=2030-02')
 assert 'ESS popup correction' in h and 'Pending' in h
 h=get(c,'employee.php?page=leaves&year=2030')
 assert 'Leave balance · 2030' in h and 'ESS isolated request' in h and 'Approved' in h and 'id="leave-dialog"' in h
 h=post(c,'employee.php?page=leaves',{'csrf':token(h),'action':'request','category':'INVALID','subject':'Retain my input','details':'Validation example','start_date':'2030-02-08','end_date':'2030-02-08'})
 assert 'data-open-on-load' in h and 'Retain my input' in h
 for tab,marker in [('overview','Personal & emergency contact'),('shift','My effective shift'),('documents','Document file'),('security','Current password')]:
  h=get(c,'employee.php?page=profile&tab='+tab)
  assert 'aria-label="My profile sections"' in h and 'ESS-APPLICANT' in h and 'Fatal error' not in h and 'Warning:' not in h
  if tab!='documents':assert marker in h,(tab,marker)
 h=get(c,'employee.php?page=profile&tab=security')
 h=post(c,'employee.php?page=profile&tab=security',{'csrf':token(h),'action':'change_password','current_password':'incorrect','new_password':'DifferentPassword123!','confirm_password':'DifferentPassword123!'})
 assert 'Current password is incorrect.' in h and 'My profile sections' in h
 h=get(c,'employee.php?page=profile');assert 'ESS-APPLICANT' in h and 'ESS-LEAD' not in h
 try:post(c,'employee.php?page=profile',{'csrf':'bad','action':'ess_profile','version':'1'});raise AssertionError('ESS CSRF bypass')
 except urllib.error.HTTPError as e:assert e.code==403
 print('PASS: employee portal pages, private profile and forbidden routes.')
except Exception:
 log.flush()
 print(open('/tmp/ess-http.log').read())
 raise
finally:
 server.terminate();server.wait();log.close()

