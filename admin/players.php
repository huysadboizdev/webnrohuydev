<?php
// admin/players.php
$page_title = 'Quản Lý Nhân Vật & Chỉ Số (Players)';
$active_tab = 'players';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/player_data_helper.php';

$message = '';
$message_type = 'success';

// Xử lý Cập nhật Chỉ Số
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new InvalidArgumentException('Invalid CSRF token');
        if (($_POST['action'] ?? '') !== 'update_stats') throw new InvalidArgumentException('Invalid action');
        require_once __DIR__.'/includes/admin_command_validator.php';
        $payload=validate_admin_command('UPDATE_STATS',$_POST);
        $id=dispatch_server_command('UPDATE_STATS',$payload,$logged_username,$_POST['request_id']??null);
        if(!$id)throw new RuntimeException('Cannot enqueue command');
        $message='Đã xếp hàng lệnh #'.$id.'; chờ máy chủ xử lý.';
        $queued_command_id=$id;
    } catch (Throwable $e) {$message='Không thể xếp hàng: '.$e->getMessage();$message_type='danger';}
}

$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$where = "1=1";
$params = [];
$types = "";

if ($search !== '') {
    $where = "(name LIKE ? OR id = ? OR account_id = ?)";
    $params = ["%$search%", (int)$search, (int)$search];
    $types = "sii";
}

$stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM player WHERE $where");
if (!empty($params)) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_rows = (int)$stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$stmt = $conn->prepare("SELECT id, account_id, name, gender, head, clan_id, data_point, data_inventory, data_location, pet, items_body, items_bag FROM player WHERE $where ORDER BY id DESC LIMIT ? OFFSET ?");
$types_page = $types . "ii";
$params_page = array_merge($params, [$limit, $offset]);
$stmt->bind_param($types_page, ...$params_page);
$stmt->execute();
$players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function get_planet_name($gender) {
    switch ((int)$gender) {
        case 0: return 'Trái Đất';
        case 1: return 'Namếc';
        case 2: return 'Xayda';
        default: return 'Khác';
    }
}
?>

<?php if ($message): ?>
    <div style="background:var(--<?= $message_type ?>-light); border:1px solid var(--<?= $message_type ?>); color:var(--text-primary); padding:12px 18px; border-radius:var(--radius-md); margin-bottom:20px; font-weight:500;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Search Card -->
<div class="card">
    <div class="card-body">
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
            <div style="flex:1; min-width:240px;">
                <input type="text" name="q" class="form-control" placeholder="Tìm theo Tên nhân vật, ID hoặc Account ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Tìm kiếm</button>
            <?php if ($search !== ''): ?>
                <a href="/webgoc2026/admin/players.php" class="btn btn-outline">Xóa lọc</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Player Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>⚡</span>
            <span>Danh Sách Nhân Vật (<?= number_format($total_rows) ?> kết quả)</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Nhân Vật</th>
                        <th>Hành Tinh</th>
                        <th>Vị Trí Bản Đồ</th>
                        <th>Sức Mạnh / Tiềm Năng</th>
                        <th>Tài Sản</th>
                        <th>Đệ Tử (Pet)</th>
                        <th style="text-align:right;">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($players)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">
                            Không tìm thấy nhân vật nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($players as $p): ?>
                        <?php
                            $dp = json_decode($p['data_point'], true) ?: [];
                            $di = json_decode($p['data_inventory'], true) ?: [];
                            $loc = json_decode($p['data_location'] ?? '[]', true) ?: [21, 300, 336];
                            $map_id = (int)($loc[0] ?? 21);
                            $map_name = get_map_name($map_id);

                            $power = $dp[1] ?? 0;
                            $tiem_nang = $dp[2] ?? 0;
                            $gold = $di[0] ?? 0;
                            $gem = $di[1] ?? 0;
                            $ruby = $di[2] ?? 0;

                            $pet_raw = json_decode($p['pet'] ?? '[]', true) ?: [];
                            $has_pet = !empty($pet_raw) && is_array($pet_raw) && count($pet_raw) >= 2;
                        ?>
                        <tr>
                            <td>#<?= (int)$p['id'] ?></td>
                            <td>
                                <strong style="color:var(--text-primary); font-size:14px;"><?= htmlspecialchars($p['name']) ?></strong>
                                <small style="display:block; color:var(--text-muted); font-size:11px;">Acc ID: #<?= (int)$p['account_id'] ?></small>
                            </td>
                            <td>
                                <span class="badge badge-purple"><?= get_planet_name($p['gender']) ?></span>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:600; color:#f59e0b;"><?= htmlspecialchars($map_name) ?></div>
                                <small style="color:var(--text-muted); font-size:11px;">Map #<?= $map_id ?> [X:<?= (int)($loc[1]??0) ?>, Y:<?= (int)($loc[2]??0) ?>]</small>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:700; color:#38bdf8;">⚡ <?= number_format($power) ?></div>
                                <small style="color:var(--text-muted); font-size:11px;">🌟 <?= number_format($tiem_nang) ?></small>
                            </td>
                            <td>
                                <div style="font-size:12px; color:#fbbf24; font-weight:600;"><?= number_format($gold) ?> vàng</div>
                                <div style="font-size:11px; color:#38bdf8;"><?= number_format($gem) ?> ngọc xanh | <?= number_format($ruby) ?> hồng ngọc</div>
                            </td>
                            <td>
                                <?php if ($has_pet): ?>
                                    <span class="badge badge-success" style="font-size:11px;">👶 Có đệ tử</span>
                                <?php else: ?>
                                    <span class="badge badge-outline" style="font-size:11px; opacity:0.6;">Chưa có</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="/webgoc2026/admin/player_detail.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-info" style="margin-right:4px;">
                                    🔍 Soi Toàn Diện
                                </a>
                                <button type="button" class="btn btn-sm btn-primary" onclick='openPlayerModal(<?= json_encode($p) ?>)'>
                                    ⚡ Sửa Nhanh
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div style="padding:16px 20px; display:flex; justify-content:center; gap:6px;">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?q=<?= urlencode($search) ?>&page=<?= $i ?>" 
                       class="btn btn-sm <?= $page === $i ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Chỉnh Sửa Chỉ Số & Tài Sản -->
<div class="modal-backdrop" id="playerModal">
    <div class="modal" style="max-width:680px;">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700;">⚡ Chỉnh Sửa Chỉ Số: <span id="modalPlayerName" style="color:var(--primary);"></span></h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('playerModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="update_stats">
                <input type="hidden" name="player_id" id="modalPlayerId">

                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">❤️ Chỉ Số Chiến Đấu (HP, KI, Sát Thương)</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label class="form-label">HP Gốc (Máu):</label>
                        <input type="number" name="hpg" id="inputHpg" class="form-control" required min="1">
                    </div>
                    <div>
                        <label class="form-label">KI Gốc (Thể lực/Nội lực):</label>
                        <input type="number" name="mpg" id="inputMpg" class="form-control" required min="1">
                    </div>
                    <div>
                        <label class="form-label">Sức Đánh Gốc (Damage):</label>
                        <input type="number" name="dameg" id="inputDameg" class="form-control" required min="1">
                    </div>
                    <div>
                        <label class="form-label">Giáp Gốc (Defense):</label>
                        <input type="number" name="defg" id="inputDefg" class="form-control" required min="0">
                    </div>
                    <div>
                        <label class="form-label">Chí Mạng Gốc (% Crit):</label>
                        <input type="number" name="critg" id="inputCritg" class="form-control" required min="0" max="100">
                    </div>
                    <div>
                        <label class="form-label">Giới Hạn Sức Mạnh (Limit):</label>
                        <input type="number" name="limit_power" id="inputLimitPower" class="form-control" required min="0">
                    </div>
                </div>

                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">🌟 Sức Mạnh & Tiềm Năng</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label class="form-label">Sức Mạnh (Power):</label>
                        <input type="number" name="power" id="inputPower" class="form-control" required min="0">
                    </div>
                    <div>
                        <label class="form-label">Tiềm Năng:</label>
                        <input type="number" name="tiem_nang" id="inputTiemNang" class="form-control" required min="0">
                    </div>
                </div>

                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">💰 Tiền Tệ Nhân Vật</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label class="form-label">Vàng (Gold):</label>
                        <input type="number" name="gold" id="inputGold" class="form-control" required min="0">
                    </div>
                    <div>
                        <label class="form-label">Ngọc Xanh (Gem):</label>
                        <input type="number" name="gem" id="inputGem" class="form-control" required min="0">
                    </div>
                    <div>
                        <label class="form-label">Hồng Ngọc (Ruby):</label>
                        <input type="number" name="ruby" id="inputRuby" class="form-control" required min="0">
                    </div>
                </div>

                <div style="background:rgba(245, 158, 11, 0.1); border:1px solid rgba(245, 158, 11, 0.3); padding:12px; border-radius:var(--radius-md);">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="kick_player" value="1" style="width:16px; height:16px;">
                        <span style="font-size:13px; color:#f59e0b; font-weight:600;">
                            ⚡ Bắn lệnh Live Kick (Đá nhân vật thoát game nếu đang online để nhận chỉ số mới ngay lập tức)
                        </span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('playerModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu Chỉ Số Mới</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPlayerModal(p) {
    document.getElementById('modalPlayerName').innerText = `${p.name} (#${p.id})`;
    document.getElementById('modalPlayerId').value = p.id;

    let dp = [];
    let di = [];
    try { dp = JSON.parse(p.data_point) || []; } catch(e){}
    try { di = JSON.parse(p.data_inventory) || []; } catch(e){}

    document.getElementById('inputLimitPower').value = dp[0] ?? 0;
    document.getElementById('inputPower').value = dp[1] ?? 0;
    document.getElementById('inputTiemNang').value = dp[2] ?? 0;
    document.getElementById('inputHpg').value = dp[5] ?? 1000;
    document.getElementById('inputMpg').value = dp[6] ?? 1000;
    document.getElementById('inputDameg').value = dp[7] ?? 100;
    document.getElementById('inputDefg').value = dp[8] ?? 0;
    document.getElementById('inputCritg').value = dp[9] ?? 0;

    document.getElementById('inputGold').value = di[0] ?? 0;
    document.getElementById('inputGem').value = di[1] ?? 0;
    document.getElementById('inputRuby').value = di[2] ?? 0;

    openModal('playerModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
