document.querySelectorAll("form[data-confirm]").forEach(f=>f.addEventListener("submit",e=>{if(!window.confirm(f.dataset.confirm))e.preventDefault()}));
document.querySelectorAll('.record-search').forEach(input=>input.addEventListener('input',()=>{const term=input.value.toLowerCase();input.closest('.panel').querySelectorAll('tbody tr').forEach(row=>{row.hidden=!row.textContent.toLowerCase().includes(term);});}));

