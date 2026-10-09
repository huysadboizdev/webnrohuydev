<?php
// admin/mobs.php
$page_title = 'Quản Lý Quái Vật & Tỉ Lệ EXP';
$active_tab = 'mobs';
require_once __DIR__ . '/includes/header.php';

$message = '';
$message_type = 'success';

// Xử lý Cập nhật Mobs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 try {
  if(!verify_csrf_token($_POST['csrf_token']??''))throw new InvalidArgumentException('Invalid CSRF token');
  require_once __DIR__.'/includes/admin_command_validator.php';
  $action=$_POST['action']??'';
  $requestId=$_POST['request_id']??null;
  $auditPatch=$_POST;unset($auditPatch['csrf_token'],$auditPatch['request_id']);
  if($action==='update_single_mob'){
   $id=admin_integer($_POST['mob_id']??-1,0,32767);
   $hp=admin_integer($_POST['hp']??0,1);$dame=admin_integer($_POST['percent_dame']??0);$tn=admin_integer($_POST['percent_tiem_nang']??0);$speed=admin_integer($_POST['speed']??0,0,127);
   if($id===117)$speed=0;
   $queued_command_id=admin_catalog_transaction(fn()=>admin_execute_statement('UPDATE mob_template SET hp = ?, percent_dame = ?, percent_tiem_nang = ?, speed = ? WHERE id = ?','iiiii',[$hp,$dame,$tn,$speed,$id]),'RELOAD_MOB',['mob_id'=>$id,'catalog_patch'=>$auditPatch],$requestId);
  }elseif($action==='bulk_multiplier'){
   $kind=$_POST['mult_type']??'';$factor=filter_var($_POST['mult_factor']??null,FILTER_VALIDATE_FLOAT);
   if(!in_array($kind,['hp','tn'],true)||$factor===false||$factor<=0||$factor>100)throw new InvalidArgumentException('Invalid multiplier');
   $column=$kind==='hp'?'hp':'percent_tiem_nang';
   $queued_command_id=admin_catalog_transaction(fn()=>admin_execute_statement("UPDATE mob_template SET $column = ROUND($column * ?)",'d',[$factor]),'RELOAD_MOB',['bulk'=>true,'catalog_patch'=>$auditPatch],$requestId);
  }else throw new InvalidArgumentException('Invalid action');
  $message='Đã lưu và xếp hàng lệnh #'.$queued_command_id.'; đang chờ máy chủ xử lý.';
 }catch(Throwable $e){$message='Không thể lưu: '.$e->getMessage();$message_type='danger';}
}

$search = trim($_GET['q'] ?? '');
$where = "1=1";
$params = [];
$types = "";

if ($search !== '') {
    $where = "(name LIKE ? OR id = ?)";
    $params = ["%$search%", (int)$search];
    $types = "si";
}

$stmt = $conn->prepare("SELECT id, TYPE AS type, NAME AS name, hp, range_move, speed, dart_Type AS dart_type, percent_dame, percent_tiem_nang FROM mob_template WHERE $where ORDER BY id ASC LIMIT 50");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$mobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php if ($message): ?>
    <div style="background:var(--<?= $message_type ?>-light); border:1px solid var(--<?= $message_type ?>); color:var(--text-primary); padding:12px 18px; border-radius:var(--radius-md); margin-bottom:20px; font-weight:500;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Quick Bulk Tool Card -->
<div class="card" style="border:1px solid rgba(245, 158, 11, 0.3);">
    <div class="card-header" style="background:rgba(245, 158, 11, 0.05);">
        <div class="card-title" style="color:#f59e0b;">
            <span>⚡</span>
            <span>Công Cụ Điều Chỉnh Hàng Loạt Quái Vật Toàn Máy Chủ</span>
        </div>
        <button type="button" class="btn btn-sm btn-primary" onclick="triggerLiveSync('RELOAD_MOB', {})">
            🔄 Live Reload Quái Ngay
        </button>
    </div>
    <div class="card-body">
        <form method="POST" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="action" value="bulk_multiplier">

            <div>
                <label class="form-label">Chọn Hạng Mục Nhân:</label>
                <select name="mult_type" class="form-control" style="width:200px;">
                    <option value="hp">Máu Quái (HP)</option>
                    <option value="tn">Tiềm Năng Nhận Được (%)</option>
                </select>
            </div>
            <div>
                <label class="form-label">Hệ Số Nhân (VD: 1.5, 2, 0.5):</label>
                <input type="number" step="0.1" name="mult_factor" class="form-control" value="2" min="0.1" max="50" style="width:140px;">
            </div>
            <button type="submit" class="btn btn-warning" onclick="return confirm('Thao tác này sẽ áp dụng lên TOÀN BỘ quái vật. Bạn có chắc không?');">
                Áp Dụng All Server
            </button>
        </form>
    </div>
</div>

<!-- Mob List Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>👾</span>
            <span>Danh Sách Quái Vật (`mob_template`)</span>
        </div>
        <form method="GET" style="display:flex; gap:8px;">
            <input type="text" name="q" class="form-control" placeholder="Tìm tên quái hoặc ID..." value="<?= htmlspecialchars($search) ?>" style="width:220px;">
            <button type="submit" class="btn btn-primary">Tìm</button>
        </form>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Quái</th>
                        <th>Máu (HP)</th>
                        <th>% Sát Thương</th>
                        <th>% Tiềm Năng</th>
                        <th>Tốc Độ</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($mobs)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--text-muted); padding:30px;">
                            Không tìm thấy quái vật nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mobs as $m): ?>
                        <tr>
                            <td>#<?= (int)$m['id'] ?></td>
                            <td><strong style="color:var(--text-primary);"><?= htmlspecialchars($m['name']) ?></strong></td>
                            <td style="color:#ef4444; font-weight:700;"><?= number_format((int)$m['hp']) ?></td>
                            <td><?= (int)$m['percent_dame'] ?>%</td>
                            <td style="color:#10b981; font-weight:700;"><?= (int)$m['percent_tiem_nang'] ?>%</td>
                            <td><?= (int)$m['speed'] ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline" onclick='openMobModal(<?= json_encode($m) ?>)'>
                                    ✏️ Chỉnh Sửa
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

<!-- Modal Sửa Quái -->
<div class="modal-backdrop" id="mobModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700;">👾 Chỉnh Sửa Quái: <span id="modalMobName" style="color:var(--primary);"></span></h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('mobModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="update_single_mob">
                <input type="hidden" name="mob_id" id="modalMobId">

                <div class="form-group">
                    <label class="form-label">Máu Quái (HP):</label>
                    <input type="number" name="hp" id="inputMobHp" class="form-control" required min="1">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label class="form-label">% Sát Thương (Dame):</label>
                        <input type="number" name="percent_dame" id="inputMobDame" class="form-control" required min="0">
                    </div>
                    <div>
                        <label class="form-label">% Tiềm Năng Rơi:</label>
                        <input type="number" name="percent_tiem_nang" id="inputMobTn" class="form-control" required min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tốc Độ Di Chuyển:</label>
                    <input type="number" name="speed" id="inputMobSpeed" class="form-control" required min="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('mobModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu & Live Reload</button>
            </div>
        </form>
    </div>
</div>

<script>
function openMobModal(m) {
    document.getElementById('modalMobName').innerText = `${m.name} (#${m.id})`;
    document.getElementById('modalMobId').value = m.id;
    document.getElementById('inputMobHp').value = m.hp;
    document.getElementById('inputMobDame').value = m.percent_dame;
    document.getElementById('inputMobTn').value = m.percent_tiem_nang;
    document.getElementById('inputMobSpeed').value = m.speed;
    openModal('mobModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
