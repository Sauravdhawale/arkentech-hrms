'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('assets/foundation.js','utf8');
function scenario(depValue,selected,legacy){
 const callbacks={};
 const options=[
  {value:'',dataset:{}},
  {value:'1',dataset:{department:'10'}},
  {value:'2',dataset:{department:'20'}},
  {value:'3',dataset:legacy?{department:'',legacyDepartment:'10'}:{department:''}}
 ].map(o=>({...o,hasAttribute(name){return name==='data-legacy-department'&&Object.hasOwn(this.dataset,'legacyDepartment');}}));
 const department={value:depValue,addEventListener(name,callback){callbacks[name]=callback;}};
 const designation={options,value:selected,get selectedOptions(){return options.filter(o=>o.value===this.value);}};
 const document={body:{classList:{add(){}}},getElementById(){return null;},addEventListener(){},querySelectorAll(){return [];},querySelector(s){if(s==='[name=department_id][data-dependent]')return department;if(s==='[name=designation_id]')return designation;return null;}};
 vm.runInNewContext(source,{document,localStorage:{getItem(){return null;}}});
 return {options,department,designation,change(value){department.value=value;callbacks.change();}};
}
let s=scenario('10','1',false);
assert.equal(s.options[1].disabled,false);assert.equal(s.options[2].disabled,true);assert.equal(s.options[3].disabled,true);
s.change('20');assert.equal(s.designation.value,'');assert.equal(s.options[1].hidden,true);assert.equal(s.options[2].hidden,false);
s=scenario('','',false);assert.ok(s.options.slice(1).every(o=>o.disabled));assert.equal(s.options[0].disabled,false);
s=scenario('10','3',true);assert.equal(s.options[3].disabled,false);assert.equal(s.designation.value,'3');
s.change('20');assert.equal(s.options[3].disabled,true);assert.equal(s.designation.value,'');
console.log('PASS: Actual employee dropdown script filters scoped options, clears stale selection, hides global options and preserves unchanged legacy assignments.');
