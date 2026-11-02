'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage();
  const errors=[]; page.on('pageerror',error=>errors.push(error.message));
  const html=execFileSync('php',[path.join(__dirname,'employee_dashboard_fixture.php')],{encoding:'utf8'});
  for(const width of [375,768,1440]){
   await page.setViewportSize({width,height:1000});
   await page.setContent(html);
   for(const file of ['style.css','workspace.css','ess.css','theme.css','employee-dashboard.css'])
    await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets',file),'utf8')});
   await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/ess.js'),'utf8')});
   await page.evaluate(()=>{
    document.dispatchEvent(new CustomEvent('ess:punch-status',{detail:{state:'in',timing:{
     state:'in',as_of:150000,shift_start:130000,shift_end:160000,worked:16492,shift_worked:16492,
     before_start:0,after_end:0,first_in:135000,break:6055,allowed_break:5400,target:28800,
     first_in_label:'02 Nov, 07:27 PM',last_out_label:'03 Nov, 01:20 AM',
     shift_end_label:'03 Nov, 03:00 AM',shift_label:'Night shift · 02 Nov, 06:00 PM – 03 Nov, 03:00 AM'
    }}}));
   });
   assert.match(await page.locator('[data-workday-value="worked"]').innerText(),/04h 34m/);
   assert.match(await page.locator('[data-workday-state]').innerText(),/Checked In/);
   assert.equal(await page.locator('[data-workday-value]').count(),9);
   assert.equal(await page.locator('[data-workday-value="after_end"]').isVisible(),false);
   await page.locator('.ess-time-details summary').click();
   assert.equal(await page.locator('[data-workday-value="after_end"]').isVisible(),true);
   await page.locator('.ess-time-details summary').click();
   await page.locator('[data-dialog-open="correction-dialog"]').click();
   assert.equal(await page.locator('#correction-dialog').evaluate(el=>el.open),true);
   await page.locator('[data-dialog-close]').click();
   assert.equal(await page.locator('input[type=month]').inputValue(),'2026-11');
   for(const dark of [false,true]){
    await page.evaluate(dark=>document.body.classList.toggle('dark',dark),dark);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,'page overflow at '+width);
    const overflow=await page.locator('#content .panel').evaluateAll(els=>els.filter(el=>el.scrollWidth>el.clientWidth+2).map(el=>el.className));
    assert.deepEqual(overflow,[],'card overflow at '+width);
    assert.equal(await page.locator('.ess-balance-grid').evaluate(el=>getComputedStyle(el).overflowY),'visible');
    const font=await page.locator('[data-workday-value="worked"]').evaluate(el=>getComputedStyle(el).fontFamily);
    assert.doesNotMatch(font,/digital|segment/i);
    fs.mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});
    await page.screenshot({path:'/tmp/shrms-ui-screenshots/employee-dashboard-'+width+'-'+(dark?'dark':'light')+'.png',fullPage:true});
   }
   await page.locator('.ess-time-details summary').focus();
   await page.keyboard.press('Enter');
   assert.equal(await page.locator('.ess-time-details').evaluate(el=>el.open),true);
  }
  assert.deepEqual(errors,[]);
  console.log('PASS: employee dashboard at 375/768/1440px, light/dark, live hooks, details, correction dialog and overflow.');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
