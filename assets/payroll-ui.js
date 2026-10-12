(() => {
 document.querySelectorAll('[data-pay-list]').forEach(list=>{
  const rows=[...list.querySelectorAll('[data-pay-row]')], search=list.querySelector('[data-pay-search]');let filter='';
  const update=()=>{const query=(search?.value||'').trim().toLowerCase();let count=0;rows.forEach(row=>{const show=(!filter||row.dataset.payCategory===filter)&&row.textContent.toLowerCase().includes(query);row.hidden=!show;if(show)count++;});const empty=list.querySelector('[data-pay-empty]');if(empty)empty.hidden=count>0;const result=list.querySelector('[data-pay-result]');if(result)result.textContent=count+' of '+rows.length+' records';};
  search?.addEventListener('input',update);list.querySelectorAll('[data-pay-filter]').forEach(button=>button.addEventListener('click',()=>{filter=button.dataset.payFilter;list.querySelectorAll('[data-pay-filter]').forEach(b=>{b.classList.toggle('is-active',b===button);b.setAttribute('aria-pressed',String(b===button));});update();}));
 });
})();
