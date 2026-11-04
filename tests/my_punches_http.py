"""Own-only, read-only punch history against disposable CI data."""
import os,re,json,subprocess,time,urllib.request,urllib.parse,urllib.error,http.cookiejar
assert os.environ.get('DB_NAME')=='peopleflow_ci'
base='http://127.0.0.1:8096/'
log=open('/tmp/my-punches-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8096','-t','.'],stdout=log,stderr=log)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(c,path):
 with c.open(base+path) as r:return r.read().decode()
def login(name):
 c=client();h=get(c,'login.php');token=re.search(r'name="csrf" value="([^"]+)"',h).group(1)
 c.open(base+'login.php',urllib.parse.urlencode({'csrf':token,'email':'ess.'+name,'password':'User@123'}).encode()).read()
 return c
try:
 time.sleep(1)
 assert 'counts' not in get(client(),'my-punches.php?date=2026-01-02')
 a=login('applicant');b=login('lead')
 own=json.loads(get(a,'my-punches.php?date=2026-01-02'))
 other=json.loads(get(b,'my-punches.php?date=2026-01-02'))
 assert own['counts']=={'total':2,'in':1,'out':1,'unknown':0},own
 assert other['counts']['total']==3,other
 assert own['punches'][0]['time_label']=='01:02:03 AM' and own['punches'][0]['date_label']=='03 Jan 2026'
 assert own['punches'][1]['source']=='Admin manual'
 assert 'raw_payload' not in str(own) and 'actor_id' not in str(own)
 for query in ['employee_id=1','user_id=1','owner=1']:
  result=json.loads(get(a,'my-punches.php?date=2026-01-02&'+query))
  assert result['punches']==own['punches'],'URL owner override leaked data'
 for date in ['2026-02-30','tomorrow','2099-01-01','2026-1-2']:
  try:get(a,'my-punches.php?date='+date);raise AssertionError('Bad date accepted')
  except urllib.error.HTTPError as e:assert e.code==400
 empty=json.loads(get(a,'my-punches.php?date=2026-01-01'));assert empty['counts']['total']==0
 current=json.loads(get(a,'my-punches.php'));assert 'punches' in current,current
 try:a.open(base+'my-punches.php',b'');raise AssertionError('POST accepted')
 except urllib.error.HTTPError as e:assert e.code==405
 assert json.loads(get(a,'my-punches.php?date=2026-01-02'))['punches']==own['punches'],'Read endpoint changed records'
 print('PASS: authenticated own-only history, tamper protection, dates, overnight punches and read-only endpoint.')
except Exception:
 log.flush();print(open('/tmp/my-punches-http.log').read());raise
finally:
 server.terminate();server.wait();log.close()
