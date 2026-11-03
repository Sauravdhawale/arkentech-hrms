"""Exercise real employee routes, CSV exports and legacy admin bypasses."""
import os, re, json, subprocess, time, urllib.request, urllib.parse, urllib.error, http.cookiejar
assert os.environ.get('DB_NAME') == 'peopleflow_ci'
fixtures=json.load(open('/tmp/team-access-fixtures.json'))
base='http://127.0.0.1:8098/'
log=open('/tmp/team-access-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8098','-t','.'],stdout=log,stderr=log)
def request(client,path,data=None):
    with client.open(base+path, None if data is None else urllib.parse.urlencode(data).encode()) as r:
        text=r.read().decode()
        assert 'Fatal error' not in text and 'Warning:' not in text, text[-2000:]
        return text
def denied(client,path,code=403,data=None):
    try: request(client,path,data); raise AssertionError('Route allowed: '+path)
    except urllib.error.HTTPError as e: assert e.code==code,(path,e.code)
def login(name):
    c=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    h=request(c,'login.php');token=re.search(r'name="csrf" value="([^"]+)"',h).group(1)
    request(c,'login.php',{'csrf':token,'email':'scope.'+name,'password':'User@123'})
    return c
try:
    time.sleep(1)
    c=login('lead');own=fixtures['member']['id'];other=fixtures['outsider']['id']
    h=request(c,'employee.php?page=team_people')
    assert 'Scope Member' in h and 'Scope Outsider' not in h and 'Scope Unassigned' not in h and 'Scope Removed' not in h
    assert 'My team' in h and 'Team People' in h and 'Team Attendance' in h
    h=request(c,'employee.php?page=team_people&search=Outsider');assert 'No matching team members' in h
    h=request(c,'employee.php?page=team_attendance');assert 'Scope Member' in h and 'Scope Outsider' not in h and 'Refresh attendance' in h
    path=f'employee.php?page=team_attendance&employee_id={own}&month=2031-02'
    h=request(c,path);assert 'February 2031' in h and '28 calendar days' in h and 'Download CSV' in h
    csv=request(c,path+'&export=1');assert 'Scope Member' in csv and 'Scope Outsider' not in csv and len(csv.splitlines())==29
    for extra in [f'&employee_id={other}', '&employee_id=0','&employee_id=-1','&employee_id=abc']:
        for suffix in ['', '&export=1']:
            denied(c,'employee.php?page=team_attendance'+extra+suffix)
    denied(c,'employee.php?page=team_people&employee_id='+str(other))
    denied(c,'employee.php?page=team_attendance&export=1')
    for page in ['team_people','team_attendance']:
        denied(c,'employee.php?page='+page,405,{'action':'ess_profile','employee_id':own,'mobile':'FORGED'})
    for page in ['employees','employee-view','employee-edit','attendance','monthly','manual_attendance','payroll_payslips','roles','account']:
        denied(c,f'super-admin.php?page={page}&id={other}&employee_id={other}&export=1')
    denied(c,f'super-admin.php?page=attendance_requests&timeline_live=1&employee_id={other}')
    denied(c,f'media.php?employee={other}',404)
    h=request(c,f'employee.php?page=attendance&employee_id={other}&month=2031-02');assert 'Scope Outsider' not in h
    normal=login('member')
    for page in ['team_people','team_attendance']:denied(normal,'employee.php?page='+page)
    h=request(login('empty'),'employee.php?page=team_people');assert 'No team members assigned yet' in h
    print('PASS: Team Leader navigation, monthly CSV, direct URL isolation, read-only routes, legacy admin denial, own attendance and empty teams.')
except Exception:
    log.flush();print(open('/tmp/team-access-http.log').read());raise
finally:
    server.terminate();server.wait();log.close()
