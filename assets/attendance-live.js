'use strict';
(() => {
 const root=document.querySelector('[data-attendance-live]');if(!root)return;
 const body=root.querySelector('[data-live-rows]'),updated=root.querySelector('[data-live-updated]');
 const dialog=document.getElementById('admin-live-punch'),form=dialog?.querySelector('form');
 const filters=root.querySelector('[data-live-filters]'),refresh=root.querySelector('[data-live-refresh]');
 let data=JSON.parse(document.getElementById('attendance-live-data').textContent),busy=false,dirty=false,stale=false;
 const duration=seconds=>{const n=Math.max(0,Math.floor(Number(seconds||0)/60));return `${Math.floor(n/60)}h ${String(n%60).padStart(2,'0')}m`;};
 const time=stamp=>{
  if(!stamp)return '—';
  // Punch timestamps are company wall time, not browser/UTC time.
  const match=String(stamp).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
  if(!match)return stamp;
  const hour=Number(match[4]);return `${match[3]}/${match[2]} · ${String(hour%12||12).padStart(2,'0')}:${match[5]}:${match[6]||'00'} ${hour>=12?'PM':'AM'}`;
 };
 function node(tag,text,cls){const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;}
 function cell(row,text,detail){const td=node('td',text);if(detail)td.append(node('small',detail));row.append(td);return td;}
 function render(next){
  if(!Array.isArray(next.people)||!next.as_of)throw new Error('Invalid live response');
  data=next;stale=false;const scroll=root.querySelector('.att-live-scroll');const x=scroll.scrollLeft,y=scroll.scrollTop;
  const counts={in:0,out:0,none:0,review:0},rows=document.createDocumentFragment();
  for(const person of next.people){
   const s=person.presence,t=s.timing||{},tr=node('tr');counts[s.state]=(counts[s.state]||0)+1;
   cell(tr,person.name,`${person.employee_code||'No employee ID'} · ${person.department||'No department'}`);
   const status=cell(tr,'');status.append(node('span',s.label,`att-live-badge ${s.state}`));status.append(node('small',s.inferred?'Inferred from punch sequence':s.detail));
   const shift=cell(tr,s.attendance_date||'No current shift',t.shift_label||'Review the employee shift assignment');
   if(s.attendance_date){const link=node('a','View daily record');link.href=`?page=attendance&date=${encodeURIComponent(s.attendance_date)}&employee_id=${Number(person.user_id)}`;shift.append(link);}
   cell(tr,time(s.last_punch_at));cell(tr,duration(t.worked));cell(tr,duration(t.break));
   if(next.can_manage){const action=cell(tr,'');
    if(s.attendance_date&&!t.expired&&['in','out','none'].includes(s.state)){
     const label=s.state==='in'?'Check Out':'Check In';const b=node('button',label,'button '+(s.state==='in'?'':'primary'));b.type='button';b.dataset.liveEmployee=person.user_id;b.dataset.liveAction=label;b.setAttribute('aria-label',`${label} ${person.name}`);action.append(b);
    }else{const a=node('a','Review / correct','button');a.href='?page=manual_attendance';action.append(a);}
   }
   rows.append(tr);
  }
  if(!next.people.length){const tr=node('tr'),td=node('td','No active employees match these filters.');td.colSpan=next.can_manage?7:6;tr.append(td);rows.append(tr);}
  body.replaceChildren(rows);scroll.scrollLeft=x;scroll.scrollTop=y;
  const cards=document.createDocumentFragment();for(const [key,label] of Object.entries({in:'Checked in',out:'Checked out',none:'Not checked in',review:'Needs review'})){const card=node('div',undefined,'card att-live-count');card.append(node('span',label),node('strong',counts[key]));cards.append(card);}root.querySelector('[data-live-counts]').replaceChildren(cards);
  root.querySelector('[data-live-limit]').hidden=!next.limited;
  updated.textContent=`Updated ${new Intl.DateTimeFormat('en-IN',{timeZone:next.timezone,hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true}).format(new Date(next.as_of))} · refreshes every 5 seconds`;
  updated.classList.remove('is-stale');
 }
 const blocked=()=>document.hidden||dirty||!!document.querySelector('dialog[open]');
 async function load(){
  if(busy||blocked())return;busy=true;refresh.disabled=true;
  const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),12000);
  try{
   const url=new URL(location.href);url.searchParams.set('live_status','1');
   const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
   if(!response.ok||!response.headers.get('content-type')?.includes('application/json'))throw new Error('Unavailable');
   const next=await response.json();if(!blocked())render(next);
  }catch(error){stale=true;updated.textContent='Live update unavailable. Last shown values may be stale. Refresh or sign in again.';updated.classList.add('is-stale');root.querySelectorAll('[data-live-employee]').forEach(b=>b.disabled=true);}
  finally{clearTimeout(timeout);busy=false;refresh.disabled=false;}
 }
 root.addEventListener('click',event=>{
  const button=event.target.closest('[data-live-employee]');if(!button||!form||stale)return;
  const person=data.people.find(p=>String(p.user_id)===button.dataset.liveEmployee);if(!person)return;
  form.elements.employee_id.value=person.user_id;form.elements.punch_action.value=button.dataset.liveAction;form.elements.presence_revision.value=person.revision;form.elements.notes.value='';
  dialog.querySelector('[data-live-person]').textContent=`${person.name} · ${person.employee_code||''} · Shift date ${person.presence.attendance_date}`;
  dialog.querySelector('#admin-live-title').textContent=`Admin ${button.dataset.liveAction}`;form.querySelector('[data-live-save]').textContent=`Confirm ${button.dataset.liveAction}`;
  dialog.showModal();form.elements.notes.focus();
 });
 filters.addEventListener('input',()=>{dirty=true;updated.textContent='Apply filters to resume live updates.';});
 filters.addEventListener('change',()=>{dirty=true;updated.textContent='Apply filters to resume live updates.';});
 refresh.addEventListener('click',()=>{if(dirty){filters.requestSubmit();return;}load();});
 dialog?.addEventListener('close',()=>load());
 form?.addEventListener('submit',()=>{form.querySelector('[data-live-save]').disabled=true;});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
 render(data);setInterval(load,5000);
})();
