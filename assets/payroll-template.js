(() => {
 document.querySelectorAll('[data-pay-template-editor]').forEach(editor => {
  const form=editor.closest('form'), html=editor.querySelector('[name=html_template]'), mode=editor.querySelector('[name=template_mode]'), rows=editor.querySelector('[data-attribute-rows]'), message=editor.querySelector('[data-template-message]');
  let next=rows.children.length;
  editor.querySelector('[data-token-search]')?.addEventListener('input',event=>{
   const query=event.target.value.trim().toLowerCase().replaceAll(' ','_');
   editor.querySelectorAll('[data-template-token]').forEach(button=>button.hidden=!button.dataset.templateToken.includes(query));
   editor.querySelectorAll('.pay-token-group').forEach(group=>{group.hidden=!!query&&!group.querySelector('[data-template-token]:not([hidden])');group.open=!!query;});
  });
  editor.addEventListener('click',async event => {
   const button=event.target.closest('button');if(!button)return;
   if(button.hasAttribute('data-print-preview')){editor.querySelector('[data-template-preview]').contentWindow.print();return;}
   if(button.hasAttribute('data-template-starter')){
    if(html.value.trim()&&!window.confirm('Replace the HTML currently in the editor with this starter layout?'))return;
    html.value=button.dataset.templateStarter;mode.value='html';
   }
   if(button.hasAttribute('data-template-token')){html.setRangeText('['+button.dataset.templateToken+']',html.selectionStart,html.selectionEnd,'end');html.focus();}
   if(button.hasAttribute('data-remove-attribute'))button.closest('[data-attribute-row]').remove();
   if(button.hasAttribute('data-add-attribute')){
    if(rows.children.length>=40){message.textContent='Use at most 40 custom attributes.';return;}
    const row=document.createElement('div');row.className='pay-grid';row.dataset.attributeRow='';const prefix='attributes['+(next++)+']';
    row.innerHTML='<label>Attribute name<input name="'+prefix+'[name]" pattern="[a-z][a-z0-9_]{0,39}" maxlength="40" placeholder="office_label"></label><label>Source<select name="'+prefix+'[source]"><option value="text">Fixed text</option><option value="field">Saved field</option><option value="component">Salary component code</option></select></label><label>Text / field name / code<input name="'+prefix+'[value]" maxlength="500"></label><button type="button" class="button" data-remove-attribute>Remove</button>';rows.append(row);
   }
   if(button.hasAttribute('data-preview-template')){
    button.disabled=true;message.textContent='Preparing preview…';
    try{const response=await fetch('payroll-template-preview.php',{method:'POST',body:new FormData(form),credentials:'same-origin'});const result=await response.json();if(!response.ok||result.error)throw new Error(result.error||'Preview unavailable.');const frame=editor.querySelector('[data-template-preview]');frame.srcdoc=result.html;frame.hidden=false;editor.querySelector('[data-preview-empty]').hidden=true;editor.querySelector('[data-print-preview]').disabled=false;message.textContent='Sample preview only. Save a dated revision to use this design for new payroll runs.';}
    catch(error){message.textContent=error.message||'Unable to preview. Check your session and try again.';}finally{button.disabled=false;}
   }
  });
  if(mode.value==='standard'||html.value.trim())editor.querySelector('[data-preview-template]').click();
 });
})();



