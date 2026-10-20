/* Presentation only. Shift assignment history and calculation rules stay on the server. */
(()=>{'use strict';
 const minutes=value=>{const m=String(value).trim().match(/^(0?[1-9]|1[0-2]):([0-5]\d)\s+([ap]m)$/i);return m?((+m[1]%12)+(m[3].toLowerCase()==='pm'?12:0))*60+(+m[2]):null;};
 const duration=value=>`${Math.floor(value/60)}h ${String(value%60).padStart(2,'0')}m`;
 document.querySelectorAll('[data-shift-editor]').forEach(form=>{
  const output=form.querySelector('[data-shift-preview]');if(!output)return;
  const get=name=>form.elements.namedItem(name)?.value??'';
  function preview(){
   const start=minutes(get('start')),end=minutes(get('end'));
   output.classList.remove('needs-review');
   if(start===null||end===null){output.textContent='Enter start and end times with AM or PM, for example 09:00 AM and 06:00 PM.';return;}
   if(start===end){output.textContent='Start and end times must be different.';output.classList.add('needs-review');return;}
   const span=(end-start+1440)%1440,rawBreak=Number(get('break_hours'))*60+Number(get('break_remainder'));
   const breakMinutes=Number.isFinite(rawBreak)?Math.max(0,Math.round(rawBreak)):0,available=Math.max(0,span-breakMinutes);
   output.replaceChildren();const title=document.createElement('strong');title.textContent=`${get('start').trim().toUpperCase()} → ${get('end').trim().toUpperCase()} · ${end<start?'Ends next day':'Ends the same day'}`;
   const detail=document.createElement('span');detail.textContent=`${duration(span)} scheduled − ${duration(breakMinutes)} break = ${duration(available)} after break.`;output.append(title,detail);
   const warnings=[];
   if(span>960)warnings.push('This shift is longer than 16 hours. Check the end time and AM/PM.');
   if(breakMinutes>=span)warnings.push('Break must be shorter than the shift.');
   if(Number(get('required_hours'))*60>available)warnings.push('Required working hours exceed the time available after the break. Check these values.');
   if(warnings.length){const note=document.createElement('span');note.textContent=warnings.join(' ');output.append(note);output.classList.add('needs-review');}
  }
  form.addEventListener('input',preview);form.addEventListener('change',preview);preview();
 });
})();
