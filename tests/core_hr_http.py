"""Core HR HTTP regressions. Disposable test DB only; requires Phase 1 test fixtures."""
import os,re,subprocess,time,urllib.request,urllib.parse,urllib.error,http.cookiejar,json
assert os.environ.get('DB_NAME')=='peopleflow_ci'
base='http://127.0.0.1:8089/'
log=open('/tmp/peopleflow-core-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8089','-t','.'],stdout=log,stderr=log)
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
 for page in ['overview','employees','settings','departments','designations','roles','shifts','roster','holidays','attendance','attendance_history','monthly','leaves','leave_history','leave_policy','balances']:
  _,h=get(a,'super-admin.php?page='+page);assert all(x not in h for x in ['Fatal error','Warning:','Parse error']),page
 _,h=get(a,'super-admin.php?page=shifts');assert 'WORK CONFIGURATION' in h and 'Company Settings' in h
 _,h=get(a,'super-admin.php?page=leaves');assert 'CI request' in h
 f=json.load(open('/tmp/core-hr-fixture.json'))
 _,h=get(a,'super-admin.php?page=monthly&month=2026-01&employee_id='+str(f['employee']))
 assert '8h 10m' in h,h[-2000:]
 with a.open(base+'super-admin.php?page=monthly&month=2026-01&employee_id='+str(f['employee'])+'&export=1') as r:
  assert r.headers['Content-Type'].startswith('text/csv');assert 'working_days' in r.read().decode()
 _,h=get(a,'super-admin.php?page=holidays');_,h=post(a,'super-admin.php?page=holidays',{'csrf':token(h),'action':'core_config','module':'holidays','title':'HTTP Future Holiday','date':'2030-01-02','holiday_type':'Public Holiday','active':1});assert 'Configuration saved.' in h
 _,h=get(a,'super-admin.php?page=holidays&year=2030');assert 'HTTP Future Holiday' in h
 _,h=get(a,'super-admin.php?page=holidays&year=2026');assert 'HTTP Future Holiday' not in h
 try:post(a,'super-admin.php?page=holidays',{'csrf':'bad','action':'core_delete','module':'holidays','id':f['holiday']});raise AssertionError('CSRF bypass')
 except urllib.error.HTTPError as e:assert e.code==403
 viewer=login('test.two','User@123')
 for page in ['shifts','roster','holidays','attendance','monthly','leaves','balances']:
  try:get(viewer,'super-admin.php?page='+page);raise AssertionError('Unauthorized '+page)
  except urllib.error.HTTPError as e:assert e.code==403
 # Permitted dashboard must not become a way to mutate Core HR settings.
 _,h=get(viewer,'super-admin.php?page=overview');_,h=post(viewer,'super-admin.php?page=overview',{'csrf':token(h),'action':'core_config','module':'holidays','title':'Forbidden','date':'2030-02-01','holiday_type':'Company Holiday','active':1});assert 'do not have permission' in h
 print('PASS: Core HR routes, settings placement, report export, year filtering, CSRF and role guards.')
finally:
 server.terminate();server.wait(timeout=10);log.close()
