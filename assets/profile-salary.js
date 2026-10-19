(()=>{
 const toggle=document.getElementById('setup-profile-salary'),fields=document.getElementById('profile-salary-fields');
 if(toggle&&fields){const update=()=>{fields.disabled=!toggle.checked;fields.hidden=!toggle.checked;};toggle.addEventListener('change',update);update();}
 document.querySelectorAll('[data-salary-editor]').forEach(editor=>{
  let rows;try{rows=JSON.parse(editor.dataset.salaryRules||'[]');}catch{return;}
  const date=editor.querySelector('[name="salary[effective_from]"]'),select=editor.querySelector('[name="salary[structure_id]"]'),note=editor.querySelector('[data-salary-rule-note]'),details=editor.querySelector('details');
  const basis=editor.querySelector('[name="salary[salary_basis]"]');
  if(!date||!select||!note)return;
  let family=select.selectedOptions[0]?.dataset.family||'';
  const update=()=>{
   if(!date.value)return;
   const groups=new Map();rows.forEach(row=>{if(!groups.has(row.family))groups.set(row.family,[]);groups.get(row.family).push(row);});
   const choices=[],effective=[];
   groups.forEach((revisions,key)=>{
    revisions.sort((a,b)=>b.from.localeCompare(a.from)||b.id-a.id);
    const valid=revisions.find(row=>row.from<=date.value&&(!row.until||row.until>=date.value));
    choices.push({family:key,row:valid||revisions[0],valid:!!valid});if(valid)effective.push({family:key,row:valid});
   });
   if(!family&&effective.length===1)family=effective[0].family;
   const chosen=choices.find(c=>c.family===family),valid=chosen?.valid;
   select.replaceChildren(new Option('Choose applicable salary rules',''));
   choices.forEach(choice=>{const option=new Option(`${choice.row.title} · from ${choice.row.from}`,String(choice.row.id));option.dataset.family=choice.family;option.selected=choice.family===family;select.add(option);});
   if(!chosen)select.value='';
   note.classList.toggle('salary-rules-warning',!valid);
   if(valid)note.textContent=`Salary breakup uses ${chosen.row.title} automatically.`;
   else{
    const scope=family?rows.filter(row=>row.family===family):rows,earliest=scope.map(row=>row.from).sort()[0];
    note.textContent=earliest&&date.value<earliest?`Salary rules are available from ${earliest}. Enter a salary start date on or after that date, or configure rules for the earlier period.`:chosen?`The selected salary rules do not cover ${date.value}. Review the rules in More salary options.`:effective.length>1?'More than one salary rule applies. Choose one in More salary options.':`No salary rules cover ${date.value}. Review Payroll → Configuration.`;
   }
  };
  let dateEdited=editor.dataset.salaryDateTouched==='1';
  date.addEventListener('change',()=>{dateEdited=true;update();});
  if(toggle){const joining=editor.closest('form')?.querySelector('[name="joining_date"]');if(joining)joining.addEventListener('change',()=>{if(!dateEdited){date.value=[editor.dataset.salaryMonthStart||date.value,joining.value].sort().pop();update();}});}
  select.addEventListener('change',()=>{family=select.selectedOptions[0]?.dataset.family||'';update();});
  editor.addEventListener('invalid',event=>{if(event.target.closest('details')&&details)details.open=true;},true);
  if(basis){const updateBasis=()=>{const annual=basis.value==='Annual CTC';editor.querySelector('[data-salary-amount-label]').textContent=annual?'Annual CTC (₹) *':'Monthly salary (₹) *';editor.querySelector('[data-salary-basis-note]').textContent=annual?'Annual company cost; company rules calculate the monthly salary.':'Monthly gross before attendance and other deductions.';};basis.addEventListener('change',updateBasis);updateBasis();}
  update();
 });
})();
