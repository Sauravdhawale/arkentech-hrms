const assert=require('node:assert/strict');
const {spawn,execFileSync}=require('node:child_process');
const {mkdirSync,readFileSync}=require('node:fs');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
assert.equal(process.env.DB_NAME,'peopleflow_ci');
const server=spawn('php',['-S','127.0.0.1:8107','-t','.'],{stdio:'ignore'}),base='http://127.0.0.1:8107';
(async()=>{let browser,fixture=false;try{
 execFileSync('php',['tests/attendance_timeline.php','--ui']);fixture=true;const f=JSON.parse(readFileSync('/tmp/timeline-fixture.json','utf8'));
 for(let n=0;n<50;n++){try{if((await fetch(base+'/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/login.php');await page.locator('[name=email]').fill('test.admin');await page.locator('[name=password]').fill('Admin-Changed-2026!');await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login.php')),page.locator('.login-submit').click()]);
 await page.goto(base+'/super-admin.php?page=attendance_requests');assert.equal(await page.getByRole('heading',{name:'Attendance Corrections',exact:true}).count(),1);
 await page.locator('[name=timeline_employee]').selectOption(String(f.employee));await page.locator('[name=timeline_date]').fill(f.day);await Promise.all([page.waitForURL(u=>u.searchParams.has('timeline_employee')),page.getByRole('button',{name:'Open timeline',exact:true}).click()]);
 assert.equal(/Fatal error|Warning:|Parse error/.test(await page.locator('body').innerText()),false,await page.locator('body').innerText());
 assert.equal(await page.locator('.atl-boundary').count(),4);assert.equal(await page.locator('[data-tl-work]').innerText(),'8h 00m');assert.equal(await page.locator('[data-tl-break]').innerText(),'1h 00m');
 // Keyboard and non-drag controls adjust the same underlying timeline.
 await page.getByRole('slider',{name:'Time marker 3',exact:true}).focus();await page.keyboard.press('ArrowLeft');assert.equal(await page.locator('[data-tl-difference]').innerText(),'+0h 01m');
 await page.getByRole('button',{name:'Move marker 3 earlier by one minute',exact:true}).click();assert.equal(await page.locator('[data-tl-difference]').innerText(),'+0h 02m');
 await page.getByRole('button',{name:'Reset changes',exact:true}).click();
 // Drag the third marker left, then reset before the precise correction.
 const box=await page.getByRole('slider',{name:'Time marker 3',exact:true}).boundingBox();await page.mouse.move(box.x+box.width/2,box.y+box.height/2);await page.mouse.down();await page.mouse.move(box.x+box.width/2-30,box.y+box.height/2,{steps:5});await page.mouse.up();assert.notEqual(await page.locator('[data-tl-difference]').innerText(),'0h 00m');await page.getByRole('button',{name:'Reset changes',exact:true}).click();
 await page.getByLabel('Marker 3 time',{exact:true}).fill('2026-03-04T21:30');await page.getByLabel('Marker 3 time',{exact:true}).press('Tab');assert.equal(await page.locator('[data-tl-work]').innerText(),'8h 30m');assert.equal(await page.locator('[data-tl-break]').innerText(),'0h 30m');
 await page.getByLabel('Marker 2 activity',{exact:true}).selectOption('in');assert.equal(await page.locator('[data-tl-work]').innerText(),'9h 00m');await page.getByRole('button',{name:'Reset changes',exact:true}).click();
 await page.getByLabel('Marker 3 time',{exact:true}).fill('2026-03-04T20:30');await page.getByLabel('Marker 3 time',{exact:true}).press('Tab');assert.ok(await page.locator('[data-tl-save]').isDisabled());assert.match(await page.locator('[data-tl-message]').innerText(),/order/);await page.getByRole('button',{name:'Reset changes',exact:true}).click();
 mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});
 for(const width of [1440,768,390]){await page.setViewportSize({width,height:1000});assert.ok(await page.locator('body').evaluate(e=>e.scrollWidth<=innerWidth+1),'Timeline page overflow '+width);await page.screenshot({path:`/tmp/shrms-ui-screenshots/timeline-${width}.png`,fullPage:true,animations:'disabled'});}
 await page.setViewportSize({width:1440,height:1000});await page.locator('#theme-toggle').click();await page.screenshot({path:'/tmp/shrms-ui-screenshots/timeline-dark.png',fullPage:true});await page.locator('#theme-toggle').click();
 // Requests stay on the page and open the correct employee/day for atomic approval.
 await page.goto(base+`/super-admin.php?page=attendance_requests&timeline_employee=${f.employee}&timeline_date=${f.day}&request_id=${f.request}`);
 await page.getByLabel('Marker 3 time',{exact:true}).fill('2026-03-04T21:30');await page.getByLabel('Marker 3 time',{exact:true}).press('Tab');await page.locator('[data-attendance-timeline] [name=notes]').fill('UI test: approved office duty for thirty minutes');
 await Promise.all([page.waitForURL(u=>u.searchParams.get('timeline_employee')===String(f.employee)&&!u.searchParams.has('request_id')),page.getByRole('button',{name:'Save correction & approve request',exact:true}).click()]);
 assert.match(await page.locator('body').innerText(),/Attendance timeline corrected/);assert.equal(await page.locator('[data-tl-work]').innerText(),'8h 30m');
 await page.locator('.atl-audit summary').click();assert.match(await page.locator('.atl-audit').innerText(),/UI test: approved office duty/);assert.equal(await page.locator('.atl-audit-grid ul').first().locator('li').count(),4,'Original scans preserved');
 assert.equal(errors.length,0,errors.join('\n'));console.log('PASS: timeline navigation, drag, keyboard, exact editing, validation, responsive UI, audited save and request approval.');
}finally{if(browser)await browser.close();server.kill();if(fixture)execFileSync('php',['tests/attendance_timeline.php','--cleanup']);}})().catch(e=>{console.error(e);process.exitCode=1;});
