(function(root){
 'use strict';
 const clone=value=>JSON.parse(JSON.stringify(value));
 function integer(raw,min,max){
  if(!/^-?\d+$/.test(String(raw)))throw new Error('Cần nhập số nguyên');
  const value=Number(raw);if(!Number.isSafeInteger(value)||value<min||value>max)throw new Error('Số ngoài giới hạn');return value;
 }
 function parseOptions(raw){
  const tokens=String(raw).trim()?String(raw).trim().split(/\s+/):[];
  if(tokens.length>100)throw new Error('Tối đa 100 option/món');
  const seen=new Set();return tokens.map(token=>{
   if(!/^\d+:\d+$/.test(token))throw new Error('Option phải có dạng ID:param, ví dụ 50:20');
   const [a,b]=token.split(':');const id=integer(a,0,32767),param=integer(b,0,2147483647);
   if(seen.has(id))throw new Error('Trùng option #'+id);seen.add(id);return {id,param};
  });
 }
 class Draft {
  constructor(items=[]){this.items=[];this.index=null;items.forEach(item=>this.save(clone(item)));}
  edit(index){if(!this.items[index])throw new Error('Không tìm thấy món');this.index=index;return clone(this.items[index]);}
  cancel(){this.index=null;}
  save(item){
   const id=integer(item.id,-3,32767),quantity=integer(item.quantity,1,2147483647);
   const options=parseOptions((item.options||[]).map(op=>`${op.id}:${op.param}`).join(' '));
   if(this.items.some((old,index)=>old.id===id&&index!==this.index))throw new Error('Món đã có; hãy dùng nút Sửa');
   if(this.index===null&&this.items.length>=200)throw new Error('Tối đa 200 món/code');
   const value={id,quantity,options};if(this.index===null)this.items.push(value);else this.items[this.index]=value;this.cancel();
  }
  remove(index){this.items.splice(index,1);this.cancel();}
 }
 async function resolveOptions(raw,resolver,isCurrent){
  const candidate=parseOptions(raw);await Promise.all(candidate.map(op=>resolver(op.id)));
  return isCurrent()?candidate:null;
 }
 const api={parseOptions,Draft,integer,resolveOptions};
 if(typeof module!=='undefined'&&module.exports)module.exports=api;
 root.GiftcodeOptions=api;
 if(typeof document==='undefined')return;
 document.addEventListener('DOMContentLoaded',()=>{
  const boot=root.giftcodeBoot;if(!boot)return;
  const el=id=>document.getElementById(id),form=el('giftcodeForm');
  let draft=new Draft(),options=[{id:30,param:0}],optionNames=new Map(Object.entries(boot.optionNames||{}).map(([id,name])=>[Number(id),name]));
  let itemNames=new Map(Object.entries(boot.itemNames||{}).map(([id,meta])=>[Number(id),meta]));
  let optionGeneration=0,itemGeneration=0,editorGeneration=0,pendingOptions=0;let optionTimer,itemTimer;
  const error=message=>{el('giftcodeError').textContent=message;};
  const button=(text,handler)=>{const b=document.createElement('button');b.type='button';b.className='btn btn-sm btn-outline';b.textContent=text;b.onclick=handler;return b;};
  const itemIcon=url=>{const img=document.createElement('img');img.src=url;img.alt='';img.width=24;img.height=24;return img;};
  const optionLabel=op=>`#${op.id}: ${(optionNames.get(op.id)||'Option').replace(/#/g,String(op.param))} (param ${op.param})`;
  function renderOptions(){
   el('optionRows').replaceChildren();options.forEach((op,index)=>{
    const row=document.createElement('div');row.className='giftcode-option-row';
    const label=document.createElement('span');label.textContent=optionLabel(op);
    const input=document.createElement('input');input.type='number';input.className='form-control';input.min='0';input.max='2147483647';input.value=op.param;
    input.onchange=()=>{try{op.param=integer(input.value,0,2147483647);++editorGeneration;renderOptions();error('');}catch(e){input.value=op.param;error(e.message);}};
    row.append(label,input,button('Xóa',()=>{++editorGeneration;options.splice(index,1);renderOptions();}));el('optionRows').append(row);
   });
  }
  function resetEditor(){++editorGeneration;++itemGeneration;++optionGeneration;clearTimeout(itemTimer);clearTimeout(optionTimer);el('itemSearchResults').replaceChildren();el('optionSearchResults').replaceChildren();el('optionSearch').value='';draft.cancel();el('selectedItemId').value='';el('searchItemInput').value='';el('selectedItemQty').value=1;options=[{id:30,param:0}];el('quickOptions').value='';el('saveItem').textContent='Thêm món';el('cancelItem').hidden=true;renderOptions();}
  function renderItems(){
   el('giftItemList').replaceChildren();draft.items.forEach((item,index)=>{
    const row=document.createElement('div');row.className='giftcode-item';const title=document.createElement('strong');
    title.textContent=`#${item.id} ${itemNames.get(item.id)?.name||'Phần thưởng'} × ${item.quantity}`;
    if(itemNames.get(item.id)?.icon_url)row.append(itemIcon(itemNames.get(item.id).icon_url));row.append(title);
    item.options.forEach(op=>{const line=document.createElement('div');line.textContent=optionLabel(op);row.append(line);});
    row.append(button('Sửa',()=>{resetEditor();const edited=draft.edit(index);el('selectedItemId').value=edited.id;el('searchItemInput').value=itemNames.get(edited.id)?.name||'#'+edited.id;el('selectedItemQty').value=edited.quantity;options=edited.options;el('saveItem').textContent='Lưu món';el('cancelItem').hidden=false;renderOptions();}),button('Xóa',()=>{draft.remove(index);resetEditor();renderItems();}));el('giftItemList').append(row);
   });
  }
  async function catalog(action,q){
   const res=await fetch(`api/search_items.php?action=${action}&q=${encodeURIComponent(q)}&limit=252`,{headers:{'X-CSRF-Token':adminCsrfToken()}});
   if(!res.ok)throw new Error('Không tải được catalog; hãy thử lại');const data=await res.json();if(data.status!=='success')throw new Error(data.message||'Không tải được catalog');return data.data;
  }
  async function resolveOption(id){
   if(optionNames.has(id))return;
   const rows=await catalog('options',String(id));const found=rows.find(row=>Number(row.id)===id);if(!found)throw new Error('Không tìm thấy option #'+id);optionNames.set(id,found.name);
  }
  el('searchItemInput').oninput=()=>{
   clearTimeout(itemTimer);const generation=++itemGeneration,q=el('searchItemInput').value.trim();el('selectedItemId').value='';el('itemSearchResults').replaceChildren();
   if(!q)return;itemTimer=setTimeout(async()=>{try{const rows=await catalog('items',q);if(generation!==itemGeneration)return;
    rows.forEach(item=>{item.id=Number(item.id);itemNames.set(item.id,item);const choice=button(`#${item.id} ${item.name}`,()=>{el('selectedItemId').value=item.id;el('searchItemInput').value=item.name;el('itemSearchResults').replaceChildren();++itemGeneration;});if(item.icon_url)choice.prepend(itemIcon(item.icon_url));el('itemSearchResults').append(choice);});if(!rows.length)error('Không tìm thấy vật phẩm');
   }catch(e){if(generation===itemGeneration)error(e.message);}},250);
  };
  el('optionSearch').oninput=()=>{
   clearTimeout(optionTimer);const generation=++optionGeneration,q=el('optionSearch').value.trim();el('optionSearchResults').replaceChildren();
   if(!q)return;optionTimer=setTimeout(async()=>{try{const rows=await catalog('options',q);if(generation!==optionGeneration)return;
    rows.forEach(op=>{const id=Number(op.id);optionNames.set(id,op.name);el('optionSearchResults').append(button(`#${id} ${op.name}`,()=>{try{options=parseOptions(options.map(o=>`${o.id}:${o.param}`).concat(`${id}:0`).join(' '));++editorGeneration;renderOptions();error('');}catch(e){error(e.message);}}));});if(!rows.length)error('Không tìm thấy option');
   }catch(e){if(generation===optionGeneration)error(e.message);}},250);
  };
  el('applyQuickOptions').onclick=async()=>{
   const generation=++editorGeneration;++pendingOptions;el('applyQuickOptions').disabled=true;
   try{const candidate=await resolveOptions(el('quickOptions').value,resolveOption,()=>generation===editorGeneration);
    if(candidate!==null){options=clone(candidate);renderOptions();error('');}
   }catch(e){if(generation===editorGeneration)error(e.message);}finally{--pendingOptions;el('applyQuickOptions').disabled=pendingOptions>0;}
  };
  el('saveItem').onclick=()=>{try{if(pendingOptions)throw new Error('Chờ xác nhận options trước khi lưu món');draft.save({id:el('selectedItemId').value,quantity:el('selectedItemQty').value,options});resetEditor();renderItems();error('');}catch(e){error(e.message);}};
  el('cancelItem').onclick=resetEditor;
  function openGift(gift){
   ++itemGeneration;++optionGeneration;clearTimeout(itemTimer);clearTimeout(optionTimer);el('itemSearchResults').replaceChildren();el('optionSearchResults').replaceChildren();
   const editing=Boolean(gift?.id);form.elements.action.value=editing?'update_giftcode':'create_giftcode';form.elements.id.value=gift?.id||'';form.elements.expected_fingerprint.value=gift?.fingerprint||'';
   form.elements.code.value=gift?.code||'';form.elements.code.readOnly=editing;form.elements.count_left.value=gift?.count_left??-1;form.elements.expired.value=(gift?.expired||boot.defaultExpiry).replace(' ','T');
   el('randomCode').hidden=editing;el('giftcodeModalTitle').textContent=editing?'Sửa Giftcode':'Tạo Giftcode';
   form.elements.request_id.value=gift?.request_id||adminRequestId();draft=new Draft(gift?.items||[]);resetEditor();renderItems();error('');openModal('createGiftcodeModal');
  }
  el('newGiftcode').onclick=()=>openGift(null);
  document.querySelectorAll('[data-edit-giftcode]').forEach(b=>b.onclick=()=>openGift(boot.giftcodes.find(gift=>Number(gift.id)===Number(b.dataset.editGiftcode))));
  el('randomCode').onclick=()=>{const bytes=new Uint8Array(8);crypto.getRandomValues(bytes);form.elements.code.value='NRO-'+Array.from(bytes,b=>'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[b%32]).join('');};
  form.onsubmit=e=>{try{if(pendingOptions)throw new Error('Chờ xác nhận options trước khi lưu code');if(draft.index!==null||el('selectedItemId').value!=='')throw new Error('Lưu hoặc hủy món đang soạn trước khi lưu code');if(!draft.items.length)throw new Error('Cần ít nhất một món');form.elements.items_json.value=JSON.stringify(draft.items);}catch(err){e.preventDefault();error(err.message);}};
  if(boot.restore)openGift(boot.restore);else{resetEditor();renderItems();}
 });
})(typeof globalThis!=='undefined'?globalThis:this);
