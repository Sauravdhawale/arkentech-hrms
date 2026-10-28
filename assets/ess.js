(()=>{'use strict';
// Both employee cards project the same server snapshot, using a monotonic clock.
// A brief delayed poll keeps an explicitly labelled estimate; prolonged outages pause it.
const liveGraceSeconds=90;
function projectShift(t,received){
 const age=Math.max(0,(performance.now()-received)/1000);
 const now=Math.min(t.as_of+Math.min(age,liveGraceSeconds),t.limit);
 const delta=Math.max(0,now-t.as_of);
 const worked=t.worked+(t.state==='in'?delta:0);
 const after=t.after_end+(t.state==='in'?Math.max(0,now-Math.max(t.as_of,t.shift_end)):0);
 const late=t.first_in==null?0:Math.max(0,t.first_in-t.shift_start);
 const early=t.first_in==null?0:Math.max(0,t.shift_start-t.first_in);
 const breaks=(t.break||0)+(t.state==='out'&&t.first_in!=null?Math.max(0,Math.min(now,t.shift_end)-Math.max(t.as_of,t.shift_start)):0);
 return {age,now,worked,after,late,early,breaks,overtime:Math.max(0,after-late),stale:age>=liveGraceSeconds,delayed:age>15,expired:t.expired||now>=t.limit};
}
function timerState(t,p,reconnecting){
 if(p.expired)return t.state==='in'?'Check-Out missing · review required':'Shift complete';
 if(p.stale)return 'Timer paused · reconnecting';
 if(reconnecting||p.delayed)return 'Reconnecting · estimated time';
 return t.state==='in'?(p.now>=t.shift_end?'Checked In · After-shift time':'Checked In · Working'):t.state==='out'?(p.now<t.shift_end?'Checked Out · Break running':'Checked Out'):'Not checked in';
}
function timerNote(t,p,reconnecting){
 if(p.expired)return `Counting window ended${t.limit_label?' at '+t.limit_label:''}. ${t.state==='in'?'Check-out is missing; ask HR to review the punches and allowed after-shift window.':'Showing the last recorded totals.'}`;
 if(p.stale)return 'Connection unavailable for 90 seconds. Totals are paused estimates until the server responds; no attendance records have been changed.';
 const source=t.inferred?'Estimated from alternating biometric punches. ':'Based on recorded punch directions. ';
 return `${reconnecting||p.delayed?'Reconnecting; temporarily estimating from the last verified status. ':''}${source}Work pauses while checked out. Net overtime is worked time after shift end minus late arrival, minimum zero. ${t.manual_baseline?'Earlier manual attendance is used as a baseline. ':''}Live estimates; payroll approval remains separate.`;
}
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
  }catch(error){badge.dataset.state='review';label.textContent='Status unavailable';detail.textContent='Waiting to reconnect';document.dispatchEvent(new CustomEvent('ess:punch-status',{detail:{transient:true,label:'Status unavailable',detail:'Waiting to reconnect.'}}));}
  finally{clearTimeout(timeout);busy=false;}
 }
 refresh();setInterval(refresh,10000);document.addEventListener('visibilitychange',refresh);
})();


(()=>{'use strict';
 const card=document.querySelector('[data-workday]');if(!card)return;
 let snapshot=null,received=0,reconnecting=false;
 const text=(key,value)=>{card.querySelector(`[data-workday-${key}]`).textContent=value;};
 const duration=seconds=>{seconds=Math.max(0,Math.floor(seconds));return `${String(Math.floor(seconds/3600)).padStart(2,'0')}h ${String(Math.floor(seconds/60)%60).padStart(2,'0')}m ${String(seconds%60).padStart(2,'0')}s`;};
 function render(){
  if(!snapshot)return;
  const t=snapshot,p=projectShift(t,received);
  const {worked,overtime}=p;
  const values={worked,remaining:Math.max(0,t.target-worked),break:p.breaks,allowed_break:t.allowed_break,excess_break:Math.max(0,p.breaks-t.allowed_break),after_end:p.after,overtime};
  Object.entries(values).forEach(([key,value])=>{const el=card.querySelector(`[data-workday-value="${key}"]`);if(el)el.textContent=duration(value);});
  card.querySelector('[data-workday-progress]').value=t.target?Math.min(100,worked/t.target*100):0;
  card.dataset.excess=values.excess_break>0?'true':'false';
  text('state',timerState(t,p,reconnecting));
  text('target',`Required work: ${duration(t.target)} · Net overtime excludes late arrival`);
  text('shift',t.shift_label);text('in',t.first_in_label);text('out',t.last_out_label);
  text('note',timerNote(t,p,reconnecting));
 }
 document.addEventListener('ess:punch-status',event=>{
  if(event.detail?.transient&&snapshot){reconnecting=true;render();return;}
  const t=event.detail?.timing;
  if(!t){snapshot=null;text('state',event.detail?.label||'Status unavailable');text('note',event.detail?.detail||'Waiting to reconnect');card.querySelectorAll('[data-workday-value]').forEach(el=>el.textContent='—');card.querySelector('[data-workday-progress]').value=0;text('in','—');text('out','—');return;}
  snapshot=t;received=performance.now();reconnecting=false;render();
 });
 setInterval(render,1000);
})();


// Current shift on the attendance page shares the existing authenticated status poll.
(()=>{'use strict';
 const card=document.querySelector('[data-attendance-today]');if(!card)return;
 let snapshot=null,received=0,reconnecting=false;
 const duration=n=>{n=Math.max(0,Math.floor(n));return `${String(Math.floor(n/3600)).padStart(2,'0')}h ${String(Math.floor(n/60)%60).padStart(2,'0')}m ${String(n%60).padStart(2,'0')}s`;};
 const set=(key,value)=>{card.querySelector(`[data-attendance-live-${key}]`).textContent=value;};
 function render(){
  if(!snapshot)return;const t=snapshot,p=projectShift(t,received);
  for(const [key,value] of Object.entries({worked:p.worked,late:p.late,early:p.early,overtime:p.overtime}))card.querySelector(`[data-attendance-live="${key}"]`).textContent=duration(value);
  set('state',timerState(t,p,reconnecting));
  set('shift',t.shift_label);
  set('note',timerNote(t,p,reconnecting));
 }
 document.addEventListener('ess:punch-status',event=>{
  if(event.detail?.transient&&snapshot){reconnecting=true;render();return;}
  const data=event.detail,t=data?.timing;
  if(!t||(data.state==='review'&&!t.expired)){snapshot=null;set('state',data?.label||'Unavailable');set('note',data?.detail||'Waiting for punch data.');set('shift',t?.shift_label||'');card.querySelectorAll('[data-attendance-live]').forEach(el=>el.textContent='—');return;}
  snapshot=t;received=performance.now();reconnecting=false;render();
 });
 setInterval(render,1000);
})();


})();
