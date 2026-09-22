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
 for page in ['attendance_settings','shift_defaults','time_policies','biometric_integration','attendance_dashboard','attendance_requests','daily_work_status','punch_log','attendance_exceptions','company_calendar','devices','mapping','sync','raw_logs','attendance']:
  _,h=get(a,'super-admin.php?page='+page);assert all(x not in h for x in ['Fatal error','Warning:','Parse error','being configured']),page
 _,h=get(a,'super-admin.php?page=attendance');assert 'Preview import' in h and 'manual-punch' in h
 _,h=get(a,'super-admin.php?page=company_calendar&month=2026-02');assert 'CI Event' in h
 print('PASS: attendance upgrade pages, manual import controls and calendar rendering.')
finally:
 server.terminate();server.wait(timeout=10);log.close()
