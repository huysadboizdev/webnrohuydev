const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=__dirname+'/../admin/assets/js/server-maintenance.js';
assert.ok(fs.existsSync(path),'maintenance UI exists');
const context={document:{addEventListener(){}},console};vm.createContext(context);vm.runInContext(fs.readFileSync(path,'utf8'),context);
const state=phase=>({available:true,heartbeat_stale:false,state:{phase}});
assert.equal(context.maintenanceCanSend(state('OPEN'),'MAINTENANCE_START',false),true);
assert.equal(context.maintenanceCanSend(state('FAILED'),'MAINTENANCE_START',false),true);
assert.equal(context.maintenanceCanSend(state('DRAINING'),'MAINTENANCE_START',false),false);
assert.equal(context.maintenanceCanSend(state('MAINTENANCE'),'MAINTENANCE_OPEN',false),true);
for(const phase of ['OPEN','DRAINING','FAILED'])assert.equal(context.maintenanceCanSend(state(phase),'MAINTENANCE_OPEN',false),false);
assert.equal(context.maintenanceCanSend({...state('OPEN'),heartbeat_stale:true},'MAINTENANCE_START',false),true,'stale heartbeat still permits queueing maintenance');
assert.equal(context.maintenanceCanSend({...state('FAILED'),heartbeat_stale:true},'MAINTENANCE_START',false),true);
assert.equal(context.maintenanceCanSend({...state('MAINTENANCE'),heartbeat_stale:true},'MAINTENANCE_OPEN',false),false,'stale state must never reopen');
assert.equal(context.maintenanceCanSend({...state('OPEN'),available:false},'MAINTENANCE_START',false),false);
assert.equal(context.maintenanceCanSend(state('OPEN'),'MAINTENANCE_START',true),false);
assert.equal(context.maintenanceOperationFinished({type:'MAINTENANCE_START',id:22},{status:'completed'},state('DRAINING')),false);
assert.equal(context.maintenanceOperationFinished({type:'MAINTENANCE_START',id:22},{status:'completed'},{...state('MAINTENANCE'),state:{phase:'MAINTENANCE',command_id:'22'}}),true);
assert.equal(context.maintenanceOperationFinished({type:'MAINTENANCE_START',id:22},{status:'completed'},{...state('MAINTENANCE'),state:{phase:'MAINTENANCE',command_id:'21'}}),false);
assert.equal(context.maintenanceOperationFinished({type:'MAINTENANCE_START',id:22},{status:'failed'},state('OPEN')),true);
console.log('maintenance phase gates, stale/queued controls, async completion correlation OK');
(async()=>{
    const elements={};
    for(const id of ['serverMaintenance','maintenanceStart','maintenanceOpen','maintenanceRetry','maintenanceRefresh','maintenanceState','maintenanceHeartbeat','maintenanceDetail','maintenanceMessage','maintenanceHistory']) elements[id]={textContent:'',disabled:false,hidden:false,listeners:{},addEventListener(k,fn){this.listeners[k]=fn;},replaceChildren(){},append(){}};
    let ready,phase='OPEN',failPost=true,postCount=0,confirmCount=0;
    const requests=[],stored=new Map();
    const ui={console,document:{addEventListener(k,fn){ready=fn;},getElementById(id){return elements[id];},createElement(){return {textContent:''};}},
        sessionStorage:{getItem(k){return stored.get(k)||null;},setItem(k,v){stored.set(k,v);},removeItem(k){stored.delete(k);}},
        adminUrl:x=>'/subfolder'+x,adminRequestId:()=> 'request_1234567890',adminCsrfToken:()=> 'token',setInterval(){},window:{confirm(){confirmCount++;return true;}},
        async fetch(url,options){
            if(options?.method==='POST') {postCount++;requests.push(JSON.parse(options.body));if(failPost)throw new Error('Network lost');return {ok:true,status:202,json:async()=>({status:'queued',command_id:22})};}
            if(url.includes('check_id='))return {ok:true,json:async()=>({status:'success',data:{status:'completed',result_message:'accepted'}})};
            return {ok:true,json:async()=>({data:{available:true,heartbeat_stale:false,state:{phase,command_id:'22',revision:'2',server_run:'run',heartbeat_at:'now',heartbeat_age_seconds:1},history:[]}})};
        }};
    vm.createContext(ui);vm.runInContext(fs.readFileSync(path,'utf8'),ui);ready();await new Promise(setImmediate);
    assert.equal(elements.maintenanceStart.disabled,false);
    await elements.maintenanceStart.listeners.click();
    assert.equal(confirmCount,1);assert.equal(postCount,1);assert.equal(stored.size,1,'uncertain HTTP result retained');
    assert.equal(elements.maintenanceOpen.disabled,true);
    failPost=false;phase='DRAINING';await elements.maintenanceRetry.listeners.click();
    assert.equal(postCount,2);assert.equal(requests[0].request_id,requests[1].request_id,'retry keeps idempotency ID');
    assert.equal(requests[1].csrf_token,'token');assert.deepEqual(Object.keys(requests[1].payload),[]);
    assert.equal(stored.size,1,'completed queue receipt alone must not clear async operation');
    assert.match(elements.maintenanceMessage.textContent,/chờ trạng thái cuối/);
    await elements.maintenanceRetry.listeners.click();assert.equal(postCount,2,'known command only polls');
    phase='MAINTENANCE';await elements.maintenanceRefresh.listeners.click();
    assert.equal(stored.size,0,'correlated terminal phase completes operation');assert.equal(elements.maintenanceOpen.disabled,false);
    console.log('maintenance UI confirmation, CSRF, network retry dedup, async drain and reopen flow OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
