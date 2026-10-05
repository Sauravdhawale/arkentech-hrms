'use strict';
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
const department=document.querySelector('[name=department_id][data-dependent]'),designation=document.querySelector('[name=designation_id]');
if(department&&designation){const update=()=>{[...designation.options].forEach(o=>{const valid=o.value===''||(department.value!==''&&o.dataset.department===department.value)||(o.hasAttribute('data-legacy-department')&&o.dataset.legacyDepartment===department.value);o.hidden=!valid;o.disabled=!valid;});if(designation.selectedOptions[0]?.disabled)designation.value='';};department.addEventListener('change',update);update();}

// Accessible native attendance punch dialog.
document.addEventListener('click', event => {
 const open = event.target.closest('[data-open-dialog]');
 if (open) document.getElementById(open.dataset.openDialog)?.showModal();
 const close = event.target.closest('[data-close-dialog]');
 if (close) close.closest('dialog')?.close();
});

// Reopen allocation edits and validation failures as modal forms.
document.querySelectorAll('dialog[data-auto-open]').forEach(dialog => dialog.showModal());

const notificationRoot=document.querySelector('.admin-notifications');
if(notificationRoot){
 const toggle=notificationRoot.querySelector('.notification-toggle'),panel=notificationRoot.querySelector('.notification-panel');
 const closeNotifications=()=>{panel.hidden=true;toggle.setAttribute('aria-expanded','false');};
 toggle.addEventListener('click',()=>{const opening=panel.hidden;panel.hidden=!opening;toggle.setAttribute('aria-expanded',String(opening));});
 document.addEventListener('click',event=>{if(!notificationRoot.contains(event.target))closeNotifications();});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.hidden){closeNotifications();toggle.focus();}});
 notificationRoot.addEventListener('focusout',event=>{if(event.relatedTarget&&!notificationRoot.contains(event.relatedTarget))closeNotifications();});
}



// Convert inline Add/Create/Edit admin forms into compact buttons + native modal dialogs.
// This intentionally targets form editors only; read-only details, filters and tables stay inline.
(function upgradeAdminFormModals(){
 const eligible=/^(?:Add|Create|Edit|Assign|Correct)\b/i;
 const sourceLabel=source=>{
  if(source.matches('details'))return source.querySelector(':scope > summary')?.textContent?.trim()||'';
  return source.querySelector(':scope > .card-heading h2, :scope > h2, :scope > h3')?.textContent?.trim()||'';
 };
 const candidates=[];
 document.querySelectorAll('details.card,details.core-editor,details.pay-card').forEach(source=>{
  if(source.closest('dialog')||!source.querySelector('form'))return;
  const label=sourceLabel(source);if(eligible.test(label))candidates.push(source);
 });
 document.querySelectorAll('section.card').forEach(source=>{
  if(source.closest('dialog')||!source.querySelector('form'))return;
  const label=sourceLabel(source);if(eligible.test(label))candidates.push(source);
 });
 [...new Set(candidates)].forEach((source,index)=>{
  if(source.dataset.modalized==='1'||!source.isConnected)return;
  const label=sourceLabel(source);if(!label)return;
  const wasOpen=source.matches('details')&&source.open;
  source.dataset.modalized='1';
  if(source.matches('details'))source.open=true;
  source.classList.add('admin-form-source');

  const launch=document.createElement('div');launch.className='form-launch';
  const open=document.createElement('button');open.type='button';open.className='button primary admin-form-launch';
  open.textContent=(/^(?:Add|Create)\b/i.test(label)?'+ ':'')+label;
  launch.appendChild(open);
  source.parentNode.insertBefore(launch,source);

  const dialog=document.createElement('dialog');
  dialog.className='attendance-dialog admin-form-dialog'+(source.querySelector('.permissions')?' wide':'');
  dialog.id='admin-form-dialog-'+index;
  dialog.setAttribute('aria-labelledby',dialog.id+'-title');

  const header=document.createElement('div');header.className='card-heading admin-form-modal-heading';
  const title=document.createElement('h2');title.id=dialog.id+'-title';title.textContent=label;
  const close=document.createElement('button');close.type='button';close.className='button small';close.textContent='Close';close.setAttribute('aria-label','Close '+label);
  header.append(title,close);
  dialog.appendChild(header);
  source.parentNode.insertBefore(dialog,source);
  dialog.appendChild(source);

  open.addEventListener('click',()=>dialog.showModal());
  close.addEventListener('click',()=>dialog.close());
  dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});

  const editMode=/^(?:Edit|Correct)\b/i.test(label)||new URLSearchParams(location.search).has('edit')||new URLSearchParams(location.search).has('revision');
  const validationError=!!document.querySelector('.alert.error');
  if(wasOpen||editMode||validationError)requestAnimationFrame(()=>{if(!dialog.open)dialog.showModal();});
 });
})();
