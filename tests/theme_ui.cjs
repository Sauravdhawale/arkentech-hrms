/* Actual authenticated browser checks, against the disposable CI database only. */
const assert=require('node:assert/strict');
const {spawn,execFileSync}=require('node:child_process');
const {mkdirSync,readFileSync}=require('node:fs');
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
  for(const route of ['overview','employees','employee-add','settings','departments','designations','roles','attendance_dashboard','attendance','manual_attendance','attendance_requests','daily_work_status','monthly','devices','mapping','punch_log','sync','leaves','recruitment_dashboard','documents','announcements','performance','tasks','helpdesk','reports','account']){
   await page.goto(base+'/super-admin.php?page='+route);await check();assert.equal(await page.locator('body>aside').count(),1,route+' shell');
  }
  assert.equal(await page.locator('.pay-nav').count(),1);
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});
   for(const route of ['payroll_dashboard','payroll_payslips','payslip_settings','salary_components']){
    await page.goto(base+'/super-admin.php?page='+route+'&month=2025-02');await check();assert.equal(await page.locator('dialog[open]').count(),0,'Payroll pages must not auto-open a popup');
    assert.deepEqual((await page.locator('.pay-nav a').allTextContents()).map(t=>t.replace('↗','').trim()),['Dashboard','Payslips','Payslip Designer','Configuration']);
    await page.screenshot({path:'/tmp/shrms-ui-screenshots/'+route+'-'+width+'.png',fullPage:true});
    if(route==='payroll_payslips'){
     const detail=page.locator('[data-pay-detail]').first();assert.ok(await detail.count());
     await detail.click();assert.equal(await page.locator('.pay-detail-drawer[open]').count(),1);
     assert.ok((await page.locator('.pay-detail-drawer[open] iframe').getAttribute('src')).includes('payroll-slip.php'));
     await page.keyboard.press('Escape');assert.equal(await page.locator('.pay-detail-drawer[open]').count(),0);
    }
   }
  }
  const salaryFixture=JSON.parse(readFileSync('/tmp/payroll-fixture.json','utf8'));
  // Published payslips use the same print document at desktop and phone widths.
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});
   await page.goto(base+'/payroll-slip.php?entry='+salaryFixture.entry);await layout();
   const slip=page.frameLocator('#payslip-document');
   await slip.getByText('MONTHLY PAYSLIP',{exact:true}).waitFor();
   assert.equal(await slip.locator('body').evaluate(e=>e.scrollWidth<=innerWidth+1),true,'Payslip iframe overflow');
   assert.equal(await page.getByRole('link',{name:'Download PDF',exact:true}).count(),1);
   assert.equal(await page.getByText('Download text PDF').count(),0);
   assert.equal(await slip.getByRole('img',{name:'Company logo'}).count(),1);
   await page.screenshot({path:'/tmp/shrms-ui-screenshots/monthly-payslip-'+width+'.png',fullPage:true});
  }
  await page.goto('file:///tmp/shrms-ui-screenshots/sample-payslip.html');
  await page.pdf({path:'/tmp/shrms-ui-screenshots/sample-payslip-print.pdf',format:'A4',printBackground:true,displayHeaderFooter:false});
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});await page.goto(base+'/super-admin.php?page=payroll_payslips&month=2025-10');await check();
   const choices=page.locator('[data-pay-select]');assert.ok(await choices.count()>0,'Ready payslips have no selection');
   assert.equal(await page.locator('[data-pay-review-selected]').isDisabled(),true);
   await choices.first().check();assert.equal(await page.locator('[data-pay-selected-count]').textContent(),'1 selected');
   await page.locator('[data-pay-review-selected]').click();assert.equal(await page.locator('#pay-publish-dialog').evaluate(e=>e.open),true);
   assert.equal(await page.locator('[data-pay-publish-preview] tr').count(),1);
   assert.equal(await page.locator('#pay-publish-dialog [name=confirm]').isChecked(),false);
   await layout();await page.screenshot({path:'/tmp/shrms-ui-screenshots/payslip-selection-'+width+'.png'});
   await page.keyboard.press('Escape');await page.locator('[data-pay-select-all]').check();
   assert.equal(await choices.evaluateAll(es=>es.every(e=>e.checked)),true);
   await page.locator('[data-pay-select-all]').uncheck();assert.equal(await page.locator('[data-pay-review-selected]').isDisabled(),true);
  }

  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});
   await page.goto(base+'/super-admin.php?page=employee-edit&id='+salaryFixture.employee+'#profile-salary');
   await check();
   assert.equal(await page.locator('[data-salary-editor]').count(),1);
   const salaryDate=page.locator('[name="salary[effective_from]"]');
   const initialDate=await salaryDate.inputValue();
   await salaryDate.fill('2020-01-01');await salaryDate.dispatchEvent('change');
   assert.match(await page.locator('[data-salary-rule-note]').innerText(),/available from|do not cover|No salary rules/);
   await salaryDate.fill(initialDate);await salaryDate.dispatchEvent('change');
   await page.locator('#profile-salary').screenshot({path:'/tmp/shrms-ui-screenshots/profile-salary-'+width+'.png'});
  }
  await page.setViewportSize({width:1440,height:1000});
  for(const route of ['settings','leave_routes','time_policies']){await page.goto(base+'/super-admin.php?page='+route);for(const width of [1440,390]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(350);await check();assert.equal(await page.locator('dialog[open]').count(),0,'Settings browsing must not open a modal');assert.equal(await page.locator('.settings-category[aria-current="true"]').count(),1);assert.equal(await page.locator('.settings-section-tabs a[aria-current="page"]').count(),1);assert.ok(await page.locator('.settings-menu').evaluate(e=>e.getBoundingClientRect().height<180),'Settings navigation too tall');await page.screenshot({path:'/tmp/shrms-ui-screenshots/settings-'+route+'-'+width+'.png',fullPage:true});}}
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/super-admin.php?page=overview');
  const nav=page.locator('body>aside');assert.equal(await nav.evaluate(e=>Math.round(e.getBoundingClientRect().width)),250);
  await page.locator('.menu-toggle').click();assert.equal(await nav.evaluate(e=>Math.round(e.getBoundingClientRect().width)),76);await layout();
  assert.ok(await nav.locator('nav a').first().getAttribute('title'));await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-collapsed.png'});
  await page.locator('.menu-toggle').click();await page.locator('.ui-search-trigger').click();await page.locator('#ui-menu-query').fill('attendance');assert.ok(await page.locator('.ui-search-results a').count()>0);await page.getByRole('button',{name:'Close search',exact:true}).click();assert.equal(await page.locator('.ui-search-dialog').evaluate(e=>e.open),false);
  await page.locator('#theme-toggle').click();assert.equal(await page.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(23, 25, 28)');await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-dark.png'});await page.locator('#theme-toggle').click();
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin.png'});
  await page.setViewportSize({width:1024,height:900});await layout();
  await page.setViewportSize({width:390,height:844});await layout();assert.equal(await nav.evaluate(e=>e.inert),true);await page.locator('.menu-toggle').click();assert.equal(await nav.evaluate(e=>e.inert),false);await page.keyboard.press('Escape');assert.equal(await nav.evaluate(e=>e.inert),true);await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-mobile.png'});
  await page.context().clearCookies();await page.goto(base+'/login.php');
  await page.locator('[name=email]').fill('payroll.ci');await page.locator('[name=password]').fill('Payroll-CI-2026!');
  await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login.php')),page.locator('.login-submit').click()]);
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});await page.goto(base+'/employee.php?page=my_salary');await check();
   assert.ok(await page.getByRole('link',{name:/Download .* payslip PDF/}).count()>0);
   assert.equal(await page.locator('.salary-history').getAttribute('open'),null);
   if(width===390)assert.ok(await page.locator('.ess-payslips').evaluate(e=>e.getBoundingClientRect().bottom>=e.querySelector('tbody').getBoundingClientRect().bottom),'Mobile payslip cards escape their panel');
   await page.locator('.salary-history>summary').click();assert.ok(await page.locator('.salary-history table').isVisible());
   await page.evaluate(()=>window.scrollTo(0,0));
   if(width===390)assert.ok(await page.locator('.ess-slip-actions').first().evaluate(e=>e.getBoundingClientRect().right<=innerWidth),'Mobile download action offscreen');
   await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee-payroll-'+width+'.png',fullPage:true});
  }
  await page.context().clearCookies();await page.setViewportSize({width:1440,height:1000});await login('ess.applicant');
  for(const route of ['overview','profile','my_shift','attendance','leaves','inbox','tasks','documents','performance','helpdesk']){await page.goto(base+'/employee.php?page='+route);await check();}
  await page.goto(base+'/employee.php?page=attendance&month=2026-01');await check();
  assert.equal(await page.locator('.ess-attendance-scroll tbody tr').count(),31);
  assert.equal(await page.locator('.ess-attendance-scroll th').filter({hasText:'Early arrival'}).count(),1);
  assert.equal(await page.locator('.ess-attendance-scroll th').filter({hasText:'Net overtime'}).count(),1);
  assert.equal(await page.locator('[data-attendance-live]').count(),4);
  await page.evaluate(()=>document.dispatchEvent(new CustomEvent('ess:punch-status',{detail:{state:'out',timing:{as_of:108000,limit:110000,worked:32400,after_end:7200,state:'out',first_in:72000,shift_start:68400,shift_end:100800,shift_label:'Test night shift',expired:false,inferred:false}}})));
  assert.equal(await page.locator('[data-attendance-live="late"]').innerText(),'01h 00m 00s');
  assert.equal(await page.locator('[data-attendance-live="overtime"]').innerText(),'01h 00m 00s');
  assert.equal(await page.locator('[data-attendance-live="worked"]').innerText(),'09h 00m 00s');
  await page.evaluate(()=>document.dispatchEvent(new CustomEvent('ess:punch-status',{detail:{state:'review',label:'Unavailable'}})));
  assert.equal(await page.locator('[data-attendance-live="worked"]').innerText(),'—');
  await page.reload();

  assert.ok(await page.locator('.ess-attendance-scroll').evaluate(e=>e.clientHeight>=320),'Employee attendance table collapsed');
  await page.locator('[data-dialog-open="correction-dialog"]').click();assert.equal(await page.locator('#correction-dialog').evaluate(e=>e.open),true);await page.keyboard.press('Escape');
  for(const width of [1440,390]){await page.setViewportSize({width,height:1000});await layout();await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee-attendance-'+width+'.png',fullPage:true,animations:'disabled'});}
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/employee.php?page=overview');
  assert.equal(await page.locator('[data-workday-value="after_end"]').count(),0,'Only one overtime total is shown');
  assert.equal(await page.locator('[data-workday-value="overtime"]').count(),1);
  assert.equal(await page.locator('.ess-workday-metrics small').filter({hasText:/^Overtime$/}).count(),1);
  assert.match(await page.locator('script[src^="assets/ess.js"]').getAttribute('src'),/ess\.js\?v=[a-f0-9]{12}$/,'Attendance script uses a content version');
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee.png'});
  await page.locator('[data-ess-theme]').click();assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('dark')));await page.locator('[data-ess-theme]').click();
  await page.locator('[data-dialog-open="correction-dialog"]').click();assert.equal(await page.locator('#correction-dialog').evaluate(e=>e.open),true);await page.keyboard.press('Escape');
  for(const width of [1024,768,390,360]){await page.setViewportSize({width,height:844});await layout();}
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee-mobile.png'});
  await page.locator('#menu').click();assert.equal(await nav.evaluate(e=>e.inert),false);await page.keyboard.press('Escape');
  assert.deepEqual(errors,[]);console.log('PASS: shared theme, both authenticated shells, navigation search/collapse, themes, modal and desktop/tablet/mobile layouts.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});





