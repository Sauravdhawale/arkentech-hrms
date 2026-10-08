/* Actual authenticated browser checks, against the disposable CI database only. */
const assert=require('node:assert/strict');
const {spawn,execFileSync}=require('node:child_process');
const {mkdirSync}=require('node:fs');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
assert.equal(process.env.DB_NAME,'peopleflow_ci');
execFileSync('php',['-r',`if(getenv('DB_NAME')!=='peopleflow_ci')exit(1);require 'auth.php';require 'includes/core-hr/service.php';$db=db();$r=chr_rows($db,'attendance_settings')[0]??[];att_save_settings($db,['id'=>1,'role'=>'super_admin'],['mode'=>'Manual + Biometric','biometric_enabled'=>1,'version'=>$r['version']??0]);`]);
const base='http://127.0.0.1:8099';
const server=spawn('php',['-S','127.0.0.1:8099','-t','.'],{stdio:'ignore'});
const wait=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{let browser;
 try{
  for(let n=0;n<50;n++){try{if((await fetch(base+'/login.php')).ok)break;}catch{}await wait(100);}
  browser=await chromium.launch({headless:true});mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});
  const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  async function layout(){assert.equal(await page.locator('body').evaluate(e=>e.scrollWidth<=innerWidth+1),true,'horizontal page overflow '+page.url());}
  async function check(){assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('shrms-ui')),page.url()+' '+(await page.locator('body').innerText()).slice(0,300));assert.equal(await page.locator('body').evaluate(e=>getComputedStyle(e).fontFamily.includes('Noto Sans')),true);await layout();assert.equal(await page.locator('body').innerText().then(t=>/Fatal error|Warning:/.test(t)),false);}
  async function login(user){await page.goto(base+'/login.php');await page.locator('[name=email]').fill(user);await page.locator('[name=password]').fill(user==='test.admin'?'Admin-Changed-2026!':'User@123');await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login.php')),page.locator('.login-submit').click()]);}
  await page.goto(base+'/login.php');await check();assert.equal(await page.locator('.login-submit').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(159, 17, 12)');await page.screenshot({path:'/tmp/shrms-ui-screenshots/login.png'});
  await page.goto(base+'/forgot-password.php');await check();
  await login('test.admin');await page.goto(base+'/super-admin.php?page=employees');const profileLink=await page.locator('a[href*="employee-view&id="]').first().getAttribute('href');assert.ok(profileLink);await page.goto(base+'/super-admin.php'+profileLink);await check();
  for(const route of ['overview','employees','employee-add','settings','departments','designations','roles','attendance_dashboard','attendance','manual_attendance','attendance_requests','daily_work_status','monthly','devices','mapping','punch_log','sync','leaves','payroll_dashboard','recruitment_dashboard','documents','announcements','performance','tasks','helpdesk','reports','account']){
   await page.goto(base+'/super-admin.php?page='+route);await check();assert.equal(await page.locator('body>aside').count(),1,route+' shell');
  }
  await page.goto(base+'/super-admin.php?page=overview');
  const nav=page.locator('body>aside');assert.equal(await nav.evaluate(e=>Math.round(e.getBoundingClientRect().width)),250);
  await page.locator('.menu-toggle').click();assert.equal(await nav.evaluate(e=>Math.round(e.getBoundingClientRect().width)),76);await layout();
  assert.ok(await nav.locator('nav a').first().getAttribute('title'));await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-collapsed.png'});
  await page.locator('.menu-toggle').click();await page.locator('.ui-search-trigger').click();await page.locator('#ui-menu-query').fill('employee');assert.ok(await page.locator('.ui-search-results a').count()>0);await page.getByRole('button',{name:'Close search',exact:true}).click();assert.equal(await page.locator('.ui-search-dialog').evaluate(e=>e.open),false);
  await page.locator('#theme-toggle').click();assert.equal(await page.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(23, 25, 28)');await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-dark.png'});await page.locator('#theme-toggle').click();
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin.png'});
  await page.setViewportSize({width:1024,height:900});await layout();
  await page.setViewportSize({width:390,height:844});await layout();assert.equal(await nav.evaluate(e=>e.inert),true);await page.locator('.menu-toggle').click();assert.equal(await nav.evaluate(e=>e.inert),false);await page.keyboard.press('Escape');assert.equal(await nav.evaluate(e=>e.inert),true);await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-mobile.png'});
  await page.context().clearCookies();await page.setViewportSize({width:1440,height:1000});await login('ess.applicant');
  for(const route of ['overview','profile','my_shift','attendance','leaves','inbox','my_salary','tasks','documents','performance','helpdesk']){await page.goto(base+'/employee.php?page='+route);await check();}
  await page.goto(base+'/employee.php?page=attendance&month=2026-01');await check();
  assert.equal(await page.locator('.ess-attendance-scroll tbody tr').count(),31);
  assert.ok(await page.locator('.ess-attendance-scroll').evaluate(e=>e.clientHeight>=320),'Employee attendance table collapsed');
  await page.locator('[data-dialog-open="correction-dialog"]').click();assert.equal(await page.locator('#correction-dialog').evaluate(e=>e.open),true);await page.keyboard.press('Escape');
  for(const width of [1440,390]){await page.setViewportSize({width,height:1000});await layout();await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee-attendance-'+width+'.png',fullPage:true,animations:'disabled'});}
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/employee.php?page=overview');await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee.png'});
  await page.locator('[data-ess-theme]').click();assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('dark')));await page.locator('[data-ess-theme]').click();
  await page.locator('[data-dialog-open="correction-dialog"]').click();assert.equal(await page.locator('#correction-dialog').evaluate(e=>e.open),true);await page.keyboard.press('Escape');
  for(const width of [1024,768,390,360]){await page.setViewportSize({width,height:844});await layout();}
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee-mobile.png'});
  await page.locator('#menu').click();assert.equal(await nav.evaluate(e=>e.inert),false);await page.keyboard.press('Escape');
  assert.deepEqual(errors,[]);console.log('PASS: shared theme, both authenticated shells, navigation search/collapse, themes, modal and desktop/tablet/mobile layouts.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});

