'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.UI_PLAYWRIGHT_PATH||'playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.UI_BROWSER_PATH?{executablePath:process.env.UI_BROWSER_PATH}:{})});
 try{
  const page=await browser.newPage();
  for(const mode of ['team_people','team_attendance','month']){
   const html=execFileSync('php',[path.join(__dirname,'team_workspace_fixture.php')],{encoding:'utf8',env:{...process.env,TEAM_UI_PAGE:mode}});
   for(const width of [390,768,1440,1920]){
    await page.setViewportSize({width,height:900});await page.setContent(html);
    for(const file of ['style.css','workspace.css','ess.css','tables.css','theme.css','team-workspace.css'])await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets',file),'utf8')});
    await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/tables.js'),'utf8')});
    assert.equal(await page.locator('.team-table').evaluate(el=>getComputedStyle(el).maxHeight),'none','No forced short table viewport');
    assert.equal(await page.locator('.team-intro,.team-tabs,input[name=search]').count(),0);
    assert.equal(await page.getByRole('button',{name:'Search',exact:true}).count(),0);
    assert.ok(await page.locator('.team-table tbody td').first().evaluate(el=>parseFloat(getComputedStyle(el).fontSize)>=16),'Readable table text');
    const bounds=await page.locator('.team-records').boundingBox();
    if(mode!=='month')assert.ok(bounds.y<160,'Table starts directly below the page title');
    if(width===1920)assert.ok(bounds.width>1550,'Table uses the full desktop workspace');
    assert.equal(await page.getByRole('button',{name:/Save|Delete|Check In|Check Out/}).count(),0);
    if(mode==='month')assert.match(await page.locator('tbody').innerText(),/7h 45m/);
    else assert.equal(await page.getByRole('link',{name:'View month'}).count(),4);
    for(const dark of [false,true]){
     await page.evaluate(value=>document.body.classList.toggle('dark',value),dark);
     assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,mode+' page overflow at '+width);
     assert.equal(await page.locator('.team-records').evaluate(el=>el.scrollWidth<=el.clientWidth+1),true,'table escapes card');
     const control=mode==='month'?page.locator('input[type=month]'):page.getByRole('link',{name:'View month'}).first();await control.focus();
     assert.equal(await control.evaluate(el=>el===document.activeElement),true);
     fs.mkdirSync('/tmp/shrms-ui-screenshots',{recursive:true});
     await page.screenshot({path:`/tmp/shrms-ui-screenshots/team-${mode}-${width}-${dark?'dark':'light'}.png`,fullPage:true});
    }
   }
  }
  console.log('PASS: team People, live attendance and month views, 390/768/1440/1920px, light/dark, labels, read-only actions and overflow.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});

