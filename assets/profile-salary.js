(()=>{const toggle=document.getElementById('setup-profile-salary'),fields=document.getElementById('profile-salary-fields');if(!toggle||!fields)return;const update=()=>{fields.disabled=!toggle.checked;fields.hidden=!toggle.checked;};toggle.addEventListener('change',update);update();})();

