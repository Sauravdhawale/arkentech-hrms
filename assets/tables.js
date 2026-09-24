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
 bulk.querySelector('[data-bulk-review]').addEventListener('click',()=>{const n=selected().length;if(!n){alert('Select at least one employee.');return;}const deleting=bulk.elements.operation.value==='delete';const assignments=['bulk_department','bulk_designation','bulk_role'].map(k=>bulk.elements[k]).filter(s=>s.value).map(s=>s.selectedOptions[0].textContent);if(!deleting&&!assignments.length){alert('Choose an assignment first.');return;}bulk.querySelector('[data-bulk-summary]').textContent=deleting?'Delete '+n+' selected employees from the directory and disable their logins? Historical records will be retained.':'Apply '+assignments.join(', ')+' to '+n+' selected employees?';bulk.elements.confirmed.checked=false;dialog.showModal();});
 update();
}
