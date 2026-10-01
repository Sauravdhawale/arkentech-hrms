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
  }catch(error){badge.dataset.state='review';label.textContent='Status unavailable';detail.textContent='Waiting to reconnect';}
  finally{clearTimeout(timeout);busy=false;}
 }
 refresh();setInterval(refresh,10000);document.addEventListener('visibilitychange',refresh);
})();
