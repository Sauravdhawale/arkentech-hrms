'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.UI_BROWSER_EXECUTABLE?{executablePath:process.env.UI_BROWSER_EXECUTABLE}:{})});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  let fail=false;
  const data={date:'2026-11-02',today:'2026-11-03',timezone:'Asia/Kolkata',updated_label:'04:20:00 AM',counts:{total:3,in:1,out:1,unknown:1},corrected:false,message:'',punches:[
   {at:'2026-11-02T19:00:01+05:30',date_label:'02 Nov 2026',time_label:'07:00:01 PM',direction:'in',source:'Biometric',note:'Inferred from punch sequence'},
   {at:'2026-11-03T01:02:03+05:30',date_label:'03 Nov 2026',time_label:'01:02:03 AM',direction:'out',source:'Admin manual',note:'Recorded punch'},
   {at:'2026-11-03T02:03:04+05:30',date_label:'03 Nov 2026',time_label:'02:03:04 AM',direction:'unknown',source:'Biometric',note:'Direction needs review'}]};
  const summary='<section data-my-punches-summary><button data-my-punches-open>View punch times</button><div class="my-punches-counts">'+['total','in','out'].map(k=>`<div><span>${k}</span><strong data-punch-count="${k}">—</strong></div>`).join('')+'</div><p data-punch-summary-note></p></section>';
  await page.route('http://punch.test/**',route=>{
   const url=new URL(route.request().url());
   if(url.pathname.endsWith('my-punches.php')){
    if(fail)return route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({error:'Temporarily unavailable'})});
    const body=url.searchParams.has('date')?{...data,date:url.searchParams.get('date'),counts:{total:0,in:0,out:0,unknown:0},punches:[]}:data;
    return route.fulfill({contentType:'application/json',body:JSON.stringify(body)});
   }
   return route.fulfill({contentType:'text/html',body:`<!doctype html><html><meta name="viewport" content="width=device-width,initial-scale=1"><body class="shrms-ui employee-ui"><button data-my-punches-open>My punches</button>${summary}</body></html>`});
  });
  for(const width of [375,768,1440]){
   await page.setViewportSize({width,height:900});await page.goto('http://punch.test/employee.php');
   for(const name of ['style.css','workspace.css','ess.css','theme.css']) await page.addStyleTag({content:fs.readFileSync(path.join(process.env.UI_BASE_ASSETS||path.join(root,'assets'),name),'utf8').replace(/@import\s+url\([^)]*\)\s*;/g,'')});
   await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/my-punches.css'),'utf8')});
   await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/my-punches.js'),'utf8')});
   await page.waitForFunction(()=>document.querySelector('[data-punch-count="total"]').textContent==='3');
   await page.getByRole('button',{name:'My punches',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('[data-rows]').children.length===3);
   assert.match(await page.locator('dialog').innerText(),/01:02:03 AM/);assert.match(await page.locator('dialog').innerText(),/03 Nov 2026/);
   assert.match(await page.locator('[data-message]').innerText(),/1 unclassified/);
   assert.equal(await page.locator('[data-count="out"]').innerText(),'1');
   assert.equal(await page.locator('dialog').evaluate(e=>e.scrollWidth<=e.clientWidth+1),true,'Dialog overflow '+width);
   assert.equal(await page.locator('.my-punches-table-wrap').evaluate(e=>e.scrollWidth<=e.clientWidth+1),true,'Table overflow '+width);
   fs.mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});
   for(const dark of [false,true]){await page.evaluate(d=>document.body.classList.toggle('dark',d),dark);await page.screenshot({path:`/tmp/shrms-ui-screenshots/my-punches-${width}-${dark?'dark':'light'}.png`});}
   await page.locator('[data-date]').fill('2026-11-01');await page.locator('[data-date]').dispatchEvent('change');
   await page.waitForFunction(()=>document.querySelector('[data-count="total"]').textContent==='0');
   assert.equal(await page.locator('[data-punch-count="total"]').innerText(),'3','Historical selection must not change current dashboard counts');
   assert.match(await page.locator('[data-message]').innerText(),/No punches received/);
   await page.getByRole('button',{name:'Current shift',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('[data-count="total"]').textContent==='3');
   fail=true;await page.getByRole('button',{name:'Refresh',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('[data-message]').textContent.includes('last successful refresh'));
   fail=false;await page.keyboard.press('Escape');assert.equal(await page.locator('dialog').evaluate(e=>e.open),false);
   assert.equal(await page.getByRole('button',{name:'My punches',exact:true}).evaluate(e=>e===document.activeElement),true);
  }
  assert.deepEqual(errors,[]);console.log('PASS: punch dialog, seconds, dates, counts, empty/error states, date isolation, keyboard and responsive light/dark UI.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
