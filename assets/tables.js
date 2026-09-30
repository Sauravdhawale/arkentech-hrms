'use strict';
// Bound each data table independently, including tables outside the new directory.
document.querySelectorAll('table').forEach(table=>{
 if(table.classList.contains('calendar')||!table.tHead)return;
 let wrap=table.parentElement;
 if(!wrap.classList.contains('table-scroll')&&!wrap.classList.contains('app-table-scroll')){wrap=document.createElement('div');table.before(wrap);wrap.append(table);}
 wrap.classList.add('app-table-scroll');wrap.tabIndex=0;wrap.setAttribute('role','region');if(!wrap.hasAttribute('aria-label'))wrap.setAttribute('aria-label','Scrollable records');
 if(table.dataset.freezeFirst==='true')wrap.classList.add('freeze-first');
 // Keep the table inside the viewport. Recalculate after filters or resizing.
 const fit=()=>{const top=wrap.getBoundingClientRect().top;const room=window.innerHeight-Math.max(120,top)-60;wrap.style.maxHeight=Math.max(220,Math.min(window.innerHeight*.64,room))+'px';};
 fit();window.addEventListener('resize',fit);document.addEventListener('toggle',fit,true);
});
const bulk=document.querySelector('[data-bulk-form]');
if(bulk){
 const checks=[...document.querySelectorAll('[data-employee-select]')],all=document.querySelector('[data-select-employees]'),counter=bulk.querySelector('[data-selection-count]'),dialog=bulk.querySelector('[data-bulk-dialog]');
 const selected=()=>checks.filter(c=>c.checked);
 const update=()=>{const n=selected().length;counter.textContent=n+' selected';all.checked=checks.length>0&&n===checks.length;all.indeterminate=n>0&&n<checks.length;};
 all?.addEventListener('change',()=>{checks.forEach(c=>c.checked=all.checked);update();});checks.forEach(c=>c.addEventListener('change',update));
 bulk.querySelector('[data-bulk-review]').addEventListener('click',()=>{const n=selected().length;if(!n){alert('Select at least one employee.');return;}const deleting=bulk.elements.operation.value==='delete';const assignments=['bulk_department','bulk_designation','bulk_role'].map(k=>bulk.elements[k]).filter(s=>s.value).map(s=>s.selectedOptions[0].textContent);if(!deleting&&!assignments.length){alert('Choose an assignment first.');return;}bulk.querySelector('[data-bulk-summary]').textContent=deleting?'Permanently delete '+n+' selected employees, their logins, attendance, requests, documents and mappings? This cannot be undone. Raw device logs remain unlinked.':'Apply '+assignments.join(', ')+' to '+n+' selected employees?';bulk.elements.confirmed.checked=false;dialog.showModal();});
 update();
}

// Keyset batches retain the current filters and avoid offset drift as live punches arrive.
const punchScroll=document.querySelector('[data-punch-scroll]');
if(punchScroll){
 const button=document.querySelector('[data-punch-load]'),status=document.querySelector('[data-punch-load-status]');let busy=false,failed=false;
 async function more(){
  const next=punchScroll.dataset.next;if(!next||busy)return;busy=true;button.disabled=true;status.textContent='Loading punches…';
  try{
   const response=await fetch(next,{credentials:'same-origin'});if(!response.ok)throw new Error('Load failed');
   const doc=new DOMParser().parseFromString(await response.text(),'text/html'),part=doc.querySelector('[data-punch-scroll]');
   if(!part)throw new Error('Session expired or access changed');
   for(const row of part.querySelectorAll('tbody > tr'))punchScroll.querySelector('tbody').append(document.importNode(row,true));
   punchScroll.dataset.next=part.dataset.next||'';button.hidden=!punchScroll.dataset.next;failed=false;
   status.textContent=punchScroll.dataset.next?'Scroll down to load more punches.':'All matching punches loaded.';
  }catch(error){failed=true;status.textContent='Could not load more punches. Check your connection or sign in again, then retry.';button.hidden=false;button.textContent='Retry loading';}
  finally{busy=false;button.disabled=false;}
 }
 punchScroll.addEventListener('scroll',()=>{if(!failed&&punchScroll.scrollTop+punchScroll.clientHeight>=punchScroll.scrollHeight-120)more();});
 button.addEventListener('click',more);
}
