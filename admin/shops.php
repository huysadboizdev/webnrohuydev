<?php
// admin/shops.php
$page_title = 'Quản Lý Shop NPC & Chợ Ký Gửi';
$active_tab = 'shops';
require_once __DIR__ . '/includes/header.php';

$message = '';
$message_type = 'success';

// Xử lý Cập nhật Shop Item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 try {
  if(!verify_csrf_token($_POST['csrf_token']??''))throw new InvalidArgumentException('Invalid CSRF token');
  require_once __DIR__.'/includes/admin_command_validator.php';
  $action=$_POST['action']??'';
  $requestId=$_POST['request_id']??null;
  $auditPatch=$_POST;unset($auditPatch['csrf_token'],$auditPatch['request_id']);
  if($action==='update_shop_price'){
   $id=admin_integer($_POST['shop_item_id']??0,1);$cost=admin_integer($_POST['cost']??-1);$sell=admin_integer($_POST['type_sell']??-1,0,3);
   $queued_command_id=admin_catalog_transaction(function()use($id,$cost,$sell){
    admin_validate_shop_price($id,$cost,$sell);
    admin_execute_statement('UPDATE item_shop SET cost = ?, type_sell = ? WHERE id = ?','iii',[$cost,$sell,$id]);
   },'RELOAD_SHOP',['item_shop_id'=>$id,'catalog_patch'=>$auditPatch],$requestId);
  }elseif($action==='delete_consign'){
   $payload=validate_admin_command('CONSIGN_CANCEL',['listing_id'=>$_POST['consign_id']??0]);
   $queued_command_id=dispatch_server_command('CONSIGN_CANCEL',$payload,$logged_username,$requestId);
   if(!$queued_command_id)throw new RuntimeException('Cannot enqueue cancellation');
  }else throw new InvalidArgumentException('Invalid action');
  $message='Đã lưu và xếp hàng lệnh #'.$queued_command_id.'; đang chờ máy chủ xử lý.';
 }catch(Throwable $e){$message='Không thể lưu: '.$e->getMessage();$message_type='danger';}
}

$tab = $_GET['tab'] ?? 'npc_shop';

// Danh sách Shop NPC
$shop_items = [];
if ($tab === 'npc_shop') {
    $q = trim($_GET['q'] ?? '');
    $where = "1=1";
    $params = [];
    $types = "";

    if ($q !== '') {
        $where = "(t.name LIKE ? OR s.temp_id = ?)";
        $params = ["%$q%", (int)$q];
        $types = "si";
    }

    $sql = "SELECT s.id, s.tab_id, s.temp_id, s.type_sell, s.cost, s.icon_spec, t.NAME AS name, t.icon_id, t.TYPE AS type 
            FROM item_shop s 
            LEFT JOIN item_template t ON s.temp_id = t.id 
            WHERE $where 
            ORDER BY s.id DESC LIMIT 40";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $shop_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Danh sách Ký Gửi
$consign_items = [];
if ($tab === 'consign') {
    $res = $conn->query("SELECT k.*, t.NAME AS name, t.icon_id FROM shop_ky_gui k LEFT JOIN item_template t ON k.item_id = t.id ORDER BY k.id DESC LIMIT 40");
    if ($res) $consign_items = $res->fetch_all(MYSQLI_ASSOC);
}

function get_currency_name($type) {
    switch ((int)$type) {
        case 0: return 'Vàng 🟡';
        case 1: return 'Ngọc Xanh 💎';
        case 2: return 'Hồng Ngọc 🔴';
        default: return 'Khác';
    }
}
?>

<?php if ($message): ?>
    <div style="background:var(--<?= $message_type ?>-light); border:1px solid var(--<?= $message_type ?>); color:var(--text-primary); padding:12px 18px; border-radius:var(--radius-md); margin-bottom:20px; font-weight:500;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Tabs Switcher -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div style="display:flex; gap:10px;">
        <a href="?tab=npc_shop" class="btn <?= $tab === 'npc_shop' ? 'btn-primary' : 'btn-outline' ?>">
            🛒 Shop NPC Trong Game
        </a>
        <a href="?tab=consign" class="btn <?= $tab === 'consign' ? 'btn-primary' : 'btn-outline' ?>">
            🏪 Chợ Ký Gửi Người Chơi
        </a>
    </div>
    <button type="button" class="btn btn-success" onclick="triggerLiveSync('RELOAD_SHOP', {})">
        ⚡ Live Reload Toàn Bộ Shop
    </button>
</div>

<?php if ($tab === 'npc_shop'): ?>
<!-- NPC Shop Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>🛒</span>
            <span>Vật Phẩm Bán Tại Shop NPC (`item_shop`)</span>
        </div>
        <form method="GET" style="display:flex; gap:8px;">
            <input type="hidden" name="tab" value="npc_shop">
            <input type="text" name="q" class="form-control" placeholder="Tìm tên đồ hoặc ID..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" style="width:220px;">
            <button type="submit" class="btn btn-primary">Tìm</button>
        </form>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Shop</th>
                        <th>Vật Phẩm</th>
                        <th>Tab Shop</th>
                        <th>Loại Tiền</th>
                        <th>Giá Bán</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($shop_items)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:var(--text-muted); padding:30px;">
                            Không có vật phẩm nào trong shop.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shop_items as $si): ?>
                        <tr>
                            <td>#<?= (int)$si['id'] ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="item-icon-box">
                                        <img src="<?= get_item_icon_url($si['icon_id'] ?? $si['temp_id']) ?>" alt="">
                                    </div>
                                    <div>
                                        <strong style="color:var(--text-primary); font-size:14px;"><?= htmlspecialchars($si['name'] ?: "Item #{$si['temp_id']}") ?></strong>
                                        <small style="display:block; color:var(--text-muted); font-size:11px;">Template ID: #<?= (int)$si['temp_id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-purple">Tab #<?= (int)$si['tab_id'] ?></span></td>
                            <td><span class="badge badge-primary"><?= get_currency_name($si['type_sell']) ?></span></td>
                            <td style="color:#fbbf24; font-weight:700;"><?= number_format((int)$si['cost']) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline" onclick='openShopModal(<?= json_encode($si) ?>)'>
                                    ✏️ Sửa Giá
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Sửa Giá Shop -->
<div class="modal-backdrop" id="shopModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700;">🛒 Sửa Giá Bán: <span id="modalShopItemName" style="color:var(--primary);"></span></h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('shopModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="update_shop_price">
                <input type="hidden" name="shop_item_id" id="modalShopItemId">

                <div class="form-group">
                    <label class="form-label">Loại Tiền Tệ:</label>
                    <select name="type_sell" id="selectShopTypeSell" class="form-control">
                        <option value="0">Vàng (Gold 🟡)</option>
                        <option value="1">Ngọc Xanh (Gem 💎)</option>
                        <option value="2">Hồng Ngọc (Ruby 🔴)</option>
                        <option value="3">Hồng Ngọc Shop Đặc Biệt (Ruby 🔴)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Giá Bán (Cost):</label>
                    <input type="number" name="cost" id="inputShopCost" class="form-control" required min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('shopModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu & Live Reload</button>
            </div>
        </form>
    </div>
</div>

<script>
function openShopModal(si) {
    document.getElementById('modalShopItemName').innerText = `${si.name || 'Item'} (#${si.id})`;
    document.getElementById('modalShopItemId').value = si.id;
    document.getElementById('selectShopTypeSell').value = si.type_sell ?? 0;
    document.getElementById('inputShopCost').value = si.cost ?? 0;
    openModal('shopModal');
}
</script>

<?php else: ?>
<!-- Consign Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>🏪</span>
            <span>Vật Phẩm Người Chơi Ký Gửi (`shop_ky_gui`)</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Ký Gửi</th>
                        <th>Player ID</th>
                        <th>Vật Phẩm</th>
                        <th>Giá Bán</th>
                        <th>Số Lượng</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($consign_items)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:var(--text-muted); padding:30px;">
                            Hiện không có vật phẩm nào đang ký gửi trên chợ.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($consign_items as $ci): ?>
                        <tr>
                            <td>#<?= (int)$ci['id'] ?></td>
                            <td>Player #<?= (int)($ci['player_id'] ?? 0) ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div class="item-icon-box">
                                        <img src="<?= get_item_icon_url($ci['icon_id'] ?? $ci['item_id']) ?>" alt="">
                                    </div>
                                    <span><?= htmlspecialchars($ci['name'] ?: "Item #{$ci['item_id']}") ?></span>
                                </div>
                            </td>
                            <td style="color:#fbbf24; font-weight:700;">
                                <?= $ci['gold'] > 0 ? number_format((int)$ci['gold']) . ' 🟡' : number_format((int)$ci['gem']) . ' 💎' ?>
                            </td>
                            <td>x<?= (int)($ci['quantity'] ?? 1) ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa vật phẩm này khỏi chợ?');">
                                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete_consign">
                                    <input type="hidden" name="consign_id" value="<?= $ci['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Gỡ Khỏi Chợ</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
