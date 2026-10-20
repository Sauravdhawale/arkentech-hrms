/* Live-like workflows against the disposable CI database. Never production. */
const assert=require('node:assert/strict');
const {spawn,execFileSync}=require('node:child_process');
const {readFileSync,mkdirSync}=require('node:fs');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
assert.equal(process.env.DB_NAME,'peopleflow_ci');
const fixture=JSON.parse(execFileSync('php',['-r',`if(getenv('DB_NAME')!=='peopleflow_ci')exit(1);require 'auth.php';$db=db();$employee=(int)$db->query("SELECT id FROM users WHERE username='ess.applicant'")->fetchColumn();if(!$employee)exit(2);$q=$db->prepare("INSERT INTO hr_requests(user_id,kind,category,subject,details,start_date,end_date,status) VALUES(?,'regularisation','Attendance',?,'Missed scan: UI audit test','2026-02-02','2026-02-02',?)");for($i=0;$i<27;$i++)$q->execute([$employee,'UI audit correction '.$i,'Pending']);$q->execute([$employee,'UI audit reviewed','Approved']);echo json_encode(['employee'=>$employee,'pending'=>(int)$db->query("SELECT COUNT(*) FROM hr_requests WHERE kind='regularisation' AND status='Pending' AND user_id=".$employee)->fetchColumn()]);`],{encoding:'utf8'}));
const server=spawn('php',['-S','127.0.0.1:8101','-t','.'],{stdio:'ignore'}),base='http://127.0.0.1:8101';
(async()=>{let browser;try{
 for(let n=0;n<50;n++){try{if((await fetch(base+'/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/login.php');await page.locator('[name=email]').fill('test.admin');await page.locator('[name=password]').fill('Admin-Changed-2026!');await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login.php')),page.locator('.login-submit').click()]);
 mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});
 async function healthy(){assert.ok(await page.locator('body').evaluate(e=>e.scrollWidth<=innerWidth+1),'Page overflow '+page.url());assert.equal(/Fatal error|Warning:|Workspace temporarily unavailable/.test(await page.locator('body').innerText()),false);}
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:1000});await page.goto(base+'/super-admin.php?page=shifts');await healthy();
  await page.getByRole('button',{name:'+ Add Shifts',exact:true}).click();const form=page.locator('[data-shift-editor]');
  await form.locator('[name=start]').fill('09:00 AM');await form.locator('[name=end]').fill('06:00 PM');await form.locator('[name=break_hours]').fill('1');await form.locator('[name=break_remainder]').fill('0');await form.locator('[name=required_hours]').fill('8');
  assert.match(await form.locator('[data-shift-preview]').innerText(),/9h 00m scheduled.*8h 00m after break/);
  assert.match(await form.locator('[data-shift-preview]').innerText(),/same day/);
  await form.locator('[name=end]').fill('06:00 AM');assert.match(await form.locator('[data-shift-preview]').innerText(),/21h 00m/);assert.match(await form.locator('[data-shift-preview]').innerText(),/longer than 16 hours/);
  await form.locator('[name=start]').fill('06:00 PM');await form.locator('[name=end]').fill('03:00 AM');assert.match(await form.locator('[data-shift-preview]').innerText(),/Ends next day/);await healthy();
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/shift-preview-'+width+'.png'});await page.keyboard.press('Escape');
  await page.goto(base+'/super-admin.php?page=attendance_requests&employee_id='+fixture.employee);await healthy();
  assert.equal(await page.locator('.request-item').count(),25);assert.equal(await page.getByLabel('Request status').inputValue(),'Pending');
  await page.getByRole('link',{name:'Next',exact:true}).click();assert.equal(await page.locator('.request-item').count(),fixture.pending-25);
  await page.getByLabel('Request status').selectOption('Approved');await page.getByRole('button',{name:'Apply filters',exact:true}).click();assert.ok((await page.locator('.request-list').innerText()).includes('UI audit reviewed'));
  await page.getByLabel('Request status').selectOption('Pending');await page.getByRole('button',{name:'Apply filters',exact:true}).click();await page.locator('.request-item>summary').first().click();
  assert.ok(await page.locator('.request-item[open] [name=check_in]').isVisible());await healthy();await page.screenshot({path:'/tmp/shrms-ui-screenshots/attendance-requests-'+width+'.png',fullPage:true});
  await page.goto(base+'/super-admin.php?page=payroll_payslips&month=2025-09');await healthy();
  await page.locator('.pay-row-issue>summary').first().click();assert.ok(await page.locator('.pay-issue-groups').first().isVisible());assert.ok(await page.getByRole('link',{name:'Download PDF',exact:true}).count());assert.equal(await page.getByText('Download text PDF',{exact:true}).count(),0);
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/payroll-guidance-'+width+'.png',fullPage:true});
  await page.goto(base+'/super-admin.php?page=sync');await healthy();assert.ok(await page.getByRole('columnheader',{name:'Received at',exact:true}).count());assert.ok((await page.locator('main').innerText()).includes('Saved punches confirms receipt'));
  await page.screenshot({path:'/tmp/shrms-ui-screenshots/sync-history-'+width+'.png',fullPage:true});
 }
 await page.setViewportSize({width:1440,height:1000});
 await page.goto(base+'/super-admin.php?page=attendance_requests&employee_id='+fixture.employee);await page.locator('.request-item>summary').first().click();
 const first=page.locator('.request-item[open]');await first.getByLabel('Decision',{exact:true}).selectOption('Rejected');await first.getByLabel('Review reason',{exact:true}).fill('CI test: insufficient information');await first.getByRole('button',{name:'Save decision',exact:true}).click();await healthy();
 await page.getByLabel('Request status').selectOption('Rejected');await page.getByRole('button',{name:'Apply filters',exact:true}).click();assert.match(await page.locator('.request-list').innerText(),/UI audit correction 26/);
 for(const route of ['recruitment_candidates','recruitment_applications','recruitment_interviews','recruitment_offers']){await page.goto(base+'/super-admin.php?page='+route);assert.ok(await page.getByText('Planned feature',{exact:true}).isVisible());await healthy();}
 await page.goto(base+'/super-admin.php?page=employee-add');const department=page.locator('[name=department_id]'),designation=page.locator('[name=designation_id]');const available=await department.locator('option').evaluateAll(es=>es.filter(e=>e.value).map(e=>e.value));for(const id of available.slice(0,3)){await department.selectOption(id);assert.ok(await designation.locator('option').evaluateAll((es,id)=>es.every(e=>!e.value||e.hidden||e.disabled||e.dataset.department===id||e.dataset.legacyDepartment===id),id));}
 await page.goto(base+'/super-admin.php?page=settings');assert.equal(await page.getByRole('heading',{name:'Company Profile',exact:false}).count(),1);await healthy();
 assert.deepEqual(errors,[]);console.log('PASS: shift previews, correction filters/pagination/rejection, payroll guidance/download actions, sync labels, planned sections and employee department filtering at desktop/mobile widths.');
}finally{if(browser)await browser.close();server.kill();}})().catch(e=>{console.error(e);process.exitCode=1;});
