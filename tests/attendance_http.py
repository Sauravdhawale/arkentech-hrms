"""Core HR HTTP regressions. Disposable test DB only; requires Phase 1 test fixtures."""
import os,re,subprocess,time,urllib.request,urllib.parse,urllib.error,http.cookiejar,json
assert os.environ.get('DB_NAME')=='peopleflow_ci'
base='http://127.0.0.1:8089/'
log=open('/tmp/peopleflow-core-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8089','-t','.'],stdout=log,stderr=log)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(c,path):
 try:
  with c.open(base+path) as r:return r.geturl(),r.read().decode()
 except urllib.error.HTTPError as e:
  print('HTTP error',path,e.code)
  raise
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
 for page in ['attendance_settings','shift_defaults','time_policies','biometric_integration','attendance_dashboard','attendance_requests','daily_work_status','punch_log','attendance_exceptions','company_calendar','devices','mapping','sync','raw_logs','attendance','manual_attendance']:
  _,h=get(a,'super-admin.php?page='+page);assert all(x not in h for x in ['Fatal error','Warning:','Parse error','being configured']),page
 _,h=get(a,'super-admin.php?page=manual_attendance');assert 'Preview import' in h and 'manual-punch' in h and 'Add Attendance' in h and '.xlsx' in h
 _,h=get(a,'super-admin.php?page=company_calendar&month=2026-02');assert 'CI Event' in h
 # Read-only event view, rather than aggregated daily attendance.
 _,h=get(a,'super-admin.php?page=punch_log&from=2026-02-01&to=2026-02-28&punch_type=out')
 assert 'CI-1' in h and '06:00:00' in h and 'Biometric User ID' in h
 _,h=get(a,'super-admin.php?page=punch_log&from=2026-02-01&to=2026-02-28&processing_status=Unmapped')
 assert 'CI-WAIT-' in h and 'No unique active employee mapping' in h
 _,h=get(a,'super-admin.php?page=punch_log&from=2026-02-01&to=2026-02-28&device_code=NO-SUCH-DEVICE')
 assert 'No device punches in this date range' in h
 _,h=get(a,'super-admin.php?page=attendance');assert 'id="manual-punch"' not in h
 # Turn the existing setting OFF, verify menu and route gates, then restore it.
 def biometric(on):
  _,form=get(a,'super-admin.php?page=attendance_settings')
  version=re.search(r'name="version" value="([^"]+)"',form).group(1)
  values={'csrf':token(form),'action':'att_settings','version':version,'mode':'Manual + Biometric' if on else 'Manual Attendance'}
  if on:values['biometric_enabled']='1'
  _,result=post(a,'super-admin.php?page=attendance_settings',values)
  assert 'Saved successfully' in result
 biometric(False)
 try:
  for page in ['attendance_dashboard','attendance','manual_attendance','attendance_requests','daily_work_status','attendance_exceptions','monthly']:
   _,h=get(a,'super-admin.php?page='+page)
   sidebar=h.split('<aside',1)[1].split('</aside>',1)[0]
   assert 'Biometric Device' not in sidebar and '?page=punch_log' not in sidebar
   assert '?page=manual_attendance' in sidebar and '?page=attendance' in sidebar
   assert 'Fatal error' not in h
  for page in ['devices','mapping','punch_log','sync','raw_logs']:
   try:get(a,'super-admin.php?page='+page);raise AssertionError('Biometric route allowed while disabled: '+page)
   except urllib.error.HTTPError as e:assert e.code==403
 finally:biometric(True)
 _,h=get(a,'super-admin.php?page=punch_log')
 sidebar=h.split('<aside',1)[1].split('</aside>',1)[0]
 assert 'biometric-navigation' not in sidebar and 'Employee Mapping' not in sidebar and 'Sync Logs' not in sidebar
 assert 'Employee Mapping' in h and 'Check-In / Check-Out Log' in h and 'Sync Logs' in h
 assert sidebar.index('Monthly Summary')<sidebar.index('Biometric Device')
 assert 'Check-In / Check-Out Log' not in sidebar
 print('PASS: attendance upgrade pages, manual import controls and calendar rendering.')
finally:
 server.terminate();server.wait(timeout=10);log.close()
