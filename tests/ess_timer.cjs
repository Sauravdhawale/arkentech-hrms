// Exercise the shipped card renderers with a controlled clock and status events.
const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm');
let clock=0;const handlers={},intervals=[];
function card(){const nodes=new Map();return {dataset:{},querySelector(key){if(!nodes.has(key))nodes.set(key,{textContent:'',value:0});return nodes.get(key);},querySelectorAll(key){return [...nodes.entries()].filter(([k])=>k.startsWith(key.slice(0,-1)+'=')).map(([,v])=>v);}};}
const work=card(),attendance=card();
const document={body:{},hidden:false,querySelector:s=>s==='[data-workday]'?work:s==='[data-attendance-today]'?attendance:null,querySelectorAll:()=>[],addEventListener:(name,fn)=>(handlers[name]??=[]).push(fn)};
vm.runInNewContext(fs.readFileSync('assets/ess.js','utf8'),{document,performance:{now:()=>clock},MutationObserver:class{observe(){}},setInterval:fn=>intervals.push(fn)});
const send=data=>(handlers['ess:punch-status']||[]).forEach(fn=>fn({detail:data}));
const tick=seconds=>{clock+=seconds*1000;intervals.forEach(fn=>fn());};
const w=key=>work.querySelector(`[data-workday-value="${key}"]`).textContent;
const a=key=>attendance.querySelector(`[data-attendance-live="${key}"]`).textContent;
const baseline={as_of:64790,shift_start:32400,shift_end:64800,limit:72000,state:'in',worked:28790,after_end:0,break:0,allowed_break:3600,target:28800,first_in:32400,last_out:null,first_in_label:'09:00 AM',last_out_label:'—',shift_label:'Day',expired:false,inferred:true};
send({state:'in',timing:baseline});tick(20);
assert.equal(w('overtime'),'00h 00m 10s');assert.equal(a('overtime'),w('overtime'));
assert.equal(w('worked'),'08h 00m 00s','Shift work stops at scheduled end while overtime continues');
send({transient:true,label:'Status unavailable'});tick(10);
assert.equal(w('worked'),'08h 00m 00s');assert.equal(a('worked'),w('worked'));
assert.match(work.querySelector('[data-workday-state]').textContent,/estimated/);
tick(120);assert.equal(w('worked'),'08h 00m 00s');assert.equal(w('overtime'),'00h 01m 20s','Outage estimate must stop after 90 seconds');
assert.match(work.querySelector('[data-workday-state]').textContent,/paused/);
send({state:'out',timing:{...baseline,as_of:68400,state:'out',worked:32400,after_end:3600,first_in:34200}});
assert.equal(w('overtime'),'00h 30m 00s');assert.equal(a('overtime'),w('overtime'),'Both cards deduct late arrival once');
tick(5);assert.equal(w('worked'),'08h 00m 00s');assert.equal(w('overtime'),'00h 30m 00s','No overtime while checked out');
send({state:'review',label:'Check-Out missing',timing:{...baseline,as_of:72001,expired:true,worked:36000,after_end:7200}});
assert.equal(a('worked'),'08h 00m 00s','Expired shift totals remain visible for review');
assert.match(attendance.querySelector('[data-attendance-live-state]').textContent,/Last punch: Check In/);
send({state:'review',label:'Punch needs review'});assert.equal(a('worked'),'—');assert.equal(w('worked'),'—');
send({state:'none',timing:{...baseline,state:'none',worked:0,first_in:null}});tick(5);assert.equal(w('worked'),'00h 00m 00s');
console.log('PASS: shift-end overtime, matching cards, delayed polls, outage bounds, checkout, recovery and review states.');


// Overnight shift: completing required work before 4 AM must not start overtime.
const night={...baseline,shift_start:68400,shift_end:100800,limit:108000,
 as_of:100320,worked:28928,after_end:0,first_in:69549,shift_end_label:'29 Oct, 04:00 AM'};
send({state:'in',timing:night});
assert.equal(w('overtime'),'00h 00m 00s');
assert.match(work.querySelector('[data-workday-target]').textContent,/29 Oct, 04:00 AM/);
send({state:'in',timing:{...night,as_of:102600,worked:31000,after_end:1800}});
assert.equal(w('overtime'),'00h 10m 51s','30 minutes after end minus 19m09s late arrival');
assert.equal(a('overtime'),w('overtime'));



// Before-shift work is overtime; cross the boundary without another poll.
const earlySnapshot={...baseline,as_of:32390,first_in:30600,before_start:1790,shift_worked:0,worked:1790,after_end:0};
send({state:'in',timing:earlySnapshot});
assert.equal(w('worked'),'00h 00m 00s');assert.equal(w('overtime'),'00h 29m 50s');
assert.match(work.querySelector('[data-workday-state]').textContent,/Before-shift overtime/);
tick(20);assert.equal(w('worked'),'00h 00m 10s');assert.equal(w('overtime'),'00h 30m 00s');
assert.equal(a('worked'),w('worked'));assert.equal(a('overtime'),w('overtime'));
assert.equal(w('remaining'),'07h 59m 50s','Early overtime does not reduce required shift work');
// An OUT spanning the start cannot accrue shift work, overtime, or pre-shift break.
send({state:'out',timing:{...earlySnapshot,state:'out',as_of:32390,before_start:1200,worked:1200}});
tick(20);assert.equal(w('worked'),'00h 00m 00s');assert.equal(w('overtime'),'00h 20m 00s');assert.equal(w('break'),'00h 00m 10s');
// Overnight start at midnight, checked in beforehand, uses the same interval split.
send({state:'in',timing:{...earlySnapshot,as_of:86390,shift_start:86400,shift_end:118800,limit:126000,first_in:84600}});
tick(20);assert.equal(w('worked'),'00h 00m 10s');assert.equal(w('overtime'),'00h 30m 00s');
console.log('PASS: before-shift overtime, regular shift boundaries, pre-shift breaks, midnight and matching live cards.');
