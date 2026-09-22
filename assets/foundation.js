'use strict';
try { if (localStorage.getItem('peopleflow-theme') === 'dark') document.body.classList.add('dark'); } catch (_) {}
document.getElementById('theme-toggle')?.addEventListener('click', () => {const dark=document.body.classList.toggle('dark');try{localStorage.setItem('peopleflow-theme',dark?'dark':'light');}catch(_){} });
const menu=document.querySelector('.menu-toggle'),nav=document.getElementById('navigation');
menu?.addEventListener('click',()=>{const open=nav.classList.toggle('open');menu.setAttribute('aria-expanded',String(open));});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){nav?.classList.remove('open');menu?.setAttribute('aria-expanded','false');}});
document.addEventListener('click',e=>{if(nav?.classList.contains('open')&&!nav.contains(e.target)&&!menu?.contains(e.target)){nav.classList.remove('open');menu?.setAttribute('aria-expanded','false');}});
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
const department=document.querySelector('[name=department_id][data-dependent]'),designation=document.querySelector('[name=designation_id]');
if(department&&designation){const update=()=>{[...designation.options].forEach(o=>{const valid=!o.dataset.department||o.dataset.department===department.value;o.hidden=!valid;o.disabled=!valid;});if(designation.selectedOptions[0]?.disabled)designation.value='';};department.addEventListener('change',update);update();}

// Accessible native attendance punch dialog.
document.addEventListener('click', event => {
 const open = event.target.closest('[data-open-dialog]');
 if (open) document.getElementById(open.dataset.openDialog)?.showModal();
 const close = event.target.closest('[data-close-dialog]');
 if (close) close.closest('dialog')?.close();
});
