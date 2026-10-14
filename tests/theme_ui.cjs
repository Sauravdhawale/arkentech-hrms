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
  // Salary setup stays in employee Create/Edit and shares payroll assignments.
  const salaryFixture=JSON.parse(require('node:fs').readFileSync('/tmp/payroll-fixture.json','utf8'));
  await page.goto(base+'/super-admin.php?page=employee-add');
  await page.locator('[name=first_name]').fill('ProfileSalary');
  await page.locator('[name=last_name]').fill('UI');
  await page.locator('[name=username]').fill('profile.salary.ui');
  await page.locator('[name=joining_date]').fill('2025-01-01');
  const compatible=await page.locator('[name=designation_id] option[data-department]').evaluateAll(es=>es.filter(e=>e.dataset.department).map(e=>({designation:e.value,department:e.dataset.department})));
  assert.ok(compatible.length);
  await page.locator('[name=department_id]').selectOption(compatible[0].department);
  await page.locator('[name=designation_id]').selectOption(compatible[0].designation);
  await page.locator('#setup-profile-salary').check();
  await page.locator('[name="salary[structure_id]"]').selectOption(String(salaryFixture.structure));
  await page.locator('[name="salary[salary_amount]"]').fill('30000');
  await page.locator('[name="salary[effective_from]"]').fill('2025-01-01');
  await page.getByRole('button',{name:'Save employee',exact:true}).click();
  await page.waitForURL(u=>u.searchParams.get('page')==='employee-edit');
  await check();
  assert.match(await page.locator('.profile-salary-preview').innerText(),/30000.00/);
  const createdProfile=new URL(page.url()).searchParams.get('id');
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});await layout();
   await page.locator('#profile-salary').scrollIntoViewIfNeeded();
   await page.screenshot({path:'/tmp/shrms-ui-screenshots/profile-salary-'+width+'.png',animations:'disabled'});
  }
  await page.setViewportSize({width:1440,height:1000});
  await page.locator('.profile-salary-preview [name=confirm]').check();
  await page.getByRole('button',{name:'Confirm & save salary',exact:true}).click();
  await page.waitForURL(u=>u.searchParams.get('tab')==='payroll');
  await check();assert.match(await page.locator('main').innerText(),/30000.00/);
  await page.getByRole('link',{name:'Set / revise salary',exact:true}).click();
  await page.waitForURL(u=>u.searchParams.get('page')==='employee-edit');
  await page.locator('#profile-salary [name="salary[salary_amount]"]').waitFor({state:'visible'});
  assert(await page.locator('#profile-salary [name="salary[salary_amount]"]').isVisible(),'Salary amount is visible directly on edit');
  await page.locator('[name="salary[salary_amount]"]').fill('32000');
  await page.locator('[name="salary[effective_from]"]').fill('2025-02-01');
  await page.getByRole('button',{name:'Preview salary breakup',exact:true}).click();
  await check();assert.match(await page.locator('.profile-salary-preview').innerText(),/32000.00/);
  await page.locator('.profile-salary-preview [name=confirm]').check();
  await page.getByRole('button',{name:'Confirm & save salary',exact:true}).click();
  await page.waitForURL(u=>u.searchParams.get('tab')==='payroll');
  await check();assert.match(await page.locator('main').innerText(),/32000.00/);
  await page.goto(base+'/super-admin.php?page=employee_salary&employee_id='+createdProfile);
  assert.equal(await page.locator('.pay-modern-table tbody tr').count(),2,'One initial salary and one revision');
  for(const route of ['overview','employees','employee-add','settings','departments','designations','roles','attendance_dashboard','attendance','manual_attendance','attendance_requests','daily_work_status','monthly','devices','mapping','punch_log','sync','leaves','payroll_dashboard','recruitment_dashboard','documents','announcements','performance','tasks','helpdesk','reports','account']){
   await page.goto(base+'/super-admin.php?page='+route);await check();assert.equal(await page.locator('body>aside').count(),1,route+' shell');
  }
  for(const route of ['payroll_dashboard','salary_components','salary_structures','payroll_payslips','payroll_loans']){
   await page.goto(base+'/super-admin.php?page='+route);await check();
   if(route==='payroll_dashboard')assert.equal(await page.locator('.pay-kpi').count(),6);
   if(route==='payroll_payslips'){assert.equal(await page.locator('[aria-label="Payslip tools"]').count(),0);await page.getByRole('button',{name:'+ Create',exact:true}).click();assert.equal(await page.locator('#create-monthly-payroll').evaluate(e=>e.open),true);await page.getByRole('button',{name:'Close create payroll'}).click();}
   if(route==='salary_components'){
    assert.equal(await page.locator('dialog[open]').count(),0,'Configuration must not auto-open a form');
    await page.locator('[data-pay-method="Fixed"]').click();
    await page.locator('[data-pay-search]').fill('no-such-component-123');
    assert.equal(await page.locator('[data-pay-row]:visible').count(),0);
    assert.equal(await page.locator('[data-pay-empty]').isVisible(),true);
    await page.locator('[data-pay-search]').fill('');await page.locator('[data-pay-method=""]').click();
   }
   for(const width of [1440,390]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(350);await layout();await page.screenshot({path:'/tmp/shrms-ui-screenshots/'+route+'-'+width+'.png',fullPage:true,animations:'disabled'});}
   await page.setViewportSize({width:1440,height:1000});
  }
  assert.equal(await page.locator('.pay-nav a').count(),3);
  await page.goto(base+'/super-admin.php?page=employee_salary');await check();
  assert.equal(await page.locator('table table').count(),0,'Salary history must not contain nested tables');
  const salaryLink=page.getByRole('link',{name:'View salary breakup',exact:true}).first();
  assert.ok(await salaryLink.count(),'Salary fixture missing');await salaryLink.click();await check();
  assert.equal(await page.locator('.salary-summary>div').count(),3);
  assert.ok(await page.locator('.salary-detail').innerText().then(t=>t.includes('not a monthly payslip')));
  for(const width of [1440,390]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(400);await layout();await page.screenshot({path:'/tmp/shrms-ui-screenshots/salary-detail-'+width+'.png',fullPage:true});}
  await page.setViewportSize({width:794,height:1123});await page.emulateMedia({media:'print'});
  assert.equal(await page.locator('.pay-toolbar').isVisible(),false);
  assert.equal(await page.locator('.salary-detail').isVisible(),true);
  for(const wrap of await page.locator('.salary-detail .app-table-scroll').all())assert.equal(await wrap.evaluate(e=>e.scrollHeight<=e.clientHeight+1&&e.scrollWidth<=e.clientWidth+1),true,'Printed salary table clipped');
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/salary-print.png',fullPage:true});
  await page.emulateMedia({media:'screen'});await page.setViewportSize({width:1440,height:1000});

  for(const route of ['settings','leave_routes','time_policies']){await page.goto(base+'/super-admin.php?page='+route);for(const width of [1440,390]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(350);await check();assert.equal(await page.locator('dialog[open]').count(),0,'Settings browsing must not open a modal');assert.equal(await page.locator('.settings-category[aria-current="true"]').count(),1);assert.equal(await page.locator('.settings-section-tabs a[aria-current="page"]').count(),1);assert.ok(await page.locator('.settings-menu').evaluate(e=>e.getBoundingClientRect().height<180),'Settings navigation too tall');await page.screenshot({path:'/tmp/shrms-ui-screenshots/settings-'+route+'-'+width+'.png',fullPage:true});}}
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/super-admin.php?page=payslip_settings');
  assert.equal(await page.locator('.settings-menu').count(),1);
  assert.equal(await page.locator('.settings-menu a[href="?page=salary_components"]').count(),0);
  assert.equal(await page.locator('.settings-menu a[href="?page=salary_structures"]').count(),0);
  assert.equal(await page.locator('.settings-menu a[href="?page=payroll_settings"]').count(),0);
  const editor=page.locator('[data-pay-template-editor]');assert.equal(await editor.count(),1);
  await editor.evaluate(e=>{const details=e.closest('details');if(details)details.open=true;const dialog=e.closest('dialog');if(dialog&&!dialog.open)dialog.showModal();});
  await editor.getByRole('button',{name:'Classic layout',exact:true}).click();
  await editor.getByRole('button',{name:'Add attribute',exact:true}).click();
  await editor.locator('[data-attribute-row] input').first().fill('office_label');
  await editor.locator('[data-attribute-row] input').last().fill('Pune office');
  await editor.getByRole('button',{name:'Preview sample payslip',exact:true}).click();
  await page.waitForFunction(()=>document.querySelector('[data-template-message]')?.textContent.includes('Sample preview only'));
  assert.ok(await editor.locator('[data-template-preview]').getAttribute('srcdoc').then(s=>s.includes('Sample Employee')));
  for(const width of [1440,390]){await page.setViewportSize({width,height:1000});await layout();await page.screenshot({path:'/tmp/shrms-ui-screenshots/payroll-template-'+width+'.png',fullPage:true});}
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/super-admin.php?page=overview');
  const nav=page.locator('body>aside');assert.equal(await nav.evaluate(e=>Math.round(e.getBoundingClientRect().width)),250);
  await page.locator('.menu-toggle').click();assert.equal(await nav.evaluate(e=>Math.round(e.getBoundingClientRect().width)),76);await layout();
  assert.ok(await nav.locator('nav a').first().getAttribute('title'));await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-collapsed.png'});
  await page.locator('.menu-toggle').click();await page.locator('.ui-search-trigger').click();await page.locator('#ui-menu-query').fill('payslip');assert.ok(await page.locator('.ui-search-results a').count()>0);await page.getByRole('button',{name:'Close search',exact:true}).click();assert.equal(await page.locator('.ui-search-dialog').evaluate(e=>e.open),false);
  await page.locator('#theme-toggle').click();assert.equal(await page.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(23, 25, 28)');await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-dark.png'});await page.locator('#theme-toggle').click();
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin.png'});
  await page.setViewportSize({width:1024,height:900});await layout();
  await page.setViewportSize({width:390,height:844});await layout();assert.equal(await nav.evaluate(e=>e.inert),true);await page.locator('.menu-toggle').click();assert.equal(await nav.evaluate(e=>e.inert),false);await page.keyboard.press('Escape');assert.equal(await nav.evaluate(e=>e.inert),true);await page.screenshot({path:'/tmp/shrms-ui-screenshots/admin-mobile.png'});
  await page.context().clearCookies();await page.setViewportSize({width:1440,height:1000});await login('ess.applicant');
  for(const route of ['overview','profile','my_shift','attendance','leaves','inbox','my_salary','tasks','documents','performance','helpdesk']){await page.goto(base+'/employee.php?page='+route);await check();}
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
  await page.goto(base+'/employee.php?page=overview');await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee.png'});
  await page.locator('[data-ess-theme]').click();assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('dark')));await page.locator('[data-ess-theme]').click();
  await page.locator('[data-dialog-open="correction-dialog"]').click();assert.equal(await page.locator('#correction-dialog').evaluate(e=>e.open),true);await page.keyboard.press('Escape');
  for(const width of [1024,768,390,360]){await page.setViewportSize({width,height:844});await layout();}
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee-mobile.png'});
  await page.locator('#menu').click();assert.equal(await nav.evaluate(e=>e.inert),false);await page.keyboard.press('Escape');
  assert.deepEqual(errors,[]);console.log('PASS: shared theme, both authenticated shells, navigation search/collapse, themes, modal and desktop/tablet/mobile layouts.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
