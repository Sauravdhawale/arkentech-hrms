"""Organization settings and employee form checks on disposable CI data."""
import os,re,subprocess,time,urllib.request,urllib.parse,urllib.error,http.cookiejar,json
from html.parser import HTMLParser
assert os.environ.get('DB_NAME')=='peopleflow_ci'
base='http://127.0.0.1:8094/'
log=open('/tmp/organization-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8094','-t','.'],stdout=log,stderr=log)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(c,path):
 with c.open(base+path) as r:return r.geturl(),r.read().decode()
def post(c,path,v):
 with c.open(base+path,urllib.parse.urlencode(v).encode()) as r:return r.geturl(),r.read().decode()
def token(h):return re.search(r'name="csrf" value="([^"]+)"',h).group(1)
def login(name,password):
 c=client();_,h=get(c,'login.php');url,h=post(c,'login.php',{'csrf':token(h),'email':name,'password':password});assert 'login.php' not in url;return c
class Options(HTMLParser):
 def __init__(self):super().__init__();self.target=False;self.options=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='select':self.target=a.get('name')=='designation_id'
  if tag=='option' and self.target:self.options.append(a)
 def handle_endtag(self,tag):
  if tag=='select':self.target=False
try:
 for _ in range(50):
  try:get(client(),'login.php');break
  except OSError:time.sleep(.1)
 a=login('test.admin','Admin-Changed-2026!')
 f=json.load(open('/tmp/organization-fixture.json'))
 for page in ['departments','designations','employee-add','employee-edit&id='+str(f['employee'])]:
  _,h=get(a,'super-admin.php?page='+page);assert all(x not in h for x in ['Fatal error','Warning:','Parse error']),page
 for page in ['departments','designations']:
  _,h=get(a,'super-admin.php?page='+page)
  assert 'ORGANIZATION' in h
  assert 'Arkentech departments & designations' not in h and 'Preview organization update' not in h and 'Apply safe changes' not in h
 _,h=get(a,'super-admin.php?page=employee-add');p=Options();p.feed(h);assert all('disabled' in o for o in p.options if o.get('value'))
 _,h=get(a,'super-admin.php?page=employee-edit&id='+str(f['employee']));p=Options();p.feed(h)
 selected=[o for o in p.options if 'selected' in o];assert len(selected)==1 and selected[0]['value']==str(f['designation']) and 'disabled' not in selected[0]
 assert all('disabled' in o for o in p.options if o.get('value') and o.get('data-department')!=str(f['department']))
 _,h=get(a,'super-admin.php?page=departments');_,h=post(a,'super-admin.php?page=departments',{'csrf':token(h),'action':'save_department','name':'Digital Marketing','active':'1'})
 assert 'equivalent departments record already exists' in h
 viewer=login('test.two','User@123')
 _,h=get(viewer,'super-admin.php?page=overview');_,h=post(viewer,'super-admin.php?page=overview',{'csrf':token(h),'action':'organization_preview'})
 assert 'Organization update requires' in h and 'Proposed update' not in h
 try:post(a,'super-admin.php?page=departments',{'csrf':'bad','action':'organization_apply','fingerprint':'unused'});raise AssertionError('CSRF bypass')
 except urllib.error.HTTPError as e:assert e.code==403
 print('PASS: Organization setup panel removed, permission/CSRF checks, duplicate guard, and employee add/edit HTML filtering.')
finally:
 server.terminate();server.wait(timeout=10);log.close()
