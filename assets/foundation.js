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

