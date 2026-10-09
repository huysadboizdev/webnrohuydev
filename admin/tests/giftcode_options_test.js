const {test}=require('node:test');
const assert=require('node:assert/strict');
let api={};try { api=require('../assets/js/giftcode-options.js'); } catch(e) { if(e.code!=='MODULE_NOT_FOUND')throw e; }
test('quick pairs preserve zero and reject malformed, duplicate, overflow and too many options',()=>{
 assert.equal(typeof api.parseOptions,'function');
 assert.deepEqual(api.parseOptions('0:0 50:20'),[{id:0,param:0},{id:50,param:20}]);
 assert.deepEqual(api.parseOptions(''),[]);
 for(const s of ['50:20 50:30','50:-1','50:1.5','50:2147483648','abc','50:','32768:0',Array.from({length:101},(_,i)=>`${i}:0`).join(' ')])assert.throws(()=>api.parseOptions(s));
});
test('draft clones options; edit cancellation and duplicates leave original items intact',()=>{
 assert.equal(typeof api.Draft,'function');
 const d=new api.Draft();const opts=api.parseOptions('0:0');
 d.save({id:1,quantity:2,options:opts});opts[0].param=99;
 d.save({id:2,quantity:1,options:api.parseOptions('30:0')});
 const edit=d.edit(0);edit.options[0].param=7;d.cancel();
 assert.equal(d.items[0].options[0].param,0);
 assert.throws(()=>d.save({id:1,quantity:1,options:[]}));
 d.edit(0);d.save({id:1,quantity:3,options:[{id:0,param:8}]});
 assert.equal(d.items.length,2);assert.equal(d.items[0].quantity,3);assert.equal(d.items[1].options[0].id,30);
});
test('loading legacy string IDs still blocks duplicate numeric IDs',()=>{
 const d=new api.Draft([{id:'1',quantity:'2',options:[{id:'0',param:'0'}]}]);
 assert.throws(()=>d.save({id:1,quantity:1,options:[]}));
 assert.equal(d.items[0].id,1);
});
test('async quick options do not replace a different editor and reject unknown IDs atomically',async()=>{
 assert.equal(typeof api.resolveOptions,'function');
 let release;let current=true;
 const pending=api.resolveOptions('50:20',()=>new Promise(resolve=>release=resolve),()=>current);
 current=false;release();assert.equal(await pending,null);
 await assert.rejects(api.resolveOptions('0:0 50:20',async id=>{if(id===50)throw new Error('Unknown option');},()=>true));
 assert.deepEqual(await api.resolveOptions('0:0',async()=>{},()=>true),[{id:0,param:0}]);
});
