// Read-only browser verification against existing disposable regression fixtures.
const assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const {mkdirSync}=require('node:fs');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
assert.equal(process.env.DB_NAME,'peopleflow_ci');
const server=spawn('php',['-S','127.0.0.1:8100','-t','.'],{stdio:'ignore'});
const base='http://127.0.0.1:8100';
(async()=>{let browser;try{
 for(let n=0;n<50;n++){try{if((await fetch(base+'/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/login.php');await page.locator('[name=email]').fill('test.admin');await page.locator('[name=password]').fill('Admin-Changed-2026!');await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login.php')),page.locator('.login-submit').click()]);
 mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});
 for(const route of ['attendance_dashboard&date=2026-02-02','daily_work_status&month=2026-02','monthly&month=2026-02','attendance']){
  await page.goto(base+'/super-admin.php?page='+route);assert.equal(/Fatal error|Warning:|Parse error/.test(await page.locator('body').innerText()),false,route);
  if(route.startsWith('attendance_dashboard')){assert.equal(await page.getByRole('heading',{name:'Check-ins by hour'}).count(),0);assert.equal(await page.locator('.att-kpi').count(),4);}
  if(route==='attendance')assert.equal(await page.locator('.attendance-tools').count(),0);
  for(const width of [1440,390]){await page.setViewportSize({width,height:1000});assert.ok(await page.locator('body').evaluate(e=>e.scrollWidth<=innerWidth+1),'page overflow '+route+' '+width);await page.screenshot({path:'/tmp/shrms-ui-screenshots/attendance-'+route.split('&')[0]+'-'+width+'.png',fullPage:true});}
 }
 await page.setViewportSize({width:1440,height:1000});await page.goto(base+'/super-admin.php?page=daily_work_status&month=2026-02');
 await page.locator('[data-open-dialog="sheet-entry"]').click();assert.ok(await page.locator('#sheet-entry').evaluate(e=>e.open));await page.keyboard.press('Escape');
 await page.locator('[data-open-dialog="attendance-import"]').click();assert.ok(await page.locator('#attendance-import').evaluate(e=>e.open));await page.keyboard.press('Escape');
 const cell=page.locator('.att-sheet-cell').first();assert.ok(await cell.count());await cell.click();assert.ok(await page.locator('#sheet-entry').evaluate(e=>e.open));assert.ok((await page.locator('#sheet-entry [name=attendance_date]').inputValue()).startsWith('2026-02'));await page.keyboard.press('Escape');
 await page.locator('#theme-toggle').click();await page.screenshot({path:'/tmp/shrms-ui-screenshots/attendance-sheet-dark.png',fullPage:true});
 assert.deepEqual(errors,[]);console.log('PASS: attendance pages, responsive layouts, sheet dialogs, historical cell editing and dark theme.');
 }finally{if(browser)await browser.close();server.kill();}})().catch(e=>{console.error(e);process.exitCode=1;});
