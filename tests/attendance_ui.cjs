// Read-only browser verification against existing disposable regression fixtures.
const assert=require('node:assert/strict');
const {spawn,execFileSync}=require('node:child_process');
const {mkdirSync,readFileSync}=require('node:fs');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
assert.equal(process.env.DB_NAME,'peopleflow_ci');
const server=spawn('php',['-S','127.0.0.1:8100','-t','.'],{stdio:'ignore'});
const base='http://127.0.0.1:8100';
(async()=>{let browser,mappingFixture=null;try{
 for(let n=0;n<50;n++){try{if((await fetch(base+'/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/login.php');await page.locator('[name=email]').fill('test.admin');await page.locator('[name=password]').fill('Admin-Changed-2026!');await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login.php')),page.locator('.login-submit').click()]);
 mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});
 for(const route of ['attendance_dashboard&date=2026-02-02','daily_work_status&month=2026-02','monthly&month=2026-02','attendance']){
  await page.goto(base+'/super-admin.php?page='+route);assert.equal(/Fatal error|Warning:|Parse error/.test(await page.locator('body').innerText()),false,route);
  if(route.startsWith('attendance_dashboard')){assert.equal(await page.getByRole('heading',{name:'Check-ins by hour'}).count(),0);assert.equal(await page.locator('.att-kpi').count(),4);}
  if(route==='attendance'){assert.equal(await page.locator('.attendance-tools').count(),0);await page.locator('[data-live-rows] tr').first().waitFor();assert.equal(await page.locator('[data-live-counts] .card').count(),4);const response=page.waitForResponse(r=>r.url().includes('live_status=1')&&r.status()===200);await page.getByRole('button',{name:'Refresh now'}).click();await response;assert.match(await page.locator('[data-live-updated]').innerText(),/Updated/);}
  for(const width of [1440,390]){await page.setViewportSize({width,height:1000});assert.ok(await page.locator('body').evaluate(e=>e.scrollWidth<=innerWidth+1),'page overflow '+route+' '+width);await page.screenshot({path:'/tmp/shrms-ui-screenshots/attendance-'+route.split('&')[0]+'-'+width+'.png',fullPage:true,animations:'disabled'});}
 }
 await page.setViewportSize({width:1440,height:1000});await page.goto(base+'/super-admin.php?page=daily_work_status&month=2026-02');
 await page.locator('[data-open-dialog="sheet-entry"]').click();assert.ok(await page.locator('#sheet-entry').evaluate(e=>e.open));await page.keyboard.press('Escape');
 await page.locator('[data-open-dialog="attendance-import"]').click();assert.ok(await page.locator('#attendance-import').evaluate(e=>e.open));await page.keyboard.press('Escape');
 const cell=page.locator('.att-sheet-cell').first();assert.ok(await cell.count());await cell.click();assert.ok(await page.locator('#sheet-status').evaluate(e=>e.open));assert.ok((await page.locator('#sheet-status [name=attendance_date]').inputValue()).startsWith('2026-02'));assert.ok(await page.locator('#sheet-status [name=sheet_status] option[value=Absent]').count());await page.screenshot({path:'/tmp/shrms-ui-screenshots/attendance-status-popup.png',animations:'disabled'});await page.keyboard.press('Escape');
 await page.locator('#theme-toggle').click();await page.screenshot({path:'/tmp/shrms-ui-screenshots/attendance-sheet-dark.png',fullPage:true,animations:'disabled'});
 // Monthly employee records preserve one employee and show every calendar date.
 const employee=JSON.parse(readFileSync('/tmp/core-hr-fixture.json','utf8')).employee;
 await page.goto(base+`/super-admin.php?page=attendance&employee_id=${employee}&date=2026-02-06`);
 assert.equal(await page.locator('[data-attendance-date]').count(),28,'Legacy daily record opens full month');
 assert.equal(await page.locator('[name=month]').inputValue(),'2026-02');
 assert.ok(await page.locator('[data-attendance-date="2026-02-01"]').count());
 assert.ok(await page.locator('[data-attendance-date="2026-02-28"]').count());
 const csvLink=page.getByRole('link',{name:'Download CSV'});
 const csv=await page.request.get(new URL(await csvLink.getAttribute('href'),page.url()).href);
 assert.ok(csv.ok());assert.match(csv.headers()['content-type'],/text\/csv/);
 const csvBody=await csv.text();assert.match(csvBody,/2026-02-01/);assert.match(csvBody,/2026-02-28/);
 for(const width of [1440,390]){await page.setViewportSize({width,height:1000});assert.ok(await page.locator('body').evaluate(e=>e.scrollWidth<=innerWidth+1),'Monthly record overflow '+width);await page.screenshot({path:`/tmp/shrms-ui-screenshots/employee-month-${width}.png`,fullPage:true});}
 await page.setViewportSize({width:1440,height:1000});
 await page.getByRole('link',{name:'Next month',exact:true}).click();assert.equal(await page.locator('[data-attendance-date]').count(),31);assert.match(page.url(),new RegExp('employee_id='+employee));
 await page.locator('[name=month]').fill('2024-02');await page.getByRole('button',{name:'View month'}).click();assert.equal(await page.locator('[data-attendance-date]').count(),29,'Leap month');
 await page.goto(base+`/super-admin.php?page=attendance&employee_id=${employee}&month=2026-13`);assert.match(await page.locator('body').innerText(),/Choose a valid month/);
 await page.goto(base+`/super-admin.php?page=attendance&employee_id=${employee}&month=2026-02`);
 await page.getByRole('link',{name:'Correct record',exact:true}).first().click();assert.ok(await page.locator('[name=action][value=core_attendance]').count());
 await page.getByRole('link',{name:'Back to monthly record',exact:true}).click();assert.equal(await page.locator('[data-attendance-date]').count(),28);
 await page.goto(base+`/super-admin.php?page=attendance&employee_id=${employee}`);assert.ok(await page.locator('[data-attendance-live]').count(),'Employee filter alone stays live');
 const recordLink=page.getByRole('link',{name:'View monthly record',exact:true}).first();await recordLink.click();assert.ok(await page.locator('[data-attendance-date]').count()>=28);
 // ESS regressions leave biometric mode off. Preserve that fixture after this UI check.
 await page.goto(base+'/super-admin.php?page=employee-add');assert.equal(await page.locator('[data-auto-biometric]').count(),0,'Mapping hidden while biometric mode is off');
 mappingFixture=JSON.parse(execFileSync('php',['-r',`require 'auth.php'; $db=db(); $r=$db->query("SELECT id,data FROM hr_records WHERE module='attendance_settings' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC); if(!$r)exit(1); echo json_encode($r); $v=json_decode($r['data'],true); $v['mode']='Manual + Biometric'; $v['biometric_enabled']=true; $db->prepare('UPDATE hr_records SET data=? WHERE id=?')->execute([json_encode($v),$r['id']]);`],{encoding:'utf8'}));
 await page.goto(base+'/super-admin.php?page=employee-add');
 const autoMap=page.locator('[data-auto-biometric]');assert.equal(await autoMap.count(),1);assert.ok(await autoMap.isChecked());
 await page.locator('[name=employee_code]').fill('AT00341');assert.equal(await page.locator('[data-auto-biometric-preview]').inputValue(),'341');
 await page.locator('[name=employee_code]').fill('AT00177');assert.equal(await page.locator('[data-auto-biometric-preview]').inputValue(),'177');
 await autoMap.uncheck();assert.ok(await page.locator('[data-auto-biometric-device]').isDisabled());
 await autoMap.check();assert.ok(await page.locator('[data-auto-biometric-device]').isEnabled());
 for(const width of [1440,390]){await page.setViewportSize({width,height:1000});assert.ok(await page.locator('body').evaluate(e=>e.scrollWidth<=innerWidth+1),'Employee form overflow '+width);}
 assert.deepEqual(errors,[]);console.log('PASS: attendance pages, responsive layouts, sheet dialogs, historical cell editing and dark theme.');
 }finally{if(mappingFixture)execFileSync('php',['-r',"require 'auth.php'; $r=json_decode($argv[1],true); db()->prepare('UPDATE hr_records SET data=? WHERE id=?')->execute([$r['data'],$r['id']]);",JSON.stringify(mappingFixture)]);if(browser)await browser.close();server.kill();}})().catch(e=>{console.error(e);process.exitCode=1;});



