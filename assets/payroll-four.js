document.addEventListener('click',event=>{const open=event.target.closest('[data-pay-detail]');if(open){const dialog=document.getElementById(open.dataset.payDetail);if(dialog){const frame=dialog.querySelector('iframe[data-src]');if(frame&&!frame.src)frame.src=frame.dataset.src;dialog.showModal();}}const close=event.target.closest('[data-pay-close]');if(close)close.closest('dialog').close();});

document.querySelectorAll('[data-settings-tab]').forEach(grid=>{
 const attendance=['proration','lop_basis','lop_days','ot_enabled','ot_method','ot_rate','ot_basis','hours_per_day'];
 grid.querySelectorAll('label').forEach(label=>{
  const input=label.querySelector('input,select');
  if(input)label.hidden=(grid.dataset.settingsTab==='attendance')!==attendance.includes(input.name);
 });
});
