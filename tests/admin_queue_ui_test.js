const vm=require('node:vm'),fs=require('node:fs'),assert=require('node:assert/strict');
let bodies=[],states=['processing','completed'],calls=0;
const context={document:{addEventListener(){},querySelector(){return {content:'csrf-test'};}},console,crypto:{randomUUID:()=> '550e8400-e29b-41d4-a716-446655440000'},Map,JSON,Promise,Date,setTimeout:f=>f(),showToast(){},fetch:async(url,opts)=>{
 if(opts?.method==='POST'){bodies.push(JSON.parse(opts.body));return {ok:true,json:async()=>({status:'queued',command_id:7})};}
 calls++;return {ok:true,json:async()=>({status:'success',data:{id:7,status:states.shift(),result_message:'Applied'}})};
}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../admin/assets/js/admin.js','utf8'),context);
(async()=>{let done=false;context.showToast=()=>{};
 const result=await context.adminCommandFetch('/api',{method:'POST',body:JSON.stringify({action:'heal_full',player_id:1})});
 const data=await result.json();assert.equal(data.status,'success');assert.equal(calls,2);assert.equal(bodies[0].csrf_token,'csrf-test');assert.ok(bodies[0].request_id);
 states=['failed'];calls=0;const failed=await (await context.adminCommandFetch('/api',{method:'POST',body:JSON.stringify({action:'heal_full',player_id:2})})).json();assert.equal(failed.status,'error');assert.equal(failed.message,'Applied');
 const pending=await context.waitForAdminCommand(9,0);assert.equal(pending.status,'queued');
 let retryBodies=[];let attempt=0;
 context.fetch=async(url,opts)=>{
  if(opts?.method==='POST'){retryBodies.push(JSON.parse(opts.body));if(attempt++===0)throw new Error('connection lost');return {ok:true,json:async()=>({status:'queued',command_id:8})};}
  return {ok:true,json:async()=>({status:'success',data:{status:'completed',result_message:'OK'}})};
 };
 const operation={method:'POST',body:JSON.stringify({action:'topup_player',player_id:3,gold_add:'9007199254740993'})};
 await assert.rejects(context.adminCommandFetch('/api',operation),/connection lost/);
 await context.adminCommandFetch('/api',operation);
 assert.equal(retryBodies[0].request_id,retryBodies[1].request_id);assert.equal(retryBodies[1].gold_add,'9007199254740993');
 let cbCount=0;context.fetch=async(url,opts)=>opts?.method==='POST'?{ok:true,json:async()=>({status:'queued',command_id:8})}:{ok:true,json:async()=>({status:'success',data:{status:'failed',result_message:'rejected'}})};
 await context.triggerLiveSync('RELOAD_SHOP',{},()=>cbCount++);assert.equal(cbCount,0);
 context.fetch=async()=>({ok:false,json:async()=>({status:'error',message:'Admin access required'})});
 const denied=await (await context.adminCommandFetch('/api',{method:'POST',body:JSON.stringify({action:'heal_full',player_id:4})})).json();assert.equal(denied.status,'error');
 let posted=0, polled=0;
 context.fetch=async(url,opts)=>{
  if(opts?.method==='POST'){posted++;return {ok:true,json:async()=>({status:'queued',command_id:17})};}
  if(polled++===0)throw new Error('poll interrupted');
  return {ok:true,json:async()=>({status:'success',data:{status:'completed',result_message:'OK'}})};
 };
 const pendingOperation={method:'POST',body:JSON.stringify({action:'heal_full',player_id:5})};
 await assert.rejects(context.adminCommandFetch('/api',pendingOperation),/poll interrupted/);
 await context.adminCommandFetch('/api',pendingOperation);assert.equal(posted,1,'known queued request retry only polls');
 context.fetch=async()=>({ok:false,json:async()=>{throw new SyntaxError('invalid JSON');}});
 await assert.rejects(context.adminCommandFetch('/api',{method:'POST',body:JSON.stringify({action:'heal_full',player_id:6})}),/invalid JSON/);
 console.log('queue UI completion/failure/timeout/network retry/callback/auth OK');
})().catch(e=>{console.error(e);process.exitCode=1;});
