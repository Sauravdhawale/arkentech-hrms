document.addEventListener('click',event=>{const open=event.target.closest('[data-pay-detail]');if(open){const dialog=document.getElementById(open.dataset.payDetail);if(dialog){const frame=dialog.querySelector('iframe[data-src]');if(frame&&!frame.src)frame.src=frame.dataset.src;dialog.showModal();}}const close=event.target.closest('[data-pay-close]');if(close)close.closest('dialog').close();});

// Selection is scoped to the currently displayed rows and resets on filtering.
const selectedForm=document.getElementById('pay-selected-form');
if(selectedForm){
 const choices=[...selectedForm.querySelectorAll('[data-pay-select]')];
 const all=selectedForm.querySelector('[data-pay-select-all]');
 const review=selectedForm.querySelector('[data-pay-review-selected]');
 const dialog=selectedForm.querySelector('#pay-publish-dialog');
 const chosen=()=>choices.filter(input=>input.checked);
 const update=()=>{const n=chosen().length;selectedForm.querySelector('[data-pay-selected-count]').textContent=n+' selected';review.disabled=n===0;if(all){all.disabled=choices.length===0;all.checked=n>0&&n===choices.length;all.indeterminate=n>0&&n<choices.length;}};
 choices.forEach(input=>input.addEventListener('change',update));
 if(all)all.addEventListener('change',()=>{choices.forEach(input=>input.checked=all.checked);update();});
 review.addEventListener('click',()=>{
  const rows=chosen();if(!rows.length)return;
  const body=dialog.querySelector('[data-pay-publish-preview]');body.replaceChildren();
  rows.forEach(input=>{const tr=document.createElement('tr');['employee','gross','deductions','net'].forEach(key=>{const td=document.createElement('td');td.textContent=input.dataset[key];tr.append(td);});body.append(tr);});
  dialog.querySelector('[name="confirm"]').checked=false;
  dialog.querySelector('[data-pay-publish-submit]').textContent='Publish '+rows.length+' payslip'+(rows.length===1?'':'s');
  dialog.showModal();
 });
 selectedForm.addEventListener('submit',event=>{if(!dialog.open||!chosen().length){event.preventDefault();return;}dialog.querySelector('[data-pay-publish-submit]').disabled=true;});
 selectedForm.addEventListener('invalid',()=>{if(chosen().length&&!dialog.open)dialog.showModal();},true);
 update();
}

document.querySelectorAll('[data-settings-tab]').forEach(grid=>{
 const attendance=['proration','lop_basis','lop_days','ot_enabled','ot_method','ot_rate','ot_basis','hours_per_day'];
 // Keep inputs enabled so saving either tab preserves all the existing settings.
 grid.querySelectorAll('label').forEach(label=>{
  const input=label.querySelector('input,select');
  if(input)label.hidden=(grid.dataset.settingsTab==='attendance')!==attendance.includes(input.name);
 });
});


// Reveal a collapsed setting if browser validation needs the user to correct it.
document.addEventListener('invalid',event=>{
 const field=event.target;
 if(!field.closest('[data-inline-editor]'))return;
 const label=field.closest('label');
 if(label)label.hidden=false;
 for(let parent=field.parentElement;parent;parent=parent.parentElement){
  if(parent.matches('details'))parent.open=true;
  if(parent.matches('[data-pay-settings-section]'))parent.hidden=false;
 }
},true);

