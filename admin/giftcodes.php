<?php
$page_title='Giftcode Studio & Quản Lý Quà Tặng';$active_tab='giftcodes';
require_once __DIR__.'/includes/header.php';
require_once __DIR__.'/includes/giftcode_helper.php';
$message='';$message_type='success';$restore=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!verify_csrf_token($_POST['csrf_token']??''))throw new InvalidArgumentException('Invalid CSRF token');
  require_once __DIR__.'/includes/admin_command_validator.php';
  $action=$_POST['action']??'';$requestId=$_POST['request_id']??null;
  $auditPatch=$_POST;unset($auditPatch['csrf_token'],$auditPatch['request_id']);
  if(in_array($action,['create_giftcode','update_giftcode'],true)){
   $gift=giftcode_validate($_POST['code']??null,$_POST['count_left']??-1,$_POST['expired']??null,$_POST['items_json']??'[]');$auditPatch['code']=$gift['code'];
   if($action==='create_giftcode')$mutation=fn()=>giftcode_insert($conn,$gift);
   else{
    $id=admin_integer($_POST['id']??0,1);$expected=$_POST['expected_fingerprint']??'';
    if(!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new InvalidArgumentException('Invalid edit fingerprint');
    $mutation=fn()=>giftcode_update($conn,$id,$gift,$expected);
   }
   $queued_command_id=admin_catalog_transaction($mutation,'RELOAD_GIFTCODE',['code'=>$gift['code'],'catalog_patch'=>$auditPatch],$requestId);
  }elseif($action==='delete_giftcode'){
   $id=admin_integer($_POST['id']??0,1);
   $queued_command_id=admin_catalog_transaction(fn()=>admin_execute_statement('DELETE FROM giftcode WHERE id=?','i',[$id]),'RELOAD_GIFTCODE',['catalog_patch'=>$auditPatch],$requestId);
  }else throw new InvalidArgumentException('Invalid action');
  $message='Đã lưu và xếp hàng lệnh #'.$queued_command_id.'; đang chờ máy chủ xử lý.';
 }catch(Throwable $e){
  $message='Không thể lưu: '.$e->getMessage();$message_type='danger';
  if(in_array($_POST['action']??'',['create_giftcode','update_giftcode'],true)){
   try{
    $items=giftcode_canonical_detail($_POST['items_json']??'[]');
    $restore=['id'=>($_POST['action']==='update_giftcode'?(int)($_POST['id']??0):null),'code'=>is_string($_POST['code']??null)?$_POST['code']:'','count_left'=>is_scalar($_POST['count_left']??null)?$_POST['count_left']:-1,'expired'=>is_string($_POST['expired']??null)?$_POST['expired']:'','items'=>$items,'fingerprint'=>is_string($_POST['expected_fingerprint']??null)?$_POST['expected_fingerprint']:'','request_id'=>is_string($_POST['request_id']??null)?$_POST['request_id']:null];
   }catch(Throwable $ignored){}
  }
 }
}
$res=$conn->query('SELECT * FROM giftcode ORDER BY id DESC LIMIT 50');$giftcodes=$res?$res->fetch_all(MYSQLI_ASSOC):[];
$itemIds=[];$optionIds=[];$giftBoot=[];
foreach($giftcodes as $gc){
 $items=json_decode($gc['detail'],true)?:[];
 foreach($items as $item){$itemIds[(int)($item['id']??0)]=true;foreach($item['options']??[] as $op)$optionIds[(int)($op['id']??0)]=true;}
 try{$fingerprint=giftcode_fingerprint($gc);}catch(Throwable $e){$fingerprint=null;}
 $giftBoot[]=['id'=>(int)$gc['id'],'code'=>$gc['code'],'count_left'=>(int)$gc['count_left'],'expired'=>$gc['expired'],'items'=>$items,'fingerprint'=>$fingerprint];
}
if($restore)foreach($restore['items'] as $item){$itemIds[$item['id']]=true;foreach($item['options'] as $op)$optionIds[$op['id']]=true;}
$optionIds[30]=true;$itemNames=[];$optionNames=[];
if($itemIds){$ids=implode(',',array_map('intval',array_keys($itemIds)));$rows=$conn->query('SELECT id,name,icon_id FROM item_template WHERE id IN ('.$ids.')');while($row=$rows->fetch_assoc())$itemNames[(int)$row['id']]=['name'=>$row['name'],'icon_url'=>get_item_icon_url($row['icon_id'])];}
if($optionIds){$ids=implode(',',array_map('intval',array_keys($optionIds)));$rows=$conn->query('SELECT id,name FROM item_option_template WHERE id IN ('.$ids.')');while($row=$rows->fetch_assoc())$optionNames[(int)$row['id']]=$row['name'];}
$defaultExpiry=date('Y-m-d\TH:i',strtotime('+1 year'));
function giftcode_html($value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?>
<style>
.giftcode-item{padding:10px;border:1px solid var(--border-color);border-radius:8px;font-size:13px;overflow-wrap:anywhere}.giftcode-item button{margin:6px 6px 0 0}.giftcode-option-row{display:grid;grid-template-columns:1fr 130px auto;gap:8px;align-items:center;margin-bottom:6px;font-size:12px}.giftcode-grid{display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:14px}.giftcode-search-results{max-height:180px;overflow:auto;display:flex;flex-wrap:wrap;gap:4px}.giftcode-note{font-size:12px;color:var(--text-secondary);margin:10px 0}@media(max-width:600px){.giftcode-grid{grid-template-columns:1fr}.giftcode-option-row{grid-template-columns:1fr 90px auto}}
</style>
<?php if($message): ?><div class="giftcode-item" style="margin-bottom:16px;color:var(--<?= $message_type ?>)" <?php if($message_type==='success'): ?>data-admin-command-id="<?= (int)$queued_command_id ?>"<?php endif; ?>><?= giftcode_html($message) ?></div><?php endif; ?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
 <div><h2>Danh sách Giftcode máy chủ</h2><p class="giftcode-note">Lưu quà và xếp hàng đồng bộ vào game qua Live Sync.</p></div>
 <button type="button" class="btn btn-primary" id="newGiftcode">Tạo Giftcode mới</button>
</div>
<div class="card"><div class="card-body" style="padding:0"><div class="table-responsive"><table class="table">
 <thead><tr><th>ID</th><th>Mã Giftcode</th><th>Lượt còn lại</th><th>Quà / options đã cấu hình</th><th>Hạn sử dụng</th><th>Thao tác</th></tr></thead><tbody>
 <?php if(!$giftcodes): ?><tr><td colspan="6">Chưa có Giftcode.</td></tr><?php endif; ?>
 <?php foreach($giftBoot as $gc): ?>
 <tr><td>#<?= (int)$gc['id'] ?></td><td><strong><?= giftcode_html($gc['code']) ?></strong></td>
 <td><?= $gc['count_left']===-1?'Vô hạn':number_format($gc['count_left']) ?></td>
 <td><?php foreach($gc['items'] as $item): $id=(int)$item['id']; ?><div class="giftcode-item">
  <?php if(isset($itemNames[$id])): ?><img src="<?= giftcode_html($itemNames[$id]['icon_url']) ?>" width="24" height="24" alt=""><?php endif; ?>
  <strong>#<?= $id ?> <?= giftcode_html($itemNames[$id]['name']??'Phần thưởng') ?> × <?= (int)$item['quantity'] ?></strong>
  <?php foreach($item['options']??[] as $op): $oid=(int)$op['id'];$param=(int)$op['param']; ?><div>#<?= $oid ?>: <?= giftcode_html(str_replace('#',(string)$param,$optionNames[$oid]??'Option')) ?> (param <?= $param ?>)</div><?php endforeach; ?>
 </div><?php endforeach; ?></td><td><?= giftcode_html($gc['expired']) ?></td>
 <td><button type="button" class="btn btn-sm btn-outline" data-edit-giftcode="<?= $gc['id'] ?>" <?= $gc['fingerprint']===null?'disabled':'' ?>>Sửa</button>
 <form method="POST" style="display:inline" onsubmit="return confirm('Xóa Giftcode này? Lịch sử nhận quà vẫn được giữ.');">
  <input type="hidden" name="csrf_token" value="<?= giftcode_html(get_csrf_token()) ?>"><input type="hidden" name="action" value="delete_giftcode"><input type="hidden" name="id" value="<?= $gc['id'] ?>">
  <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
 </form></td></tr><?php endforeach; ?>
 </tbody></table></div></div></div>
<div class="modal-backdrop" id="createGiftcodeModal"><div class="modal" style="max-width:820px">
 <div class="modal-header"><h3 id="giftcodeModalTitle">Tạo Giftcode</h3><button type="button" class="btn btn-sm btn-outline" onclick="closeModal('createGiftcodeModal')">Đóng</button></div>
 <form method="POST" id="giftcodeForm"><div class="modal-body">
  <input type="hidden" name="csrf_token" value="<?= giftcode_html(get_csrf_token()) ?>"><input type="hidden" name="action" value="create_giftcode"><input type="hidden" name="id"><input type="hidden" name="expected_fingerprint"><input type="hidden" name="request_id"><input type="hidden" name="items_json" value="[]">
  <div class="giftcode-grid"><div><label class="form-label">Mã code (giữ nguyên khi sửa)</label><div style="display:flex;gap:8px"><input type="text" name="code" class="form-control" maxlength="100" required><button type="button" id="randomCode" class="btn btn-outline">Random</button></div></div><div><label class="form-label">Lượt còn lại (-1 = vô hạn)</label><input type="number" name="count_left" class="form-control" min="-1" max="2147483647" required></div></div>
  <label class="form-label">Hạn sử dụng</label><input type="datetime-local" name="expired" class="form-control" step="1" required>
  <p class="giftcode-note">Người đã nhận có thể nhập lại để nhận món mới hoặc phần số lượng tăng thêm; phần bổ sung không trừ lượt. Giảm/xóa món không thu hồi quà và không cho nhận lại phần đã nhận. Với món đã phát, giữ nguyên mọi option cũ; có thể thêm option mới. Code có lịch sử cũ thiếu mốc quà sẽ bị từ chối sửa.</p>
  <div id="giftItemList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px"></div>
  <div class="giftcode-item"><h4>Soạn một món quà</h4>
   <div class="giftcode-grid"><div><label class="form-label">Tìm vật phẩm theo tên / ID</label><input id="searchItemInput" class="form-control" autocomplete="off"><div id="itemSearchResults" class="giftcode-search-results"></div></div><div><label class="form-label">ID (tiền: -1/-2/-3)</label><input id="selectedItemId" type="number" min="-3" max="32767" class="form-control"></div></div>
   <label class="form-label">Số lượng</label><input id="selectedItemQty" type="number" min="1" max="2147483647" value="1" class="form-control">
   <p class="giftcode-note">Option mặc định 30:0 có thể xóa. Server vẫn có thể thêm/ép option theo quy tắc vật phẩm; đây là bản xem trước cấu hình đã lưu.</p>
   <div id="optionRows"></div><label class="form-label">Chọn nhiều option theo tên / ID</label><input id="optionSearch" class="form-control" autocomplete="off"><div id="optionSearchResults" class="giftcode-search-results"></div>
   <label class="form-label">Nhập nhanh (thay toàn bộ options của món đang soạn)</label><div style="display:flex;gap:8px"><input id="quickOptions" class="form-control" placeholder="50:20 77:20 103:20 30:0"><button type="button" id="applyQuickOptions" class="btn btn-outline">Áp dụng</button></div>
   <div style="margin-top:12px"><button type="button" id="saveItem" class="btn btn-success">Thêm món</button><button type="button" id="cancelItem" class="btn btn-outline" hidden>Hủy sửa món</button></div>
  </div><p id="giftcodeError" role="alert" style="color:var(--danger);margin-top:10px"></p>
 </div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('createGiftcodeModal')">Đóng</button><button type="submit" class="btn btn-primary">Lưu & xếp hàng Live Sync</button></div></form>
</div></div>
<script>window.giftcodeBoot=<?= json_encode(['giftcodes'=>$giftBoot,'itemNames'=>(object)$itemNames,'optionNames'=>(object)$optionNames,'defaultExpiry'=>$defaultExpiry,'restore'=>$restore],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR) ?>;</script>
<script src="assets/js/giftcode-options.js"></script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
