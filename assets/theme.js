/* Shared presentation only: no API requests, permission changes or data writes. */
(()=>{'use strict';
 const body=document.body,sidebar=document.querySelector('body>aside'),header=document.querySelector('.topbar,.main>header');
 const paths={
  menu:'M4 6h16M4 12h16M4 18h16',collapse:'m14 6-6 6 6 6',search:'M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
  home:'m3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9',people:'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 4a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-4M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  clock:'M12 8v5l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',calendar:'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2',
  file:'M14 2H5v20h14V7l-5-5v5h5M8 12h8M8 16h6',briefcase:'M8 7V4h8v3M3 7h18v14H3ZM3 12h18M10 12v3h4v-3',
  user:'M20 21v-2a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v2M16 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  settings:'m9 3-1 3-3 1-2 3 2 2-1 3 3 2 3-1 2 2 3-1 1-3 3-1 1-3-2-2V6l-3-1-2 1-2-3ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
  inbox:'M3 3h18v18H3ZM3 14h5l2 3h4l2-3h5',device:'M6 2h12v20H6ZM9 6h6M9 10h6M11 18h2',chart:'M4 20V10M10 20V4M16 20v-7M22 20H2',
  task:'M9 4H5v18h14V4h-4M9 2h6v4H9ZM8 14l3 3 5-6',help:'M9 9a3 3 0 1 1 5 2c-2 1-2 2-2 3M12 17h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
  bell:'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',moon:'M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z',sun:'M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0',logout:'M9 3H3v18h6M8 12h13m-4-4 4 4-4 4',close:'m6 6 12 12M6 18 18 6'
 };
 const icon=name=>{const span=document.createElement('span');span.className='ui-icon';span.setAttribute('aria-hidden','true');span.innerHTML='<svg viewBox="0 0 24 24"><path d="'+(paths[name]||paths.file)+'"/></svg>';return span;};
 const type=label=>/biometric|device|mapping|sync/i.test(label)?'device':/attendance|shift|punch|late|time/i.test(label)?'clock':/leave|holiday|calendar/i.test(label)?'calendar':/payroll|salary|payslip/i.test(label)?'file':/recruit|job|candidate|interview|offer/i.test(label)?'briefcase':/employee|people|team/i.test(label)?'people':/profile|account/i.test(label)?'user':/setting|department|designation|role/i.test(label)?'settings':/inbox|notification/i.test(label)?'inbox':/task/i.test(label)?'task':/help/i.test(label)?'help':/dashboard|overview/i.test(label)?'home':/report|performance/i.test(label)?'chart':'file';
 let dark=false;try{dark=(localStorage.getItem('shrms-theme')||localStorage.getItem('peopleflow-theme')||(localStorage.getItem('peopleflow-dark')==='true'?'dark':'light'))==='dark';}catch{}
 // Public login/recovery pages stay light; authenticated shells share a preference.
 if(sidebar)body.classList.toggle('dark',dark);
 const themeButtons=[...document.querySelectorAll('#theme,#theme-toggle,[data-ess-theme]')];
 function paintTheme(){themeButtons.forEach(b=>{const isDark=body.classList.contains('dark'),label=isDark?'Switch to light mode':'Switch to dark mode';b.setAttribute('aria-label',label);b.title=label;b.setAttribute('aria-pressed',String(isDark));if(!b.hasAttribute('data-ess-theme')){b.replaceChildren(icon(isDark?'sun':'moon'));if(b.id==='theme'){const s=document.createElement('span');s.className='ui-nav-label';s.textContent='Appearance';b.append(s);}}});}
 themeButtons.filter(b=>!b.hasAttribute('data-ess-theme')).forEach(b=>b.addEventListener('click',()=>{const value=body.classList.toggle('dark')?'dark':'light';try{localStorage.setItem('shrms-theme',value);localStorage.setItem('peopleflow-theme',value);localStorage.setItem('peopleflow-dark',String(value==='dark'));}catch{}paintTheme();}));paintTheme();
 if(sidebar&&header){
  body.classList.add('has-app-shell');sidebar.id=sidebar.id||'navigation';
  const entries=[];
  sidebar.querySelectorAll('nav a,nav summary,nav .nav-module-title,.sidebar-bottom>a,.logout-button,.aside-bottom .wide-button').forEach(el=>{
   const label=el.textContent.replace(/[▦♙⚙▤◇◎◉↗◷⌕⌄⌃↪]/g,'').replace(/\s+/g,' ').trim();
   const kind=/sign out/i.test(label)?'logout':type(label);el.title=label;el.setAttribute('aria-label',label);el.replaceChildren(icon(kind));const text=document.createElement('span');text.className='ui-nav-label';text.textContent=label;el.append(text);
   if(el.tagName==='A'){const group=el.closest('details,.nav-module');const groupLabel=group?.querySelector('summary,.nav-module-title')?.textContent.trim()||'';entries.push({label,href:el.getAttribute('href'),group:groupLabel,kind});}
  });
  const media=matchMedia('(max-width:780px)'),tablet=matchMedia('(max-width:1150px)');let preference=null;
  try{preference=localStorage.getItem('shrms-sidebar');}catch{}
  const triggers=[...header.querySelectorAll('#menu,.menu-toggle')];
  triggers.forEach(b=>{b.setAttribute('aria-controls',sidebar.id);b.replaceChildren(icon('menu'));});
  function refresh(){const collapsed=!media.matches&&(preference===null?tablet.matches:preference==='collapsed');body.classList.toggle('sidebar-collapsed',collapsed);if(!media.matches)body.classList.remove('nav-open');triggers.forEach(b=>{const expanded=media.matches?body.classList.contains('nav-open'):!collapsed;b.setAttribute('aria-expanded',String(expanded));b.setAttribute('aria-label',expanded?'Collapse navigation':'Expand navigation');b.title=expanded?'Collapse navigation':'Expand navigation';});sidebar.inert=media.matches&&!body.classList.contains('nav-open');}
  triggers.forEach(b=>b.addEventListener('click',()=>{if(media.matches)body.classList.toggle('nav-open');else{preference=body.classList.contains('sidebar-collapsed')?'expanded':'collapsed';try{localStorage.setItem('shrms-sidebar',preference);}catch{}}refresh();}));
  sidebar.addEventListener('click',e=>{const parent=e.target.closest('summary,.nav-module-title');if(parent&&body.classList.contains('sidebar-collapsed')){e.preventDefault();e.stopImmediatePropagation();preference='expanded';refresh();if(parent.tagName==='SUMMARY')parent.parentElement.open=true;else{parent.parentElement.classList.add('open');parent.setAttribute('aria-expanded','true');}}else if(parent?.classList.contains('nav-module-title')){e.preventDefault();e.stopImmediatePropagation();const open=parent.parentElement.classList.toggle('open');parent.setAttribute('aria-expanded',String(open));}},true);
  document.addEventListener('click',e=>{if(media.matches&&body.classList.contains('nav-open')&&!sidebar.contains(e.target)&&!triggers.some(t=>t.contains(e.target))){body.classList.remove('nav-open');refresh();}});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&body.classList.contains('nav-open')){body.classList.remove('nav-open');refresh();triggers[0]?.focus();}});
  // Keep keyboard focus in an open mobile drawer, and return focus on Escape.
  sidebar.addEventListener('keydown',e=>{if(e.key!=='Tab'||!media.matches||!body.classList.contains('nav-open'))return;const focus=[...sidebar.querySelectorAll('a,button,summary,input')].filter(x=>x.getClientRects().length&&!x.disabled);if(e.shiftKey&&document.activeElement===focus[0]){e.preventDefault();focus.at(-1)?.focus();}else if(!e.shiftKey&&document.activeElement===focus.at(-1)){e.preventDefault();focus[0]?.focus();}});
  media.addEventListener('change',refresh);tablet.addEventListener('change',refresh);refresh();
  // Search only the links already rendered for this user's permissions.
  const search=document.createElement('button');search.type='button';search.className='ui-search-trigger';search.append(icon('search'));const label=document.createElement('span');label.className='ui-search-label';label.textContent='Search menu…';const key=document.createElement('kbd');key.textContent='Ctrl K';search.append(label,key);search.setAttribute('aria-label','Search available pages');search.setAttribute('aria-haspopup','dialog');header.insertBefore(search,header.children[1]||null);
  const dialog=document.createElement('dialog');dialog.className='ui-search-dialog';dialog.setAttribute('aria-labelledby','ui-search-title');dialog.innerHTML='<div class="ui-search-top"><h2 id="ui-search-title">Find a page</h2><button type="button" class="icon-button" aria-label="Close search"></button></div><label for="ui-menu-query">Search your available pages</label><input id="ui-menu-query" type="search" autocomplete="off" placeholder="Attendance, leave, settings…"><div class="ui-search-results"></div>';
  const close=dialog.querySelector('button');close.append(icon('close'));close.addEventListener('click',()=>dialog.close());body.append(dialog);const input=dialog.querySelector('input'),results=dialog.querySelector('.ui-search-results');
  function render(){results.replaceChildren();const term=input.value.trim().toLowerCase();for(const entry of entries.filter(x=>(x.label+' '+x.group).toLowerCase().includes(term))){const a=document.createElement('a');a.href=entry.href;a.append(icon(entry.kind));const name=document.createElement('span');name.textContent=entry.label;a.append(name);if(entry.group){const small=document.createElement('small');small.textContent=entry.group;a.append(small);}results.append(a);}if(!results.children.length){const p=document.createElement('p');p.textContent='No matching pages.';results.append(p);}}
  function openSearch(){render();dialog.showModal();input.focus();}search.addEventListener('click',openSearch);input.addEventListener('input',render);document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();if(!dialog.open)openSearch();}});
 }
 // Apply digital typography only to clock/duration columns, never identifiers or dates.
 document.querySelectorAll('table').forEach(table=>{const cols=[...table.querySelectorAll('thead th')].map(h=>h.textContent.trim().toLowerCase());const indices=cols.flatMap((s,i)=>/^(punch time|check[- ]?in|check[- ]?out|worked|worked hours|working hours|break time|overtime|late|early|scheduled start|scheduled end)$/.test(s)?[i]:[]);const mark=()=>table.querySelectorAll('tbody tr').forEach(tr=>{indices.forEach(i=>{const cell=tr.cells[i];if(cell&&cell.colSpan===1)cell.classList.add('ui-digital');});if(tr.cells.length===1&&/^no (matching|records|published|requests)/i.test(tr.textContent.trim()))tr.cells[0].classList.add('ui-empty-cell');});mark();const tbody=table.tBodies[0];if(tbody)new MutationObserver(mark).observe(tbody,{childList:true});});
 const states={active:'success',present:'success',approved:'success',completed:'success',finalized:'success',paid:'success',processed:'success',pending:'warning',open:'warning','in progress':'warning',unmapped:'warning',inactive:'error',absent:'error',rejected:'error',failed:'error'};
 document.querySelectorAll('.badge,.status-badge').forEach(b=>{const state=states[b.textContent.trim().toLowerCase()];if(state)b.dataset.uiStatus=state;});
})();
