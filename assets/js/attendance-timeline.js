(()=>{'use strict';
 document.querySelectorAll('[data-attendance-timeline]').forEach(form=>{
  const cfg=JSON.parse(form.querySelector('[data-timeline-config]').textContent), clone=a=>a.map(e=>({at:Number(e.at),state:e.state}));
  let events=clone(cfg.events),dirty=!!cfg.retry,drag=null,original=clone(cfg.original),stale=false,offline=false,busy=false,receivedAt=performance.now();const track=form.querySelector('[data-tl-track]'),rows=form.querySelector('[data-tl-boundaries]'),message=form.querySelector('[data-tl-message]'),save=form.querySelector('[data-tl-save]');
  let min,max,view='recorded';
  const liveStatus=form.querySelector('[data-tl-live-status]'),refresh=form.querySelector('[data-tl-refresh]');
  const now=()=>cfg.cutoff+(cfg.live&&!dirty&&!offline?Math.max(0,Math.floor((performance.now()-receivedAt)/1000)):0);
  const local=at=>new Date((at+cfg.offset)*1000).toISOString().slice(0,19);
  const parse=value=>Date.parse(value+'Z')/1000-cfg.offset;
  const label=at=>new Date((at+cfg.offset)*1000).toLocaleString('en-GB',{timeZone:'UTC',day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',hour12:true});
  const clock=at=>new Date((at+cfg.offset)*1000).toLocaleString('en-GB',{timeZone:'UTC',hour:'2-digit',minute:'2-digit',hour12:true});
  const exact=at=>new Date((at+cfg.offset)*1000).toLocaleString('en-GB',{timeZone:'UTC',day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
  const duration=s=>{s=Math.max(0,Math.round(s));return `${Math.floor(s/3600)}h ${String(Math.floor(s%3600/60)).padStart(2,'0')}m`;};
  const endFor=(list,i)=>Math.min(now(),list[i+1]?.at??(list[i].state==='in'?now():(cfg.live?Math.max(list[i].at,Math.min(now(),cfg.end)):list[i].at)));
  const totals=list=>list.reduce((v,e,i)=>{const seconds=e.state==='in'?Math.max(0,endFor(list,i)-e.at):Math.max(0,Math.min(endFor(list,i),cfg.end)-Math.max(e.at,cfg.start));v[e.state==='in'?'work':'break']+=seconds;return v;},{work:0,break:0});
  const invalid=()=>{if(stale)return 'New punches or corrections arrived. Reload live data before saving; your draft is preserved until you reload.';if(!events.length)return cfg.start>cfg.cutoff?'This shift has not started. Choose Current shift · Live.':'No recorded check-in for this shift. Add a time marker only to correct a missing punch.';if(events.length>200)return 'Use no more than 200 time markers.';if(events[0].state!=='in')return 'The first marker must start working time.';for(let i=0;i<events.length;i++){const e=events[i];if(!Number.isFinite(e.at)||!['in','out'].includes(e.state))return 'Enter a valid time and activity.';if(e.at>cfg.cutoff)return 'Times cannot be later than this editing snapshot. Refresh live data to include newer times.';if(i&&e.at<=events[i-1].at)return 'Keep times in order; move or remove the neighbouring marker first.';}if(events.at(-1).at-events[0].at>129600)return 'Keep the timeline within 36 hours.';if(events.at(-1).state==='in'&&cfg.cutoff-events[0].at>129600)return 'Add the missing final check-out for this older shift before saving.';return '';};
  function node(tag,props={},text=''){const n=document.createElement(tag);Object.assign(n,props);if(text)n.textContent=text;return n;}
  const toolbar=node('div',{className:'atl-chart-toolbar'}),viewButtons=node('div',{className:'atl-view-buttons'}),ruler=node('div',{className:'atl-ruler'}),punchTimes=node('div',{className:'atl-punch-times'});
  const chartHeading=node('div',{});chartHeading.append(node('h3',{},'Attendance timeline'),node('p',{},'Work and breaks · times in your shift timezone'));
  viewButtons.setAttribute('role','group');viewButtons.setAttribute('aria-label','Timeline range');
  for(const [value,title] of [['recorded','Recorded time'],['shift','Full shift']]){const b=node('button',{type:'button',className:'button'},title);b.dataset.view=value;b.addEventListener('click',()=>{view=value;render(false);});viewButtons.append(b);}
  toolbar.append(chartHeading,viewButtons);track.before(toolbar);track.after(ruler);const scale=form.querySelector('.atl-scale');(scale||ruler).after(punchTimes);punchTimes.setAttribute('aria-label','Exact punch times');
  const periods=node('section',{className:'atl-periods'}),periodList=node('div',{className:'atl-period-list'});
  periods.setAttribute('aria-label','Work and break periods');
  periods.append(node('h3',{},'Work and break periods'),node('p',{},'Times use the shift timezone. Remove break counts that period as work; remove work excludes it from worked time. Review your changes, enter a reason and save.'),periodList);rows.before(periods);
  function removePeriod(i){
   const next=clone(events);next[i].state=next[i].state==='out'?'in':'out';
   // Collapse only the corrected draft. Never delete or rewrite original device punches.
   const merged=next.filter((e,j)=>!j||e.state!==next[j-1].state);
   while(merged[0]?.state==='out')merged.shift();
   if(!merged.length){message.textContent='Keep at least one working period. To remove all work for the day, use the attendance sheet to mark the day absent.';message.dataset.error='true';return;}
   events=merged;changed();periodList.querySelector('button')?.focus();
  }
  function renderPeriods(rebuild){
   if(rebuild){periodList.replaceChildren();events.forEach((e,i)=>{
    if(!Number.isFinite(e.at))return;
    const item=node('div',{className:'atl-period'});item.dataset.period=String(i);item.dataset.state=e.state;
    const info=node('div',{className:'atl-period-info'});info.append(node('strong',{},`${i+1}. ${e.state==='in'?'Working':'Break'}`),node('span',{className:'atl-period-time'}));item.append(info,node('span',{className:'atl-period-duration'}));
    if(cfg.editable){const action=node('button',{type:'button',className:'button atl-period-remove'},e.state==='in'?'Remove work':'Remove break');action.setAttribute('aria-label',`${action.textContent} period ${i+1}`);action.addEventListener('click',()=>removePeriod(i));item.append(action);}periodList.append(item);
   });}
   periodList.querySelectorAll('[data-period]').forEach(item=>{
    const i=Number(item.dataset.period),e=events[i],end=endFor(events,i);item.hidden=!(end>e.at);
    const ongoing=i===events.length-1&&cfg.live;
    item.querySelector('.atl-period-time').textContent=`${exact(e.at)} → ${exact(end)}${ongoing?(dirty?' · editing snapshot':' · ongoing'):''}`;
    item.querySelector('.atl-period-duration').textContent=duration(end-e.at);
   });
  }
  function pause(){dirty=true;}
  function changed(){pause();render();}
  function setTime(i,at){const lower=i?events[i-1].at+1:min,upper=Math.min(cfg.cutoff,i+1<events.length?events[i+1].at-1:max);events[i].at=Math.max(lower,Math.min(upper,at));dirty=true;render();}
  function render(rebuild=true,redraw=true){
   if(!drag){min=Math.min(cfg.start,...original.map(e=>e.at),...events.filter(e=>Number.isFinite(e.at)).map(e=>e.at))-1800;max=Math.max(cfg.end,...original.map(e=>e.at),...events.filter(e=>Number.isFinite(e.at)).map(e=>e.at),cfg.live?now():cfg.end)+1800;
   const validEvents=events.filter(e=>Number.isFinite(e.at));
   if(view==='recorded'&&validEvents.length){min=Math.min(...validEvents.map(e=>e.at))-900;max=Math.max(min+3600,...validEvents.map(e=>e.at),...events.map((e,i)=>Number.isFinite(e.at)?endFor(events,i):min))+900;}
   }
   viewButtons.querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.view===view)));
   const err=invalid(),v=totals(events),before=totals(original),difference=v.work-before.work;
   form.querySelector('[name=timeline_events]').value=JSON.stringify(events);
   form.querySelector('[data-tl-work]').textContent=duration(v.work);form.querySelector('[data-tl-break]').textContent=duration(v.break);form.querySelector('[data-tl-difference]').textContent=(difference<0?'−':difference>0?'+':'')+duration(Math.abs(difference));
   message.textContent=err||(events.at(-1)?.state==='in'?'Working until a recorded check-out. Shift end does not create a check-out.':'Checked out. Work is paused; break time counts within the scheduled shift.');message.dataset.error=String(!!err);if(save)save.disabled=!!err||!dirty;
   liveStatus.textContent=stale?'New data available · reload before saving':offline?'Connection interrupted · showing last received data':dirty?`Editing snapshot · ${label(cfg.cutoff)} · live updates paused`:cfg.live?`Live · ${events.at(-1)?.state==='in'?'Checked in':events.length?'Checked out':'No check-in'} · as of ${label(cfg.cutoff)} · refreshes every 10 seconds`:'Historical / scheduled shift · select Current shift for live data';
   refresh.textContent=dirty?'Discard edits & reload live data':'Refresh live data';
   form.querySelector('[name=timeline_cutoff]').value=cfg.cutoff;
   form.querySelector('[name=timeline_revision]').value=cfg.revision;
   form.querySelector('[data-tl-start]').textContent=label(min);form.querySelector('[data-tl-end]').textContent=label(max);if(redraw){track.replaceChildren();
   const width=track.clientWidth||800;
   events.forEach((e,i)=>{if(!Number.isFinite(e.at))return;const a=Math.max(min,e.at),b=Math.min(max,endFor(events,i));if(b>a){const segment=node('div',{className:'atl-segment'});segment.dataset.state=e.state;segment.style.left=`${(a-min)/(max-min)*100}%`;segment.style.width=`${(b-a)/(max-min)*100}%`;const pixels=(b-a)/(max-min)*width;if(pixels>56)segment.append(node('strong',{},pixels>110?`${i+1}. ${e.state==='in'?'Working':'Break'}`:String(i+1)));if(pixels>185){segment.append(node('span',{},`${clock(e.at)} – ${clock(endFor(events,i))}`),node('small',{},duration(b-a)));}segment.title=`${e.state==='in'?'Working':'Break'}: ${exact(e.at)} – ${exact(endFor(events,i))}`;track.append(segment);}
    const handle=node('button',{type:'button',className:'atl-handle',disabled:!cfg.editable});handle.style.left=`${Math.max(0,Math.min(100,(e.at-min)/(max-min)*100))}%`;handle.setAttribute('role','slider');handle.setAttribute('aria-label',`Time marker ${i+1}`);handle.setAttribute('aria-valuemin',String(i?events[i-1].at+1:min));handle.setAttribute('aria-valuemax',String(Math.min(cfg.cutoff,i+1<events.length?events[i+1].at-1:max)));handle.setAttribute('aria-valuenow',String(e.at));handle.setAttribute('aria-valuetext',label(e.at));handle.title=label(e.at);
    handle.addEventListener('pointerdown',ev=>{if(!cfg.editable)return;ev.preventDefault();pause();drag={i,id:ev.pointerId};track.setPointerCapture(ev.pointerId);});
    handle.addEventListener('keydown',ev=>{if(['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(ev.key)){ev.preventDefault();setTime(i,e.at+(['ArrowLeft','ArrowDown'].includes(ev.key)?-1:1)*(ev.shiftKey?300:60));track.querySelectorAll('.atl-handle')[i]?.focus();}});track.append(handle);
   });
   if(cfg.live){const marker=node('div',{className:'atl-now'}),position=Math.max(0,Math.min(100,(now()-min)/(max-min)*100));marker.style.left=`${position}%`;marker.dataset.align=position<30?'start':'end';marker.append(node('span',{},`${dirty?'Snapshot':offline?'Last received':'Now'} ${clock(now())}`));track.append(marker);}
   ruler.replaceChildren();const desired=(max-min)/Math.max(2,Math.floor(width/100)),step=[1800,3600,7200,14400,21600,43200,86400].find(s=>s>=desired)||86400;
   for(let at=Math.ceil((min+cfg.offset)/step)*step-cfg.offset;at<max;at+=step){const position=(at-min)/(max-min)*100;if(position<7||position>93)continue;const tick=node('span',{},clock(at));tick.style.left=`${position}%`;ruler.append(tick);}
   }
   if(rebuild){punchTimes.replaceChildren();events.forEach((e,i)=>{if(!Number.isFinite(e.at))return;const chip=node('div',{className:'atl-punch-chip'});chip.dataset.state=e.state;chip.append(node('span',{className:'atl-punch-number'},String(i+1)),node('strong',{},e.state==='in'?'IN':'OUT'),node('span',{},exact(e.at)));punchTimes.append(chip);});}
   renderPeriods(rebuild);
   if(!rebuild){if(!dirty)rows.querySelectorAll('input[type="datetime-local"]').forEach(input=>input.max=local(cfg.cutoff));return;}rows.replaceChildren();events.forEach((e,i)=>{
    const row=node('div',{className:'atl-boundary'});row.append(node('span',{className:'atl-number'},String(i+1)));
    const timeLabel=node('label',{},'Time'),input=node('input',{type:'datetime-local',step:'1',value:Number.isFinite(e.at)?local(e.at):'',disabled:!cfg.editable});input.setAttribute('aria-label',`Marker ${i+1} time`);input.max=local(cfg.cutoff);input.addEventListener('change',()=>{events[i].at=parse(input.value);changed();});timeLabel.append(input);row.append(timeLabel);
    const stateLabel=node('label',{},'Activity from this time'),state=node('select',{disabled:!cfg.editable});state.setAttribute('aria-label',`Marker ${i+1} activity`);state.append(node('option',{value:'in'},'Working'),node('option',{value:'out'},'Break / check out'));state.value=e.state;state.addEventListener('change',()=>{events[i].state=state.value;changed();});stateLabel.append(state);row.append(stateLabel);
    if(cfg.editable){const nudges=node('div',{className:'atl-nudge'});for(const delta of [-60,60]){const b=node('button',{type:'button',className:'button'},delta<0?'−1 min':'+1 min');b.setAttribute('aria-label',`Move marker ${i+1} ${delta<0?'earlier':'later'} by one minute`);b.addEventListener('click',()=>setTime(i,e.at+delta));nudges.append(b);}row.append(nudges);const remove=node('button',{type:'button',className:'button atl-remove'},'Remove');remove.setAttribute('aria-label',`Remove marker ${i+1}`);remove.addEventListener('click',()=>{events.splice(i,1);changed();});row.append(remove);}rows.append(row);
   });
  }
  track.addEventListener('pointermove',ev=>{if(!drag)return;const rect=track.getBoundingClientRect();const at=Math.round((min+(ev.clientX-rect.left)/rect.width*(max-min))/60)*60;setTime(drag.i,at);});
  const endDrag=()=>{if(drag){drag=null;render();}};track.addEventListener('pointerup',endDrag);track.addEventListener('pointercancel',endDrag);
  form.querySelector('[data-tl-add]')?.addEventListener('click',()=>{
   if(!events.length){events.push({at:Math.min(cfg.start,cfg.cutoff),state:'in'});changed();return;}
   let best=null;events.forEach((e,i)=>{const end=events[i+1]?.at??Math.min(cfg.end,cfg.cutoff);if(end-e.at>=120&&(!best||end-e.at>best.length))best={i,length:end-e.at,at:Math.floor((e.at+end)/2),state:e.state==='in'?'out':'in'};});
   if(best){events.splice(best.i+1,0,{at:best.at,state:best.state});changed();}else{message.textContent='There is no gap for another marker. Adjust an existing time first.';message.dataset.error='true';}
  });
  form.querySelector('[data-tl-reset]')?.addEventListener('click',()=>{events=clone(original);dirty=false;render();if(cfg.live||stale)poll();});
  form.addEventListener('submit',ev=>{const err=invalid();if(err||!cfg.editable){ev.preventDefault();message.textContent=err;message.dataset.error='true';return;}dirty=false;});
  window.addEventListener('beforeunload',ev=>{if(dirty){ev.preventDefault();ev.returnValue='';}});
  // Editing must never be replaced by a background refresh, including partially typed inputs.
  rows.addEventListener('focusin',()=>{if(cfg.editable){pause();render(false,false);}});
  form.querySelector('[name=notes]')?.addEventListener('input',()=>{pause();render(false,false);});
  async function poll(discard=false){
   if(busy||!cfg.endpoint||document.hidden)return;
   busy=true;refresh.disabled=true;
   try{
    const response=await fetch(cfg.endpoint,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:AbortSignal.timeout(10000)});
    if(!response.ok)throw new Error('Live data unavailable');
    const data=await response.json();
    if(!Array.isArray(data.events)||!Number.isFinite(data.cutoff)||typeof data.revision!=='string'||data.day!==cfg.day)throw new Error('Invalid live data');
    offline=false;
    if(dirty&&!discard){stale=data.revision!==cfg.revision;render(false,false);return;}
    const changedData=data.revision!==cfg.revision||discard;
    Object.assign(cfg,data);original=clone(data.original);events=clone(data.events);dirty=false;stale=false;receivedAt=performance.now();
    if(discard){const notes=form.querySelector('[name=notes]');if(notes)notes.value='';}
    render(changedData,!track.contains(document.activeElement));
   }catch(error){offline=true;render(false,false);}
   finally{busy=false;refresh.disabled=false;}
  }
  refresh.addEventListener('click',()=>poll(true));
  setInterval(()=>{if(cfg.live&&!dirty&&!offline&&!document.hidden)render(false,!track.contains(document.activeElement));},1000);
  setInterval(()=>{if(cfg.live||dirty)poll();},10000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
  render();
 });
})();

