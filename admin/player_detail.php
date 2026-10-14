<?php
// admin/player_detail.php
// Trung tâm Quản trị Chi Tiết 100% Thông tin & Chỉnh sửa Dữ liệu Thật của Người chơi
$page_title = 'Chi Tiết Người Chơi & Soi Dữ Liệu';
$active_tab = 'players';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/player_data_helper.php';
$pet_skill_result=$conn->query('SELECT id, NAME AS name, nclass_id AS nclass_id FROM skill_template ORDER BY id');
$pet_skill_templates=$pet_skill_result?$pet_skill_result->fetch_all(MYSQLI_ASSOC):[];

$player_id = (int)($_GET['id'] ?? 0);
if ($player_id <= 0) {
    echo '<div class="card"><div class="card-body" style="text-align:center; padding:50px;"><h2>Không tìm thấy nhân vật</h2><a href="' . rtrim(admin_url(''), '/') . '/admin/players.php" class="btn btn-primary" style="margin-top:15px;">Quay lại danh sách</a></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

// Truy vấn thông tin người chơi & tài khoản liên kết
$stmt = $conn->prepare("SELECT p.*, a.username, a.vnd, a.tongnap, a.thoi_vang, a.ban AS acc_ban, a.active AS acc_active 
                        FROM player p 
                        LEFT JOIN account a ON p.account_id = a.id 
                        WHERE p.id = ? LIMIT 1");
$stmt->bind_param("i", $player_id);
$stmt->execute();
$player = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$player) {
    echo '<div class="card"><div class="card-body" style="text-align:center; padding:50px;"><h2>Nhân vật không tồn tại trong CSDL</h2><a href="' . rtrim(admin_url(''), '/') . '/admin/players.php" class="btn btn-primary" style="margin-top:15px;">Quay lại danh sách</a></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

// Giải mã dữ liệu
$dp = json_decode($player['data_point'] ?? '[]', true) ?: [];
$di = json_decode($player['data_inventory'] ?? '[]', true) ?: [];
$loc = json_decode($player['data_location'] ?? '[]', true) ?: [21, 300, 336];
$task_raw = json_decode($player['data_task'] ?? '[]', true) ?: [0, 0, 0];
$magic_tree = json_decode($player['data_magic_tree'] ?? '[]', true) ?: [1, 5, 0];

$map_id = (int)($loc[0] ?? 21);
$map_x = (int)($loc[1] ?? 300);
$map_y = (int)($loc[2] ?? 336);
$map_name = get_map_name($map_id);

$current_task_id = (int)($task_raw[0] ?? 0);
$task_info = get_task_info($current_task_id);

// Giải mã Items
$items_body = decode_inventory_items($player['items_body'] ?? '[]');
$items_bag = decode_inventory_items($player['items_bag'] ?? '[]');
$items_box = decode_inventory_items($player['items_box'] ?? '[]');

// Giải mã Pet
$pet_data = decode_player_pet($player['pet'] ?? '[]');

// Giải mã Skills
$raw_skills = json_decode($player['skills'] ?? '[]', true) ?: [];
$player_skills = [];
foreach ($raw_skills as $s_slot) {
    $s_arr = is_string($s_slot) ? json_decode($s_slot, true) : $s_slot;
    if (is_array($s_arr) && count($s_arr) >= 2) {
        $sk_id = (int)$s_arr[0];
        $sk_point = (int)$s_arr[1];
        $player_skills[] = [
            'id' => $sk_id,
            'point' => $sk_point,
            'name' => get_skill_name($sk_id)
        ];
    }
}

// Lấy danh sách toàn bộ nhiệm vụ để làm dropdown nhảy cóc
$all_tasks_res = $conn->query("SELECT id, NAME AS name FROM task_main_template ORDER BY id ASC");
$all_tasks = $all_tasks_res ? $all_tasks_res->fetch_all(MYSQLI_ASSOC) : [];
?>

<!-- PROFILE SUMMARY HERO HEADER -->
<div class="card" style="border: 1px solid rgba(59, 130, 246, 0.3); background: linear-gradient(135deg, rgba(17,24,39,0.95) 0%, rgba(30,41,59,0.85) 100%);">
    <div class="card-body">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
            <div style="display:flex; align-items:center; gap:16px;">
                <div style="width:68px; height:68px; background:var(--bg-input); border:2px solid var(--primary); border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:32px;">
                    <?= $player['gender'] == 0 ? '🥋' : ($player['gender'] == 1 ? '🐌' : '🐒') ?>
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                        <h2 style="font-size:22px; font-weight:800; color:#fff;"><?= htmlspecialchars($player['name']) ?></h2>
                        <span class="badge badge-primary">ID: #<?= (int)$player['id'] ?></span>
                        <span class="badge badge-purple"><?= $player['gender'] == 0 ? 'Trái Đất' : ($player['gender'] == 1 ? 'Namếc' : 'Xayda') ?></span>
                        <?php if ($player['acc_ban'] == 1): ?>
                            <span class="badge badge-danger">ĐANG BỊ KHÓA</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:13px; color:var(--text-secondary); display:flex; gap:16px; flex-wrap:wrap;">
                        <span>👤 Tài khoản: <strong style="color:#38bdf8;"><?= htmlspecialchars($player['username'] ?? 'N/A') ?></strong> (Acc #<?= (int)$player['account_id'] ?>)</span>
                        <span>📍 Vị trí: <strong style="color:#f59e0b;"><?= htmlspecialchars($map_name) ?> (Map <?= $map_id ?>)</strong> [X: <?= $map_x ?>, Y: <?= $map_y ?>]</span>
                    </div>
                </div>
            </div>

            <!-- Emergency Toolbar -->
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <button type="button" class="btn btn-primary" onclick="openTopupModal()" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); border:none; box-shadow:0 0 16px rgba(16,185,129,0.4); font-weight:700;">
                    💳 Nạp Vào Người Chơi (Live)
                </button>
                <button type="button" class="btn btn-danger" onclick="triggerAction('kick_live')">
                    ⚡ Live Kick
                </button>
                <button type="button" class="btn btn-warning" onclick="triggerAction('warp_home')">
                    🏠 Về Làng (Reset Map)
                </button>
                <button type="button" class="btn btn-success" onclick="triggerAction('heal_full')">
                    ❤️ Hồi Đầy Máu/KI
                </button>
                <button type="button" class="btn btn-outline" onclick="triggerAction('upgrade_magic_tree')">
                    🌱 Đậu Thần Cấp 10
                </button>
            </div>
        </div>

        <!-- Metric Badges Row -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:12px; margin-top:20px; padding-top:16px; border-top:1px solid var(--border-color);">
            <div style="background:var(--bg-card); padding:10px 14px; border-radius:8px; border:1px solid var(--border-color);">
                <small style="color:var(--text-muted); font-size:11px; text-transform:uppercase;">⚡ Sức Mạnh</small>
                <div style="font-size:16px; font-weight:800; color:#38bdf8;"><?= number_format($dp[1] ?? 0) ?></div>
            </div>
            <div style="background:var(--bg-card); padding:10px 14px; border-radius:8px; border:1px solid var(--border-color);">
                <small style="color:var(--text-muted); font-size:11px; text-transform:uppercase;">🌟 Tiềm Năng</small>
                <div style="font-size:16px; font-weight:800; color:#a855f7;"><?= number_format($dp[2] ?? 0) ?></div>
            </div>
            <div style="background:var(--bg-card); padding:10px 14px; border-radius:8px; border:1px solid var(--border-color);">
                <small style="color:var(--text-muted); font-size:11px; text-transform:uppercase;">💰 Vàng</small>
                <div style="font-size:16px; font-weight:800; color:#fbbf24;"><?= number_format($di[0] ?? 0) ?> 🟡</div>
            </div>
            <div style="background:var(--bg-card); padding:10px 14px; border-radius:8px; border:1px solid var(--border-color);">
                <small style="color:var(--text-muted); font-size:11px; text-transform:uppercase;">💎 Ngọc Xanh</small>
                <div style="font-size:16px; font-weight:800; color:#06b6d4;"><?= number_format($di[1] ?? 0) ?> 💎</div>
            </div>
            <div style="background:var(--bg-card); padding:10px 14px; border-radius:8px; border:1px solid var(--border-color);">
                <small style="color:var(--text-muted); font-size:11px; text-transform:uppercase;">🔴 Hồng Ngọc</small>
                <div style="font-size:16px; font-weight:800; color:#ec4899;"><?= number_format($di[2] ?? 0) ?> 🔴</div>
            </div>
        </div>
    </div>
</div>

<!-- TAB NAVIGATION -->
<div style="display:flex; gap:8px; margin-bottom:20px; overflow-x:auto; padding-bottom:6px;">
    <button type="button" class="btn tab-btn btn-primary" onclick="switchTab('tabStats')">📊 Chỉ Số & Sức Mạnh</button>
    <button type="button" class="btn tab-btn btn-outline" onclick="switchTab('tabBody')">🥋 Đang Mặc (Body)</button>
    <button type="button" class="btn tab-btn btn-outline" onclick="switchTab('tabBag')">🎒 Hành Trang & Rương</button>
    <button type="button" class="btn tab-btn btn-outline" onclick="switchTab('tabPet')">👶 Đệ Tử (Pet)</button>
    <button type="button" class="btn tab-btn btn-outline" onclick="switchTab('tabSkills')">📜 Kỹ Năng & Nhiệm Vụ</button>
    <button type="button" class="btn tab-btn btn-outline" onclick="switchTab('tabAccount')">👑 Tài Khoản Liên Kết</button>
</div>

<!-- ==================== TAB 1: CHỈ SỐ & SỨC MẠNH ==================== -->
<div id="tabStats" class="tab-pane">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><span>📊</span><span>Chỉnh Sửa Chỉ Số Nhân Vật (Lưu Trực Tiếp Vào MySQL)</span></div>
        </div>
        <div class="card-body">
            <form id="formStats" onsubmit="saveStats(event)">
                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">❤️ Thuộc Tính Cơ Bản & Chiến Đấu</h4>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:20px;">
                    <div>
                        <label class="form-label">Tên Nhân Vật:</label>
                        <input type="text" id="statName" class="form-control" value="<?= htmlspecialchars($player['name']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Hành Tinh (Gender):</label>
                        <select id="statGender" class="form-control">
                            <option value="0" <?= $player['gender'] == 0 ? 'selected' : '' ?>>Trái Đất (0)</option>
                            <option value="1" <?= $player['gender'] == 1 ? 'selected' : '' ?>>Namếc (1)</option>
                            <option value="2" <?= $player['gender'] == 2 ? 'selected' : '' ?>>Xayda (2)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">HP Gốc (Máu):</label>
                        <input type="number" id="statHpg" class="form-control" value="<?= (int)($dp[5] ?? 1000) ?>" required min="1">
                    </div>
                    <div>
                        <label class="form-label">KI Gốc (Nội lực):</label>
                        <input type="number" id="statMpg" class="form-control" value="<?= (int)($dp[6] ?? 1000) ?>" required min="1">
                    </div>
                    <div>
                        <label class="form-label">Sức Đánh Gốc (Damage):</label>
                        <input type="number" id="statDameg" class="form-control" value="<?= (int)($dp[7] ?? 100) ?>" required min="1">
                    </div>
                    <div>
                        <label class="form-label">Giáp Gốc (Defense):</label>
                        <input type="number" id="statDefg" class="form-control" value="<?= (int)($dp[8] ?? 0) ?>" required min="0">
                    </div>
                    <div>
                        <label class="form-label">Chí Mạng Gốc (%):</label>
                        <input type="number" id="statCritg" class="form-control" value="<?= (int)($dp[9] ?? 0) ?>" required min="0" max="100">
                    </div>
                    <div>
                        <label class="form-label">Giới Hạn Sức Mạnh (Limit):</label>
                        <input type="number" id="statLimitPower" class="form-control" value="<?= (int)($dp[0] ?? 0) ?>" required min="0">
                    </div>
                </div>

                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">🌟 Sức Mạnh & Tiềm Năng</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
                    <div>
                        <label class="form-label">Sức Mạnh (Power):</label>
                        <input type="number" id="statPower" class="form-control" value="<?= htmlspecialchars((string)($dp[1] ?? 0), ENT_QUOTES) ?>" required min="0">
                    </div>
                    <div>
                        <label class="form-label">Tiềm Năng (TNSM):</label>
                        <input type="number" id="statTiemNang" class="form-control" value="<?= htmlspecialchars((string)($dp[2] ?? 0), ENT_QUOTES) ?>" required min="0">
                    </div>
                </div>

                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">💰 Tiền Tệ Nhân Vật</h4>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:24px;">
                    <div>
                        <label class="form-label">Vàng (Gold 🟡):</label>
                        <input type="number" id="statGold" class="form-control" value="<?= (int)($di[0] ?? 0) ?>" required min="0">
                    </div>
                    <div>
                        <label class="form-label">Ngọc Xanh (Gem 💎):</label>
                        <input type="number" id="statGem" class="form-control" value="<?= (int)($di[1] ?? 0) ?>" required min="0">
                    </div>
                    <div>
                        <label class="form-label">Hồng Ngọc (Ruby 🔴):</label>
                        <input type="number" id="statRuby" class="form-control" value="<?= (int)($di[2] ?? 0) ?>" required min="0">
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px solid var(--border-color); padding-top:16px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" id="statKickLive" style="width:18px; height:18px;">
                        <span style="font-weight:600; color:#f59e0b;">⚡ Tự động Live Kick nhân vật nếu đang online (Chống game server ghi đè)</span>
                    </label>
                    <button type="submit" class="btn btn-primary" style="padding:10px 28px;">💾 Lưu Thay Đổi Vào SQL</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== TAB 2: TRANG BỊ ĐANG MẶC (BODY) ==================== -->
<div id="tabBody" class="tab-pane" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><span>🥋</span><span>Trang Bị Nhân Vật Đang Mặc (`items_body`)</span></div>
        </div>
        <div class="card-body">
            <p style="color:var(--text-secondary); margin-bottom:16px; font-size:13px;">
                Dưới đây là danh sách toàn bộ trang bị nhân vật đang mặc trên người. Bạn có thể xem hình ảnh thực tế, các dòng thuộc tính hoặc xóa món đồ lỗi.
            </p>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:14px;">
                <?php 
                $body_slot_names = [0 => 'Áo', 1 => 'Quần', 2 => 'Găng tay', 3 => 'Giày', 4 => 'Rada', 5 => 'Cải trang', 6 => 'Phụ kiện', 7 => 'Linh thú', 8 => 'Bông tai', 9 => 'Danh hiệu'];
                foreach ($items_body as $slot_idx => $item): 
                    $slot_label = $body_slot_names[$slot_idx] ?? "Slot #$slot_idx";
                ?>
                    <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:14px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <span class="badge badge-purple"><?= $slot_label ?></span>
                                <small style="color:var(--text-muted);">Slot <?= $slot_idx ?></small>
                            </div>
                            <?php if ($item['empty']): ?>
                                <div style="display:flex; align-items:center; gap:10px; color:var(--text-muted); padding:10px 0;">
                                    <div class="item-icon-box" style="border-style:dashed;">⚪</div>
                                    <span>Trống (Không trang bị)</span>
                                </div>
                            <?php else: ?>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                                    <div class="item-icon-box" style="width:44px; height:44px;">
                                        <img src="<?= $item['icon_url'] ?>" alt="" style="max-width:38px; max-height:38px;">
                                    </div>
                                    <div>
                                        <strong style="color:#f8fafc; font-size:14px;"><?= htmlspecialchars($item['name']) ?></strong>
                                        <div style="font-size:11px; color:var(--text-muted);">ID: #<?= $item['id'] ?> | Số lượng: x<?= $item['quantity'] ?></div>
                                    </div>
                                </div>
                                <div style="background:var(--bg-card); padding:8px 10px; border-radius:6px; font-size:12px; margin-bottom:10px;">
                                    <?php if (empty($item['options'])): ?>
                                        <span style="color:var(--text-muted);">Không có thuộc tính</span>
                                    <?php else: ?>
                                        <?php foreach ($item['options'] as $opt): ?>
                                            <div style="color:#10b981;">• <?= htmlspecialchars($opt['text']) ?></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!$item['empty']): ?>
                            <div style="display:flex; justify-content:flex-end;">
                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteItem('body', <?= $slot_idx ?>)">
                                    🗑️ Tháo / Xóa
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TAB 3: HÀNH TRANG & RƯƠNG ĐỒ ==================== -->
<div id="tabBag" class="tab-pane" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><span>🎒</span><span>Hành Trang (`items_bag`) & Rương Đồ (`items_box`)</span></div>
            <button type="button" class="btn btn-primary" onclick="openGiftModal()">
                🎁 Tặng Quà / Vật Phẩm Cho Người Chơi
            </button>
        </div>
        <div class="card-body">
            <h4 style="font-size:14px; font-weight:700; color:#38bdf8; margin-bottom:12px;">🎒 Hành Trang (Túi Đồ Nhân Vật)</h4>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(56px, 1fr)); gap:8px; margin-bottom:28px;">
                <?php foreach ($items_bag as $slot_idx => $item): ?>
                    <?php if ($item['empty']): ?>
                        <div style="width:56px; height:56px; background:var(--bg-main); border:1px dashed var(--border-color); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:11px;" title="Ô trống <?= $slot_idx ?>">
                            <?= $slot_idx ?>
                        </div>
                    <?php else: ?>
                        <div class="item-slot-card" style="width:56px; height:56px; background:var(--bg-input); border:1px solid var(--border-color); border-radius:8px; position:relative; cursor:pointer; display:flex; align-items:center; justify-content:center;" onclick='showItemDetail(<?= json_encode($item) ?>, "bag")' title="<?= htmlspecialchars($item['name']) ?>">
                            <img src="<?= $item['icon_url'] ?>" style="max-width:40px; max-height:40px;" alt="">
                            <?php if ($item['quantity'] > 1): ?>
                                <span style="position:absolute; bottom:2px; right:4px; font-size:11px; font-weight:800; color:#fff; text-shadow:0 1px 2px #000;">
                                    <?= $item['quantity'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <h4 style="font-size:14px; font-weight:700; color:#fbbf24; margin-bottom:12px;">📦 Rương Đồ Tại Nhà (`items_box`)</h4>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(56px, 1fr)); gap:8px;">
                <?php foreach ($items_box as $slot_idx => $item): ?>
                    <?php if ($item['empty']): ?>
                        <div style="width:56px; height:56px; background:var(--bg-main); border:1px dashed var(--border-color); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:11px;" title="Rương trống <?= $slot_idx ?>">
                            <?= $slot_idx ?>
                        </div>
                    <?php else: ?>
                        <div class="item-slot-card" style="width:56px; height:56px; background:var(--bg-input); border:1px solid var(--border-color); border-radius:8px; position:relative; cursor:pointer; display:flex; align-items:center; justify-content:center;" onclick='showItemDetail(<?= json_encode($item) ?>, "box")' title="<?= htmlspecialchars($item['name']) ?>">
                            <img src="<?= $item['icon_url'] ?>" style="max-width:40px; max-height:40px;" alt="">
                            <?php if ($item['quantity'] > 1): ?>
                                <span style="position:absolute; bottom:2px; right:4px; font-size:11px; font-weight:800; color:#fff; text-shadow:0 1px 2px #000;">
                                    <?= $item['quantity'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TAB 4: ĐỆ TỬ (PET STUDIO) ==================== -->
<div id="tabPet" class="tab-pane" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><span>👶</span><span>Quản Lý Đệ Tử (Pet Studio)</span></div>
        </div>
        <div class="card-body">
            <?php if (!$pet_data || !$pet_data['has_pet']): ?>
                <div style="text-align:center; padding:30px; background:var(--bg-main); border-radius:var(--radius-md); border:1px dashed var(--border-color);">
                    <p style="color:var(--text-secondary); margin-bottom:14px;">Người chơi này hiện chưa có Đệ tử.</p>
                    <button type="button" class="btn btn-success" onclick="openCreatePetModal()">
                        👶 Tạo Đệ Tử Mới Cho Người Chơi
                    </button>
                </div>
            <?php else: ?>
                <form id="formPet" onsubmit="savePet(event)">
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:20px;">
                        <div>
                            <label class="form-label">Tên Đệ Tử:</label>
                            <input type="text" id="petName" class="form-control" value="<?= htmlspecialchars($pet_data['name'], ENT_QUOTES) ?>" readonly>
                        </div>
                        <div>
                            <label class="form-label">Loại Đệ Tử:</label>
                            <select id="petType" class="form-control" onchange="updatePetNamePreview()">
                                <option value="0" <?= $pet_data['type'] == 0 ? 'selected' : '' ?>>Đệ Tử Thường (0)</option>
                                <option value="1" <?= $pet_data['type'] == 1 ? 'selected' : '' ?>>Đệ Tử Mabư (1)</option>
                                <option value="2" <?= $pet_data['type'] == 2 ? 'selected' : '' ?>>Uub (2)</option>
                                <option value="3" <?= $pet_data['type'] == 3 ? 'selected' : '' ?>>Kid Beerus (3)</option>
                                <option value="4" <?= $pet_data['type'] == 4 ? 'selected' : '' ?>>Kid Jiren (4)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Sức Mạnh Đệ Tử:</label>
                            <input type="number" id="petPower" class="form-control" value="<?= htmlspecialchars((string)$pet_data['power'], ENT_QUOTES) ?>" required min="0">
                        </div>
                        <div>
                            <label class="form-label">Tiềm Năng Đệ Tử:</label>
                            <input type="number" id="petTiemNang" class="form-control" value="<?= htmlspecialchars((string)$pet_data['tiem_nang'], ENT_QUOTES) ?>" required min="0">
                        </div>
                        <div>
                            <label class="form-label">HP Gốc Đệ Tử:</label>
                            <input type="number" id="petHpg" class="form-control" value="<?= (int)$pet_data['hpg'] ?>" required min="1">
                        </div>
                        <div>
                            <label class="form-label">KI Gốc Đệ Tử:</label>
                            <input type="number" id="petMpg" class="form-control" value="<?= (int)$pet_data['mpg'] ?>" required min="1">
                        </div>
                        <div>
                            <label class="form-label">Sức Đánh Gốc Đệ Tử:</label>
                            <input type="number" id="petDameg" class="form-control" value="<?= (int)$pet_data['dameg'] ?>" required min="1">
                        </div>
                    </div>

                    <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">⚡ Kỹ Năng Đệ Tử (Chọn Chiêu Thức)</h4>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:24px;">
                        <div>
                            <label class="form-label">Chiêu 1 (Cơ bản):</label>
                            <select id="petSkill1" class="form-control">
                                <?php $selected_skill = $pet_data['skills'][0]['skill_id'] ?? -1; ?>
                                <option value="-1" <?= $selected_skill === -1 ? 'selected' : '' ?>>Chưa mở (-1)</option>
                                <?php foreach ($pet_skill_templates as $skill): ?>
                                <option value="<?= (int)$skill['id'] ?>" <?= (int)$skill['id'] === $selected_skill ? 'selected' : '' ?>><?= htmlspecialchars($skill['name']) ?> (#<?= (int)$skill['id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Chiêu 2:</label>
                            <select id="petSkill2" class="form-control">
                                <?php $selected_skill = $pet_data['skills'][1]['skill_id'] ?? -1; ?>
                                <option value="-1" <?= $selected_skill === -1 ? 'selected' : '' ?>>Chưa mở (-1)</option>
                                <?php foreach ($pet_skill_templates as $skill): ?>
                                <option value="<?= (int)$skill['id'] ?>" <?= (int)$skill['id'] === $selected_skill ? 'selected' : '' ?>><?= htmlspecialchars($skill['name']) ?> (#<?= (int)$skill['id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Chiêu 3:</label>
                            <select id="petSkill3" class="form-control">
                                <?php $selected_skill = $pet_data['skills'][2]['skill_id'] ?? -1; ?>
                                <option value="-1" <?= $selected_skill === -1 ? 'selected' : '' ?>>Chưa mở (-1)</option>
                                <?php foreach ($pet_skill_templates as $skill): ?>
                                <option value="<?= (int)$skill['id'] ?>" <?= (int)$skill['id'] === $selected_skill ? 'selected' : '' ?>><?= htmlspecialchars($skill['name']) ?> (#<?= (int)$skill['id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Chiêu 4 (Đặc biệt):</label>
                            <select id="petSkill4" class="form-control">
                                <?php $selected_skill = $pet_data['skills'][3]['skill_id'] ?? -1; ?>
                                <option value="-1" <?= $selected_skill === -1 ? 'selected' : '' ?>>Chưa mở (-1)</option>
                                <?php foreach ($pet_skill_templates as $skill): ?>
                                <option value="<?= (int)$skill['id'] ?>" <?= (int)$skill['id'] === $selected_skill ? 'selected' : '' ?>><?= htmlspecialchars($skill['name']) ?> (#<?= (int)$skill['id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Lưu Chỉ Số Đệ Tử</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==================== TAB 5: KỸ NĂNG & NHIỆM VỤ ==================== -->
<div id="tabSkills" class="tab-pane" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><span>📜</span><span>Kỹ Năng & Nhiệm Vụ Nhân Vật</span></div>
        </div>
        <div class="card-body">
            <!-- Nhiệm vụ hiện tại -->
            <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:16px; margin-bottom:24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                    <div>
                        <span class="badge badge-warning">Nhiệm Vụ Hiện Tại</span>
                        <h3 style="font-size:18px; font-weight:700; color:#f59e0b; margin-top:4px;">
                            #<?= $task_info['id'] ?> - <?= htmlspecialchars($task_info['name']) ?>
                        </h3>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-sm btn-success" onclick="completeCurrentTask(<?= $task_info['id'] + 1 ?>)">
                            ✅ Hoàn Thành Nhiệm Vụ Này
                        </button>
                    </div>
                </div>
                <p style="color:var(--text-secondary); font-size:13px; line-height:1.6; margin-bottom:14px;">
                    <?= nl2br(htmlspecialchars($task_info['detail'])) ?>
                </p>

                <!-- Dropdown Nhảy cóc nhiệm vụ -->
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <label style="font-size:13px; font-weight:600;">Nhảy cóc sang nhiệm vụ khác:</label>
                    <select id="selectTargetTask" class="form-control" style="max-width:320px;">
                        <?php foreach ($all_tasks as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['id'] == $task_info['id'] ? 'selected' : '' ?>>
                                #<?= $t['id'] ?> - <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-warning" onclick="jumpToTask()">Chuyển Nhiệm Vụ</button>
                </div>
            </div>

            <!-- Danh sách Kỹ năng nhân vật -->
            <h4 style="font-size:14px; font-weight:700; color:#38bdf8; margin-bottom:12px;">⚡ Danh Sách 8 Kỹ Năng Nhân Vật</h4>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:12px;">
                <?php foreach ($player_skills as $sk): ?>
                    <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px; padding:12px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="color:#fff;"><?= htmlspecialchars($sk['name']) ?></strong>
                            <div style="font-size:12px; color:var(--text-muted);">Skill ID: #<?= $sk['id'] ?></div>
                        </div>
                        <span class="badge badge-primary">Cấp <?= $sk['point'] ?> / 7</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TAB 6: TÀI KHOẢN LIÊN KẾT ==================== -->
<div id="tabAccount" class="tab-pane" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><span>👑</span><span>Quản Trị Tài Khoản Liên Kết (`account`)</span></div>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
                <div>
                    <label class="form-label">Tên Đăng Nhập:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($player['username'] ?? '') ?>" readonly>
                </div>
                <div>
                    <label class="form-label">Số Dư VND:</label>
                    <input type="text" class="form-control" value="<?= number_format($player['vnd'] ?? 0) ?>đ" readonly>
                </div>
                <div>
                    <label class="form-label">Thỏi Vàng Tài Khoản:</label>
                    <input type="text" class="form-control" value="<?= number_format($player['thoi_vang'] ?? 0) ?>" readonly>
                </div>
                <div>
                    <label class="form-label">Tổng Nạp Tích Lũy:</label>
                    <input type="text" class="form-control" value="<?= number_format($player['tongnap'] ?? 0) ?>đ" readonly>
                </div>
            </div>

            <!-- Form Đổi Mật Khẩu -->
            <form onsubmit="changeAccPassword(event)" style="border-top:1px solid var(--border-color); padding-top:16px;">
                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">🔑 Đổi Mật Khẩu Tài Khoản</h4>
                <div style="display:flex; gap:10px; max-width:400px;">
                    <input type="text" id="inputNewPass" class="form-control" placeholder="Nhập mật khẩu mới..." required minlength="4">
                    <button type="submit" class="btn btn-warning">Đổi Pass</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TẶNG QUÀ TRỰC TIẾP (GIFT ITEM) -->
<div class="modal-backdrop" id="giftModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700;">🎁 Tặng Vật Phẩm Trực Tiếp Vào Túi/Rương</h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('giftModal')">✕</button>
        </div>
        <form onsubmit="submitGiftItem(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Vị trí nhận đồ:</label>
                    <select id="giftTarget" class="form-control">
                        <option value="bag">🎒 Hành trang (Túi đồ)</option>
                        <option value="box">📦 Rương đồ tại nhà</option>
                    </select>
                </div>

                <div class="form-group" style="position:relative;">
                    <label class="form-label">Tìm kiếm vật phẩm:</label>
                    <input type="text" id="giftItemSearch" class="form-control" placeholder="Gõ tên vật phẩm (VD: Thỏi vàng, Bông tai, Giày thần...)" oninput="searchGiftItems(this.value)">
                    <div id="giftSearchResults" style="display:none; position:absolute; z-index:1200; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-md); max-height:220px; overflow-y:auto; width:100%; box-shadow:var(--shadow-lg);"></div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label class="form-label">ID Vật Phẩm Đã Chọn:</label>
                        <input type="number" id="giftItemId" class="form-control" required readonly placeholder="ID đồ">
                    </div>
                    <div>
                        <label class="form-label">Số Lượng:</label>
                        <input type="number" id="giftItemQty" class="form-control" value="1" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Thuộc Tính Option (Mặc định: Không thể giao dịch):</label>
                    <select id="giftOptionPreset" class="form-control">
                        <option value="30_0">Option 30: Không thể giao dịch</option>
                        <option value="50_50">Option 50: Sức đánh +50%</option>
                        <option value="77_50">Option 77: HP +50%</option>
                        <option value="103_7">Option 103: Ki +7 vạn</option>
                        <option value="none">Không thêm option</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('giftModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Tặng Ngay Vào SQL</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CHI TIẾT Ô ĐỒ -->
<div class="modal-backdrop" id="itemDetailModal">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700;" id="detailItemTitle">Thông Tin Món Đồ</h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('itemDetailModal')">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;">
            <div class="item-icon-box" style="width:56px; height:56px; margin:0 auto 12px;">
                <img id="detailItemIcon" src="" style="max-width:48px; max-height:48px;" alt="">
            </div>
            <h4 id="detailItemName" style="color:#fff; font-size:16px; margin-bottom:4px;"></h4>
            <div style="font-size:12px; color:var(--text-muted); margin-bottom:12px;" id="detailItemMeta"></div>
            
            <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px; padding:10px; text-align:left; font-size:12px; margin-bottom:16px;" id="detailItemOptions">
            </div>

            <div style="display:flex; gap:10px; align-items:center; justify-content:center; margin-bottom:12px;">
                <label style="font-size:13px;">Số lượng:</label>
                <input type="number" id="detailItemQtyInput" class="form-control" style="width:100px;" min="1">
                <button type="button" class="btn btn-primary btn-sm" onclick="saveItemQty()">Lưu SL</button>
            </div>
        </div>
        <div class="modal-footer" style="justify-content:space-between;">
            <button type="button" class="btn btn-danger btn-sm" onclick="deleteCurrentDetailItem()">🗑️ Xóa Khỏi Túi</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('itemDetailModal')">Đóng</button>
        </div>
    </div>
</div>

<!-- MODAL NẠP TIỀN & TÀI NGUYÊN TRỰC TIẾP (TOPUP LIVE) -->
<div class="modal-backdrop" id="topupModal">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700; color:#10b981;">💳 Nạp Tài Nguyên Trực Tiếp Vào Nhân Vật (Live Sync)</h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('topupModal')">✕</button>
        </div>
        <form onsubmit="submitTopup(event)">
            <div class="modal-body">
                <p style="font-size:13px; color:var(--text-secondary); margin-bottom:16px;">
                    Cộng thẳng Vàng, Ngọc, Hồng Ngọc, Thỏi Vàng vào nhân vật. Nếu người chơi đang online trong game, hệ thống sẽ phát tín hiệu nhận ngay và hiển thị thông báo chuông reo trên màn hình game.
                </p>

                <!-- VÀNG -->
                <div class="form-group" style="margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <label class="form-label" style="margin:0;">🟡 Cộng Thêm Vàng (Gold):</label>
                        <span style="font-size:12px; color:#fbbf24;">Hiện có: <?= number_format($di[0] ?? 0) ?></span>
                    </div>
                    <input type="number" id="topupGold" class="form-control" value="0" min="0" step="1000000">
                    <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupGold', 50000000)">+50 Triệu</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupGold', 200000000)">+200 Triệu</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupGold', 500000000)">+500 Triệu</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupGold', 1000000000)">+1 Tỷ</button>
                        <button type="button" class="btn btn-xs btn-danger" onclick="document.getElementById('topupGold').value=0">Xóa</button>
                    </div>
                </div>

                <!-- NGỌC XANH -->
                <div class="form-group" style="margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <label class="form-label" style="margin:0;">💎 Cộng Thêm Ngọc Xanh (Gem):</label>
                        <span style="font-size:12px; color:#06b6d4;">Hiện có: <?= number_format($di[1] ?? 0) ?></span>
                    </div>
                    <input type="number" id="topupGem" class="form-control" value="0" min="0" step="1000">
                    <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupGem', 5000)">+5,000</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupGem', 20000)">+20,000</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupGem', 50000)">+50,000</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupGem', 100000)">+100,000</button>
                        <button type="button" class="btn btn-xs btn-danger" onclick="document.getElementById('topupGem').value=0">Xóa</button>
                    </div>
                </div>

                <!-- HỒNG NGỌC -->
                <div class="form-group" style="margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <label class="form-label" style="margin:0;">🔴 Cộng Thêm Hồng Ngọc (Ruby):</label>
                        <span style="font-size:12px; color:#ec4899;">Hiện có: <?= number_format($di[2] ?? 0) ?></span>
                    </div>
                    <input type="number" id="topupRuby" class="form-control" value="0" min="0" step="1000">
                    <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupRuby', 5000)">+5,000</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupRuby', 20000)">+20,000</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupRuby', 50000)">+50,000</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupRuby', 100000)">+100,000</button>
                        <button type="button" class="btn btn-xs btn-danger" onclick="document.getElementById('topupRuby').value=0">Xóa</button>
                    </div>
                </div>

                <!-- THỎI VÀNG (ITEM 457) -->
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label">🧱 Cộng Thỏi Vàng Vào Hành Trang (Item #457):</label>
                    <input type="number" id="topupThoiVang" class="form-control" value="0" min="0">
                    <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupThoiVang', 10)">+10 Thỏi</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupThoiVang', 50)">+50 Thỏi</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupThoiVang', 100)">+100 Thỏi</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="addTopupValue('topupThoiVang', 500)">+500 Thỏi</button>
                        <button type="button" class="btn btn-xs btn-danger" onclick="document.getElementById('topupThoiVang').value=0">Xóa</button>
                    </div>
                </div>

                <div style="border-top:1px solid var(--border-color); padding-top:12px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" id="topupKickLive" style="width:16px; height:16px;">
                        <span style="font-size:13px; color:#f59e0b;">Đá người chơi ra ngoài (Kick Live) sau khi nạp (không bắt buộc)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('topupModal')">Hủy</button>
                <button type="submit" class="btn btn-success" style="background:#10b981; border:none; padding:10px 24px; font-weight:700;">🚀 Nạp Ngay Vào Game</button>
            </div>
        </form>
    </div>
</div>

<script>
const INVENTORY_ORIGINAL = <?= json_encode(['bag'=>array_map(fn($i)=>$i['expected_item']??null,$items_bag??[]),'box'=>array_map(fn($i)=>$i['expected_item']??null,$items_box??[]),'body'=>array_map(fn($i)=>$i['expected_item']??null,$items_body??[])], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const PLAYER_ID = <?= (int)$player['id'] ?>;
let activeDetailItem = null;
let activeDetailTarget = null;

// Tab switcher
function switchTab(tabId) {
    document.querySelectorAll('.tab-pane').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.classList.remove('btn-primary');
        el.classList.add('btn-outline');
    });
    document.getElementById(tabId).style.display = 'block';
    event.currentTarget.classList.remove('btn-outline');
    event.currentTarget.classList.add('btn-primary');
}

// Quick Emergency Action
function triggerAction(actionName) {
    if (!confirm('Xác nhận thực hiện hành động này vào CSDL?')) return;
    adminCommandFetch('<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/api/player_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: actionName, player_id: PLAYER_ID, kick_player: false })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Lưu Stats
function saveStats(e) {
    e.preventDefault();
    const payload = {
        action: 'update_stats',
        player_id: PLAYER_ID,
        limit_power: document.getElementById('statLimitPower').value,
        power: document.getElementById('statPower').value,
        tiem_nang: document.getElementById('statTiemNang').value,
        hpg: document.getElementById('statHpg').value,
        mpg: document.getElementById('statMpg').value,
        dameg: document.getElementById('statDameg').value,
        defg: document.getElementById('statDefg').value,
        critg: document.getElementById('statCritg').value,
        gold: document.getElementById('statGold').value,
        gem: document.getElementById('statGem').value,
        ruby: document.getElementById('statRuby').value,
        kick_player: document.getElementById('statKickLive').checked
    };
    
    // Gửi POST tới players.php
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/players.php';
    for (const key in payload) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = key;
        inp.value = payload[key];
        form.appendChild(inp);
    }
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = '<?= get_csrf_token() ?>';
    form.appendChild(csrf);

    document.body.appendChild(form);
    const requestField = document.createElement('input'); requestField.type='hidden'; requestField.name='request_id'; requestField.value=adminRequestId(); form.appendChild(requestField);
    form.submit();
}

// Show Item Detail Modal
function showItemDetail(item, target) {
    activeDetailItem = item;
    activeDetailTarget = target;
    document.getElementById('detailItemTitle').innerText = `${item.name} (#${item.id})`;
    document.getElementById('detailItemName').innerText = item.name;
    document.getElementById('detailItemIcon').src = item.icon_url;
    document.getElementById('detailItemMeta').innerText = `ID: #${item.id} | Ô số: ${item.index} (${target === 'box' ? 'Rương' : 'Túi'})`;
    document.getElementById('detailItemQtyInput').value = item.quantity;

    const optContainer = document.getElementById('detailItemOptions');
    if (!item.options || item.options.length === 0) {
        optContainer.innerHTML = '<span style="color:var(--text-muted);">Không có thuộc tính option.</span>';
    } else {
        optContainer.innerHTML = item.options.map(o => `<div style="color:#10b981;">• ${o.text}</div>`).join('');
    }

    openModal('itemDetailModal');
}

function saveItemQty() {
    if (!activeDetailItem) return;
    const qty = parseInt(document.getElementById('detailItemQtyInput').value, 10);
    adminCommandFetch('<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/api/player_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_item_qty',
            player_id: PLAYER_ID,
            target: activeDetailTarget,
            slot_index: activeDetailItem.index,
            expected_item: activeDetailItem.expected_item,
            quantity: qty,
            kick_player: false
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function deleteCurrentDetailItem() {
    if (!activeDetailItem || !confirm(`Xóa món đồ "${activeDetailItem.name}" khỏi CSDL?`)) return;
    deleteItem(activeDetailTarget, activeDetailItem.index);
}

function deleteItem(target, slotIndex) {
    adminCommandFetch('<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/api/player_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete_item',
            player_id: PLAYER_ID,
            target: target,
            slot_index: slotIndex,
            expected_item: INVENTORY_ORIGINAL[target]?.[slotIndex],
            kick_player: false
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// GIFT ITEM MODAL
function openGiftModal() { openModal('giftModal'); }

let giftSearchTimeout = null;
function searchGiftItems(q) {
    clearTimeout(giftSearchTimeout);
    const box = document.getElementById('giftSearchResults');
    if (!q || q.trim().length < 1) { box.style.display = 'none'; return; }

    giftSearchTimeout = setTimeout(() => {
        fetch(`<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/api/search_items.php?action=items&q=${encodeURIComponent(q)}`, {headers: {'X-CSRF-Token': adminCsrfToken()}})
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.data.length > 0) {
                    box.innerHTML = '';
                    data.data.forEach(item => {
                        const div = document.createElement('div');
                        div.style.cssText = 'display:flex; align-items:center; gap:10px; padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border-subtle);';
                        div.innerHTML = `
                            <div class="item-icon-box" style="width:28px; height:28px;"><img src="${item.icon_url}"></div>
                            <div style="flex:1;"><strong style="font-size:13px;">${item.name}</strong> <span style="font-size:11px; color:var(--text-muted);">(ID: ${item.id})</span></div>
                        `;
                        div.onclick = () => {
                            document.getElementById('giftItemId').value = item.id;
                            document.getElementById('giftItemSearch').value = item.name;
                            box.style.display = 'none';
                        };
                        box.appendChild(div);
                    });
                    box.style.display = 'block';
                }
            });
    }, 250);
}

function submitGiftItem(e) {
    e.preventDefault();
    const itemId = parseInt(document.getElementById('giftItemId').value, 10);
    const qty = parseInt(document.getElementById('giftItemQty').value, 10) || 1;
    const target = document.getElementById('giftTarget').value;
    const preset = document.getElementById('giftOptionPreset').value;

    let options = [];
    if (preset === '30_0') options.push([30, 0]);
    if (preset === '50_50') options.push([50, 50], [30, 0]);
    if (preset === '77_50') options.push([77, 50], [30, 0]);
    if (preset === '103_7') options.push([103, 7], [30, 0]);

    adminCommandFetch('<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/api/player_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'gift_item',
            player_id: PLAYER_ID,
            item_id: itemId,
            quantity: qty,
            target: target,
            options: options,
            kick_player: false
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            closeModal('giftModal');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Quest jumper
function jumpToTask() {
    const taskId = document.getElementById('selectTargetTask').value;
    if (!confirm(`Nhảy cóc sang nhiệm vụ #${taskId}?`)) return;
    adminCommandFetch('<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/api/player_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'change_task', player_id: PLAYER_ID, task_id: taskId, kick_player: false })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function completeCurrentTask(nextTaskId) {
    document.getElementById('selectTargetTask').value = nextTaskId;
    jumpToTask();
}

// Pet Studio Save
function updatePetNamePreview() {
    const names = <?= json_encode(array_map('default_pet_name', range(0, 4)), JSON_UNESCAPED_UNICODE) ?>;
    document.getElementById('petName').value = names[Number(document.getElementById('petType').value)] || 'Không xác định';
}

function savePet(e) {
    e.preventDefault();
    adminCommandFetch('<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/api/player_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_pet',
            player_id: PLAYER_ID,
            ...(Number(document.getElementById('petType').value) !== <?= (int)$pet_data['type'] ?>
                ? {pet_type: document.getElementById('petType').value} : {}),
            pet_power: document.getElementById('petPower').value,
            pet_tiem_nang: document.getElementById('petTiemNang').value,
            pet_hpg: document.getElementById('petHpg').value,
            pet_mpg: document.getElementById('petMpg').value,
            pet_dameg: document.getElementById('petDameg').value,
            pet_skill_1: document.getElementById('petSkill1').value,
            pet_skill_2: document.getElementById('petSkill2').value,
            pet_skill_3: document.getElementById('petSkill3').value,
            pet_skill_4: document.getElementById('petSkill4').value,
            kick_player: false
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Change Account Password
function changeAccPassword(e) {
    e.preventDefault();
    const pass = document.getElementById('inputNewPass').value.trim();
    const accId = <?= (int)$player['account_id'] ?>;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/accounts.php';

    const act = document.createElement('input'); act.type='hidden'; act.name='action'; act.value='change_password'; form.appendChild(act);
    const id = document.createElement('input'); id.type='hidden'; id.name='account_id'; id.value=accId; form.appendChild(id);
    const p = document.createElement('input'); p.type='hidden'; p.name='new_password'; p.value=pass; form.appendChild(p);
    const csrf = document.createElement('input'); csrf.type='hidden'; csrf.name='csrf_token'; csrf.value='<?= get_csrf_token() ?>'; form.appendChild(csrf);

    document.body.appendChild(form);
    const requestField = document.createElement('input'); requestField.type='hidden'; requestField.name='request_id'; requestField.value=adminRequestId(); form.appendChild(requestField);
    form.submit();
}

// Topup Modal Functions
function openTopupModal() { openModal('topupModal'); }

function addTopupValue(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = (BigInt(el.value || '0') + BigInt(val)).toString();
}

function submitTopup(e) {
    e.preventDefault();
    const gold = document.getElementById('topupGold').value || '0';
    const gem = document.getElementById('topupGem').value || '0';
    const ruby = document.getElementById('topupRuby').value || '0';
    const thoiVang = document.getElementById('topupThoiVang').value || '0';
    const kickLive = document.getElementById('topupKickLive').checked;

    if (gold <= 0 && gem <= 0 && ruby <= 0 && thoiVang <= 0) {
        showToast('Vui lòng chọn ít nhất 1 loại tài nguyên cần nạp!', 'warning');
        return;
    }

    adminCommandFetch('<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/api/player_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'topup_player',
            player_id: PLAYER_ID,
            gold_add: gold,
            gem_add: gem,
            ruby_add: ruby,
            thoi_vang_add: thoiVang,
            kick_player: kickLive
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            closeModal('topupModal');
            setTimeout(() => location.reload(), 700);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Lỗi gửi yêu cầu nạp tài nguyên', 'error');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
