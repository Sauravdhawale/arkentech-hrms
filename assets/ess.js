(()=>{'use strict';const clock=document.querySelector('[data-ess-clock]');if(clock){const started=performance.now(),epoch=Number(clock.dataset.time);const tick=()=>{clock.textContent=new Intl.DateTimeFormat('en-US',{hour12:true,timeZone:clock.dataset.zone,hour:'2-digit',minute:'2-digit',second:'2-digit'}).format(new Date(epoch+performance.now()-started));};tick();setInterval(tick,1000);}document.querySelector('[data-ess-theme]')?.addEventListener('click',()=>document.getElementById('theme')?.click());document.addEventListener('click',event=>{document.querySelectorAll('.ess-bell[open]').forEach(el=>{if(!el.contains(event.target))el.open=false;});});document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('.ess-bell[open]').forEach(el=>el.open=false);});})();
(()=>{'use strict';
const theme=document.querySelector('[data-ess-theme]');
const labelTheme=()=>{if(theme){const label=document.body.classList.contains('dark')?'Switch to light mode':'Switch to dark mode';theme.setAttribute('aria-label',label);theme.title=label;}};
labelTheme();new MutationObserver(labelTheme).observe(document.body,{attributes:true,attributeFilter:['class']});
document.querySelectorAll('[data-dialog-open]').forEach(button=>button.addEventListener('click',()=>{const dialog=document.getElementById(button.dataset.dialogOpen);if(dialog&&!dialog.open)dialog.showModal();}));
document.querySelectorAll('[data-dialog-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
document.querySelectorAll('dialog[data-open-on-load]').forEach(dialog=>dialog.showModal());
document.querySelectorAll('.ess-dropdown').forEach(menu=>menu.addEventListener('toggle',()=>{if(menu.open)document.querySelectorAll('.ess-dropdown').forEach(other=>{if(other!==menu)other.open=false;});}));
document.addEventListener('click',event=>document.querySelectorAll('.ess-dropdown[open]').forEach(menu=>{if(!menu.contains(event.target))menu.open=false;}));
document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('.ess-dropdown[open]').forEach(menu=>menu.open=false);});
})();


(()=>{'use strict';
 const badge=document.querySelector('[data-punch-status]');if(!badge)return;
 const label=badge.querySelector('[data-punch-label]'),detail=badge.querySelector('[data-punch-detail]');let busy=false;
 async function refresh(){
  if(busy||document.hidden)return;busy=true;
  const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),8000);
  try{
   const url=new URL('employee.php',location.href);url.searchParams.set('punch_status','1');
   const response=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:controller.signal});
   if(!response.ok||!response.headers.get('content-type')?.includes('application/json'))throw new Error('unavailable');
   const data=await response.json();if(!['none','in','out','review'].includes(data.state))throw new Error('invalid');
   badge.dataset.state=data.state;label.textContent=data.label;detail.textContent=data.detail;
   document.dispatchEvent(new CustomEvent('ess:punch-status',{detail:data}));
  }catch(error){badge.dataset.state='review';label.textContent='Status unavailable';detail.textContent='Waiting to reconnect';document.dispatchEvent(new CustomEvent('ess:punch-status',{detail:{label:'Status unavailable',detail:'Live timer paused. Waiting to reconnect.'}}));}
  finally{clearTimeout(timeout);busy=false;}
 }
 refresh();setInterval(refresh,10000);document.addEventListener('visibilitychange',refresh);
})();


(()=>{'use strict';
 const card=document.querySelector('[data-workday]');if(!card)return;
 let snapshot=null,received=0;
 const text=(key,value)=>{card.querySelector(`[data-workday-${key}]`).textContent=value;};
 const duration=seconds=>{seconds=Math.max(0,Math.floor(seconds));return `${String(Math.floor(seconds/3600)).padStart(2,'0')}h ${String(Math.floor(seconds/60)%60).padStart(2,'0')}m ${String(seconds%60).padStart(2,'0')}s`;};
 function render(){
  if(!snapshot)return;
  const t=snapshot,age=(performance.now()-received)/1000;
  // Do not invent continued work after a failed refresh or a stale connection.
  const now=Math.min(t.as_of+Math.min(age,15),t.limit),delta=Math.max(0,now-t.as_of);
  const worked=t.worked+(t.state==='in'?delta:0);
  const after=t.after_end+(t.state==='in'?Math.max(0,now-Math.max(t.as_of,t.shift_end)):0);
  const breaks=t.break+(t.state==='out'&&t.first_in!==null?Math.max(0,Math.min(now,t.shift_end)-Math.max(t.as_of,t.shift_start)):0);
  const extra=Math.max(0,worked-t.target);
  const overtime=t.overtime_rule==='After shift end'?after:t.overtime_rule==='After both'?Math.min(after,extra):extra;
  const values={worked,remaining:Math.max(0,t.target-worked),break:breaks,allowed_break:t.allowed_break,excess_break:Math.max(0,breaks-t.allowed_break),overtime};
  Object.entries(values).forEach(([key,value])=>card.querySelector(`[data-workday-value="${key}"]`).textContent=duration(value));
  card.querySelector('[data-workday-progress]').value=t.target?Math.min(100,worked/t.target*100):0;
  card.dataset.excess=values.excess_break>0?'true':'false';
  text('state',age>15?'Sync delayed':t.expired?'Shift ended':t.state==='in'?'Checked In · Working':t.state==='out'?(now<t.shift_end?'Checked Out · Break running':'Checked Out'):'Not checked in');
  text('target',`Required work: ${duration(t.target)} · Overtime: ${t.overtime_rule.toLowerCase()}`);
  text('shift',t.shift_label);text('in',t.first_in_label);text('out',t.last_out_label);
  text('note',age>15?'Live timer paused while waiting for fresh data.':`${t.inferred?'Estimated from alternating biometric punches. ':'From recorded punch directions. '}Work pauses while checked out. Breaks count only within shift hours. ${t.manual_baseline?'Earlier manual attendance is used as a baseline; its internal breaks are not available. ':''}${t.expired?'Counting stopped at the configured shift window. ':''}Live totals are estimates; payroll records are unchanged.`);
 }
 document.addEventListener('ess:punch-status',event=>{
  const t=event.detail?.timing;
  if(!t){snapshot=null;text('state',event.detail?.label||'Status unavailable');text('note',event.detail?.detail||'Waiting to reconnect');card.querySelectorAll('[data-workday-value]').forEach(el=>el.textContent='—');card.querySelector('[data-workday-progress]').value=0;text('in','—');text('out','—');return;}
  snapshot=t;received=performance.now();render();
 });
 setInterval(render,1000);
})();
