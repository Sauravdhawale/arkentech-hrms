"""Phase 1 integration through real authenticated HTTP, disposable DB only."""
import os,re,subprocess,time,urllib.request,urllib.parse,urllib.error,http.cookiejar,json
assert os.environ.get('DB_NAME')=='peopleflow_ci'
base='http://127.0.0.1:8088/'
log=open('/tmp/peopleflow-foundation-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8088','-t','.'],stdout=log,stderr=log)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(c,path):
 with c.open(base+path) as r:return r.geturl(),r.read().decode()
def post(c,path,v):
 with c.open(base+path,urllib.parse.urlencode(v,doseq=True).encode()) as r:return r.geturl(),r.read().decode()
def upload(c,path,v,field,filename,content,mime):
 boundary='hrms-ci-multipart-boundary'
 body=b''
 for k,value in v.items():body+=f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{value}\r\n'.encode()
 body+=f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+f'\r\n--{boundary}--\r\n'.encode()
 req=urllib.request.Request(base+path,body,{'Content-Type':'multipart/form-data; boundary='+boundary})
 with c.open(req) as r:return r.geturl(),r.read().decode()
def token(h):return re.search(r'name="csrf" value="([^"]+)"',h).group(1)
def login(name,password='User@123'):
 c=client();_,h=get(c,'login.php');url,h=post(c,'login.php',{'csrf':token(h),'email':name,'password':password});assert 'login.php' not in url,(name,h[-1200:]);return c,url,h
def denied(fn,code=403):
 try:fn();raise AssertionError('Access was allowed')
 except urllib.error.HTTPError as e:assert e.code==code,e.code
try:
 for _ in range(50):
  try:get(client(),'login.php');break
  except OSError:time.sleep(.1)
 a,_,_=login('test.admin')
 for page in ['overview','employees','employee-add','employee-edit&id=2','employee-view&id=2','employee-view&id=2&tab=employment','employee-view&id=2&tab=documents','settings','departments','designations','roles','account']:
  _,h=get(a,'super-admin.php?page='+page);assert 'Fatal error' not in h and 'Warning:' not in h,page
 _,h=get(a,'super-admin.php?page=departments');_,h=post(a,'super-admin.php?page=departments',{'csrf':token(h),'action':'save_department','name':'HTTP Department','code':'HTTP','active':1});assert 'Saved successfully.' in h
 dep=re.search(r'HTTP Department</strong>.*?edit=(\d+)',h,re.S).group(1)
 _,h=get(a,'super-admin.php?page=designations');_,h=post(a,'super-admin.php?page=designations',{'csrf':token(h),'action':'save_designation','name':'HTTP Designer','department_id':dep,'active':1});assert 'Saved successfully.' in h
 des=re.search(r'HTTP Designer</strong>.*?edit=(\d+)',h,re.S).group(1)
 _,h=get(a,'super-admin.php?page=roles')
 perm=re.search(r'name="permissions\[\]" value="(\d+)"[^>]*>View dashboard',h).group(1)
 _,h=post(a,'super-admin.php?page=roles',{'csrf':token(h),'action':'save_role','name':'HTTP Custom','slug':'http-custom','active':1,'permissions[]':[perm]});assert 'Saved successfully.' in h
 custom=re.search(r'HTTP Custom</strong>.*?edit=(\d+)',h,re.S).group(1)
 _,h=post(a,'super-admin.php?page=roles',{'csrf':token(h),'action':'save_role','id':custom,'name':'HTTP Custom Updated','slug':'http-custom','active':1,'permissions[]':[perm]});assert 'HTTP Custom Updated' in h
 _,h=post(a,'super-admin.php?page=roles',{'csrf':token(h),'action':'delete_role','id':custom});assert 'Deleted.' in h
 _,h=get(a,'super-admin.php?page=employee-add');role=re.search(r'<option value="(\d+)" selected>Employee</option>',h).group(1)
 data={'csrf':token(h),'action':'save_employee','first_name':'HTTP','last_name':'Person','username':'http.person','email':'http.person@example.test','department_id':dep,'designation_id':des,'role_id':role,'joining_date':'2026-01-02','employment_type':'Full Time','employment_status':'Active'}
 url,h=post(a,'super-admin.php?page=employee-add',data);assert 'Employee saved.' in h,h[-3000:];uid=re.search(r'id=(\d+)',url).group(1)
 _,h=get(a,'super-admin.php?page=departments');_,h=post(a,'super-admin.php?page=departments',{'csrf':token(h),'action':'delete_department','id':dep});assert 'assigned to an employee or another record' in h
 e,url,h=login('http.person');assert 'page=security' in url
 url,h=post(e,'employee.php?page=security',{'csrf':token(h),'action':'change_password','current_password':'User@123','new_password':'HTTP-Password-2026!','confirm_password':'HTTP-Password-2026!'});assert 'Saved successfully.' in h,h[-1500:]
 _,h=get(e,'employee.php?page=security');assert 'login.php' not in get(e,'index.php')[0]
 denied(lambda:get(e,'super-admin.php?page=employees'))
 viewer,_,h=login('test.two');_,h=get(viewer,'super-admin.php?page=employees');assert 'All employees' in h
 denied(lambda:post(viewer,'super-admin.php?page=employees',{'csrf':token(h),'action':'delete_employee','id':uid}))
 denied(lambda:post(a,'super-admin.php?page=employees',{'csrf':'bad','action':'delete_employee','id':uid}))
 denied(lambda:get(viewer,'super-admin.php?page=employee-view&id='+uid+'&tab=documents'))
 docpath='super-admin.php?page=employee-view&id='+uid+'&tab=documents'
 _,h=get(a,docpath)
 _,h=upload(a,docpath,{'csrf':token(h),'action':'save_document','employee_id':uid,'title':'CI document','type':'Resume'},'document','ci.pdf',b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF','application/pdf')
 assert 'Document saved.' in h,h[-1800:]
 file_id=re.search(r'document.php\?id=(\d+)',h).group(1)
 with a.open(base+'document.php?id='+file_id) as r:assert r.read().startswith(b'%PDF')
 with e.open(base+'document.php?id='+file_id) as r:assert r.read().startswith(b'%PDF')
 denied(lambda:get(viewer,'document.php?id='+file_id),404)
 stranger,_,_=login('test.one');denied(lambda:get(stranger,'document.php?id='+file_id),404)
 _,h=get(a,docpath)
 _,h=upload(a,docpath,{'csrf':token(h),'action':'save_document','employee_id':uid,'title':'Bad upload','type':'Resume'},'document','evil.php',b'<?php echo "bad";', 'application/x-php')
 assert 'Only PDF, JPEG or PNG' in h
 _,h=get(a,docpath)
 document_id=re.search(r'name="document_id" value="(\d+)"',h).group(1)
 _,h=post(a,docpath,{'csrf':token(h),'action':'delete_document','document_id':document_id});assert 'Document and its uploaded versions deleted.' in h
 denied(lambda:get(a,'document.php?id='+file_id),404)
 _,h=get(a,'super-admin.php?page=settings')
 import base64
 png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=')
 _,h=upload(a,'super-admin.php?page=settings',{'csrf':token(h),'action':'save_company','name':'CI Company','timezone':'Asia/Kolkata'},'logo','logo.png',png,'image/png')
 assert 'Company settings updated.' in h
 with a.open(base+'media.php') as r:assert r.headers['Content-Type']=='image/png' and r.read()==png

 _,h=get(a,'super-admin.php?page=employee-view&id='+uid+'&tab=employment');_,h=post(a,'super-admin.php?page=employee-view&id='+uid+'&tab=employment',{'csrf':token(h),'action':'toggle_employee','id':uid});assert 'deactivated' in h
 assert 'login.php' in get(e,'index.php')[0]
 _,h=post(a,'super-admin.php?page=employee-view&id='+uid+'&tab=employment',{'csrf':token(h),'action':'toggle_employee','id':uid});assert 'activated' in h
 e,_,_=login('http.person','HTTP-Password-2026!')
 anon=client();_,h=get(anon,'forgot-password.php');_,h=post(anon,'forgot-password.php',{'csrf':token(h),'identity':'http.person'});assert 'If an active account' in h
 mail=json.load(open('/tmp/peopleflow-reset-test.json'));assert mail['email']=='http.person@example.test'
 path='reset-password.php?token='+mail['token'];_,h=get(anon,path);_,h=post(anon,path,{'csrf':token(h),'token':mail['token'],'password':'Reset-HTTP-Password-2026!','confirm_password':'Reset-HTTP-Password-2026!'});assert 'Password updated' in h
 assert 'login.php' in get(e,'index.php')[0]
 login('http.person@example.test','Reset-HTTP-Password-2026!')
 _,h=get(a,'super-admin.php?page=employee-view&id='+uid+'&tab=employment');_,h=post(a,'super-admin.php?page=employee-view&id='+uid+'&tab=employment',{'csrf':token(h),'action':'delete_employee','id':uid});assert 'archived' in h
 _,h=get(a,'super-admin.php?page=employees&search=http.person');assert 'No employees found' in h
 _,h=get(a,'super-admin.php?page=account')
 _,h=post(a,'super-admin.php?page=account',{'csrf':token(h),'action':'change_password','current_password':'User@123','new_password':'Admin-Changed-2026!','confirm_password':'Admin-Changed-2026!'});assert 'Password changed.' in h
 _,h=get(a,'super-admin.php?page=overview');assert 'Good ' in h
 url,h=post(a,'logout.php',{'csrf':token(h)});assert 'login.php' in url
 assert 'login.php' in get(a,'super-admin.php')[0]
 login('test.admin','Admin-Changed-2026!')
 print('PASS: Phase 1 screens, employee creation, assigned-department delete protection, forced password change, role/CSRF denial, disable/reactivate, email recovery, session revocation, archive.')
finally:
 server.terminate();server.wait(timeout=10);log.close()
 if __import__('sys').exc_info()[0]:print(open('/tmp/peopleflow-foundation-http.log').read())
