const assert=require('node:assert/strict');
const {readFileSync,mkdirSync}=require('node:fs');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.UI_BROWSER_PATH?{executablePath:process.env.UI_BROWSER_PATH}:{})});
 try{
 const page=await browser.newPage({viewport:{width:1280,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 const start=Date.parse('2026-11-02T18:00:00+05:30')/1000,end=start+9*3600;
 let data={events:[{at:start,state:'in'},{at:start+3600,state:'out'},{at:start+5400,state:'in'}],start,end,cutoff:start+7259,offset:19800,revision:'one',day:'2026-11-02',live:true,editable:true,endpoint:'/timeline'},failed=false;
 const payload=()=>({...data,original:data.events});
 await page.route('http://timeline.test/timeline',r=>r.fulfill({status:failed?503:200,contentType:'application/json',body:JSON.stringify(failed?{error:'Offline'}:payload())}));
 const html=()=>`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{font:15px Arial;background:#f3f4f6;color:#172033;padding:20px}.card{max-width:1100px;margin:auto;background:white;border-radius:16px}.button{border:1px solid #ccd1d9;border-radius:8px;padding:10px;color:inherit;background:white}h1{font-size:25px}</style><style>${readFileSync('assets/css/attendance-timeline.css','utf8')}</style></head><body><section class="card atl"><h1>Attendance Corrections</h1><h3>Demo employee · Night shift</h3><p>02 Nov, 06:00 PM → 03 Nov, 03:00 AM · Asia/Kolkata</p><form data-attendance-timeline><script type="application/json" data-timeline-config>${JSON.stringify(payload())}</script><input type="hidden" name="timeline_events"><input type="hidden" name="timeline_cutoff"><input type="hidden" name="timeline_revision"><div class="atl-live-bar"><span data-tl-live-status role="status"></span><button type="button" class="button" data-tl-refresh>Refresh live data</button></div><div class="atl-totals"><div><span>Working time so far</span><strong data-tl-work></strong></div><div><span>Break time within shift</span><strong data-tl-break></strong></div><div><span>Change in working time</span><strong data-tl-difference></strong></div></div><div class="atl-legend"><span><i class="atl-work-dot"></i> Working</span><span><i class="atl-break-dot"></i> Break</span></div><div class="atl-track" data-tl-track></div><div class="atl-scale"><span data-tl-start></span><span data-tl-end></span></div><p data-tl-message></p><div class="atl-boundaries" data-tl-boundaries></div><div class="atl-tools"><button type="button" class="button" data-tl-add>Add time marker</button><button type="button" class="button" data-tl-reset>Reset changes</button></div><label>Reason<textarea name="notes"></textarea></label><button class="button" data-tl-save>Save correction</button></form></section></body></html>`;
 await page.route('http://timeline.test/',r=>r.fulfill({contentType:'text/html',body:html()}));
 await page.goto('http://timeline.test/');await page.clock.install();await page.addScriptTag({content:readFileSync('assets/js/attendance-timeline.js','utf8')});
 assert.equal(await page.locator('[data-tl-work]').innerText(),'1h 30m');assert.equal(await page.locator('[data-tl-break]').innerText(),'0h 30m');
 await page.clock.runFor(2000);assert.equal(await page.locator('[data-tl-work]').innerText(),'1h 31m','Clock advances without a page reload');
 data.cutoff+=60;await page.getByRole('button',{name:'Refresh live data',exact:true}).click();await page.waitForFunction(()=>document.querySelector('[name=timeline_cutoff]').value===String(Date.parse('2026-11-02T20:01:59+05:30')/1000));assert.equal(await page.locator('[data-tl-work]').innerText(),'1h 31m');
 // A new OUT stops work and starts the live break.
 data.events.push({at:data.cutoff,state:'out'});data.cutoff+=120;data.revision='two';await page.getByRole('button',{name:'Refresh live data',exact:true}).click();await page.waitForFunction(()=>document.querySelectorAll('.atl-boundary').length===4);assert.equal(await page.locator('[data-tl-work]').innerText(),'1h 31m');assert.equal(await page.locator('[data-tl-break]').innerText(),'0h 32m');
 // Never overwrite a draft, its reason or revision when a new punch arrives.
 await page.getByLabel('Marker 3 time',{exact:true}).fill('2026-11-02T19:20');await page.getByLabel('Marker 3 time',{exact:true}).press('Tab');await page.locator('[name=notes]').fill('Office work, approved ten minutes');const draft=await page.locator('[name=timeline_events]').inputValue();
 data.events.push({at:data.cutoff,state:'in'});data.cutoff+=10;data.revision='three';await page.clock.runFor(10000);await page.waitForFunction(()=>document.querySelector('[data-tl-live-status]').textContent.includes('New data'));
 assert.equal(await page.locator('[name=timeline_events]').inputValue(),draft);assert.equal(await page.locator('[name=timeline_revision]').inputValue(),'two');assert.equal(await page.locator('[name=notes]').inputValue(),'Office work, approved ten minutes');assert.ok(await page.locator('[data-tl-save]').isDisabled());
 await page.getByRole('button',{name:'Discard edits & reload live data',exact:true}).click();await page.waitForFunction(()=>document.querySelectorAll('.atl-boundary').length===5);assert.equal(await page.locator('[name=timeline_revision]').inputValue(),'three');
 // Shift end never synthesizes OUT; an open IN continues after midnight.
 data.cutoff=end+7200;data.revision='four';await page.getByRole('button',{name:'Refresh live data',exact:true}).click();await page.waitForFunction(()=>document.querySelector('[name=timeline_revision]').value==='four');assert.match(await page.locator('[data-tl-live-status]').innerText(),/Checked in/);assert.equal(await page.locator('.atl-boundary').count(),5);assert.equal(await page.locator('[data-tl-work]').innerText(),'10h 28m');
 failed=true;await page.getByRole('button',{name:'Refresh live data',exact:true}).click();await page.waitForFunction(()=>document.querySelector('[data-tl-live-status]').textContent.includes('Connection interrupted'));
 failed=false;await page.getByRole('button',{name:'Refresh live data',exact:true}).click();await page.waitForFunction(()=>document.querySelector('[data-tl-live-status]').textContent.startsWith('Live'));
 mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});for(const width of [1280,390]){await page.setViewportSize({width,height:1000});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.screenshot({path:`/tmp/shrms-ui-screenshots/timeline-live-${width}.png`,fullPage:true});}
 // Exact date/time labels are visible, including the overnight day change.
 assert.match(await page.locator('.atl-period-time').first().innerText(),/02 Nov.*06:00:00 pm/);
 assert.match(await page.locator('.atl-period-time').last().innerText(),/03 Nov.*05:00:00 am/);
 assert.match(await page.locator('.atl-now').innerText(),/Now 05:00 am/);
 // Removing a closed break credits that interval, without changing the source payload.
 await page.getByRole('button',{name:'Remove break period 2',exact:true}).click();
 assert.equal(await page.locator('[data-tl-work]').innerText(),'10h 58m');assert.equal(await page.locator('[data-tl-break]').innerText(),'0h 02m');
 assert.equal(data.events[1].state,'out');assert.equal(await page.locator('.atl-boundary').count(),3);
 assert.match(await page.locator('[data-tl-live-status]').innerText(),/Editing snapshot/);
 await page.getByRole('button',{name:'Discard edits & reload live data',exact:true}).click();await page.waitForFunction(()=>document.querySelectorAll('.atl-boundary').length===5);
 // Removing interior work turns precisely that period into excluded/break time.
 await page.getByRole('button',{name:'Remove work period 3',exact:true}).click();
 assert.equal(await page.locator('[data-tl-work]').innerText(),'9h 56m');assert.equal(await page.locator('[data-tl-break]').innerText(),'1h 03m');
 const corrected=JSON.parse(await page.locator('[name=timeline_events]').inputValue());assert.deepEqual(corrected,[data.events[0],data.events[1],data.events[4]]);
 await page.getByRole('button',{name:'Discard edits & reload live data',exact:true}).click();await page.waitForFunction(()=>document.querySelectorAll('.atl-boundary').length===5);
 // Removing first work excludes the leading interval without an invalid leading OUT.
 await page.getByRole('button',{name:'Remove work period 1',exact:true}).click();
 assert.equal(JSON.parse(await page.locator('[name=timeline_events]').inputValue())[0].at,data.events[2].at);
 // A sole work period cannot silently erase an entire day's attendance.
 data.events=[{at:start,state:'in'}];data.revision='sole';await page.getByRole('button',{name:'Discard edits & reload live data',exact:true}).click();await page.waitForFunction(()=>document.querySelectorAll('.atl-boundary').length===1);
 await page.getByRole('button',{name:'Remove work period 1',exact:true}).click();assert.match(await page.locator('[data-tl-message]').innerText(),/Keep at least one working period/);assert.equal(await page.locator('.atl-boundary').count(),1);
 // Short breaks have exact labels even when the full-shift graph cannot fit text.
 data.events=[{at:start+180,state:'in'},{at:start+900,state:'out'},{at:start+1620,state:'in'}];data.cutoff=start+9600;data.revision='short-break';
 await page.getByRole('button',{name:'Refresh live data',exact:true}).click();await page.waitForFunction(()=>document.querySelector('[name=timeline_revision]').value==='short-break');
 assert.equal(await page.locator('.atl-punch-chip').count(),3);assert.match(await page.locator('.atl-punch-chip').nth(1).innerText(),/OUT.*06:15:00 pm/s);
 const beforeRange=await page.locator('[name=timeline_events]').inputValue(),beforeWork=await page.locator('[data-tl-work]').innerText();
 await page.getByRole('button',{name:'Full shift',exact:true}).click();assert.equal(await page.getByRole('button',{name:'Full shift',exact:true}).getAttribute('aria-pressed'),'true');assert.equal(await page.locator('[name=timeline_events]').inputValue(),beforeRange);assert.equal(await page.locator('[data-tl-work]').innerText(),beforeWork);assert.ok(await page.locator('[data-tl-save]').isDisabled());
 await page.getByRole('button',{name:'Recorded time',exact:true}).click();
 for(const width of [1280,390]){await page.setViewportSize({width,height:1000});await page.getByRole('button',{name:'Recorded time',exact:true}).click();assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.screenshot({path:`/tmp/shrms-ui-screenshots/timeline-readable-${width}.png`,fullPage:true});}
 assert.deepEqual(errors,[]);console.log('PASS: live totals, punches, draft protection, overnight overtime, responsive timeline, period removal, exact punch labels and non-mutating timeline zoom.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
