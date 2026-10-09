<?php
// admin/index.php
$page_title = 'Dashboard & Live Sync Center';
$active_tab = 'dashboard';
require_once __DIR__ . '/includes/header.php';

// Thống kê cơ bản từ Database
$total_accounts = 0;
$total_players = 0;
$total_revenue = 0;
$total_giftcodes = 0;
$persisted_exp_rate = null;
if ($conn && $conn instanceof mysqli) {
    try {
        $settings = $conn->query("SELECT setting_value FROM server_runtime_setting WHERE setting_key='exp_rate'");
        $setting = $settings ? $settings->fetch_assoc() : null;
        if ($setting && ctype_digit($setting['setting_value']) && (int)$setting['setting_value'] >= 1 && (int)$setting['setting_value'] <= 100) {
            $persisted_exp_rate = (int)$setting['setting_value'];
        }
    } catch (mysqli_sql_exception $unavailable) {}
}


if ($conn && $conn instanceof mysqli) {
    // 1. Tổng tài khoản
    $res = $conn->query("SELECT COUNT(*) AS total FROM account");
    if ($res) $total_accounts = (int)$res->fetch_assoc()['total'];

    // 2. Tổng nhân vật
    $res = $conn->query("SELECT COUNT(*) AS total FROM player");
    if ($res) $total_players = (int)$res->fetch_assoc()['total'];

    // 3. Tổng nạp VND
    $res = $conn->query("SELECT SUM(tongnap) AS total FROM account");
    if ($res) $total_revenue = (float)($res->fetch_assoc()['total'] ?? 0);

    // 4. Tổng giftcode
    $res = $conn->query("SELECT COUNT(*) AS total FROM giftcode WHERE expired > NOW()");
    if ($res) $total_giftcodes = (int)$res->fetch_assoc()['total'];
}

$recent_commands = get_recent_server_commands(8);
?>

<!-- Metrics Grid -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">👥</div>
        <div class="metric-info">
            <h3>Tổng Tài Khoản</h3>
            <div class="value"><?= number_format($total_accounts) ?></div>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">⚡</div>
        <div class="metric-info">
            <h3>Tổng Nhân Vật</h3>
            <div class="value"><?= number_format($total_players) ?></div>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">💰</div>
        <div class="metric-info">
            <h3>Tổng Doanh Thu</h3>
            <div class="value"><?= number_format($total_revenue) ?>đ</div>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">🎁</div>
        <div class="metric-info">
            <h3>Giftcode Hoạt Động</h3>
            <div class="value"><?= number_format($total_giftcodes) ?></div>
        </div>
    </div>
</div>

<!-- Live Sync Engine Control Panel -->
<div class="card" style="border: 1px solid rgba(16, 185, 129, 0.3);">
    <div class="card-header" style="background: rgba(16, 185, 129, 0.05);">
        <div class="card-title" style="color: #10b981;">
            <span>⚡</span>
            <span>Live Sync Engine (Hot Reload Realtime - Không Cần Tắt Server)</span>
        </div>
        <span class="badge badge-success">Sẵn Sàng Đồng Bộ</span>
    </div>
    <div class="card-body">
        <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 13px;">
            Nhấp nút bên dưới để phát lệnh trực tiếp vào RAM Game Server. Dữ liệu mới trong SQL sẽ được cập nhật tức thì vào game trong vòng 1-3 giây mà <strong>không làm gián đoạn hay khởi động lại máy chủ</strong>.
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            <button type="button" class="btn btn-primary" onclick="triggerLiveSync('RELOAD_GIFTCODE', {})">
                🎁 Nạp Lại Giftcode
            </button>
            <button type="button" class="btn btn-warning" onclick="triggerLiveSync('RELOAD_MOB', {})">
                👾 Nạp Lại Quái & HP
            </button>
            <button type="button" class="btn btn-warning" onclick="triggerLiveSync('RELOAD_BOSS', {})">
                👹 Nạp Lại Cấu Hình Boss
            </button>
            <a class="btn btn-outline" href="/webgoc2026/admin/bosses.php">Chỉnh HP/Sức đánh boss</a>
            <button type="button" class="btn btn-success" onclick="triggerLiveSync('RELOAD_SHOP', {})">
                🛒 Nạp Lại Shop NPC
            </button>
            <button type="button" class="btn btn-outline" onclick="openExpModal()">
                📈 Đổi Rate EXP Server
            </button>
            <button type="button" class="btn btn-danger" onclick="openBroadcastModal()">
                📢 Gửi Thông Báo Kênh Thế Giới
            </button>
        </div>
    </div>
</div>

<!-- Lịch sử Lệnh Live Sync -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>📋</span>
            <span>Hàng Đợi Lệnh Live Sync Gần Đây (`server_command_queue`)</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline" onclick="location.reload()">
            🔄 Làm mới
        </button>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Loại Lệnh</th>
                        <th>Dữ Liệu (Payload)</th>
                        <th>Trạng Thái</th>
                        <th>Người Thực Hiện</th>
                        <th>Thời Gian Tạo</th>
                        <th>Thời Gian Chạy</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recent_commands)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--text-muted); padding:30px;">
                            Chưa có lệnh Live Sync nào trong hàng đợi. Nhấp các nút bên trên để thử nghiệm!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_commands as $cmd): ?>
                        <tr>
                            <td>#<?= (int)$cmd['id'] ?></td>
                            <td><strong style="color:var(--primary);"><?= htmlspecialchars($cmd['command_type']) ?></strong></td>
                            <td style="font-family:monospace; font-size:12px; color:var(--text-secondary); max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= htmlspecialchars($cmd['payload'] ?: '{}') ?>
                            </td>
                            <td>
                                <?php if ($cmd['status'] === 'completed'): ?>
                                    <span class="badge badge-success">Hoàn tất</span>
                                <?php elseif ($cmd['status'] === 'processing'): ?>
                                    <span class="badge badge-warning">Đang xử lý</span>
                                <?php elseif ($cmd['status'] === 'failed'): ?>
                                    <span class="badge badge-danger">Thất bại</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">Chờ xử lý (Pending)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($cmd['created_by']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($cmd['created_at']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($cmd['executed_at'] ?: '---') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Đổi Rate EXP -->
<div class="modal-backdrop" id="expModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700;">📈 Cấu Hình Hệ Số EXP Server (Live)</h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('expModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Hệ số EXP Server (VD: 1, 2, 3, 5, 10):</label>
                <input type="number" id="inputExpRate" class="form-control" value="<?= $persisted_exp_rate !== null ? $persisted_exp_rate : '' ?>" min="1" max="100" placeholder="1–100">
                <small style="color:var(--text-muted); display:block; margin-top:6px;">
                    <?= $persisted_exp_rate !== null ? 'EXP đã lưu SQL: ×' . $persisted_exp_rate : 'Chưa có EXP lưu SQL; server dùng Config.properties.' ?> Lệnh hoàn tất trong lịch sử xác nhận đã áp dụng vào game; giá trị được giữ sau khởi động lại.
                </small>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('expModal')">Hủy</button>
            <button type="button" class="btn btn-primary" onclick="submitExpRate()">Lưu & Bắn Lệnh</button>
        </div>
    </div>
</div>

<!-- Modal Bắn Thông Báo -->
<div class="modal-backdrop" id="broadcastModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700;">📢 Thông Báo Kênh Thế Giới</h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('broadcastModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Nội dung thông báo (tối đa 100 ký tự):</label>
                <textarea id="inputNoticeText" class="form-control" rows="3" maxlength="100" placeholder="Nhập nội dung thông báo gửi đến toàn bộ người chơi..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('broadcastModal')">Hủy</button>
            <button type="button" class="btn btn-danger" onclick="submitBroadcastNotice()">Bắn Thông Báo Ngay</button>
        </div>
    </div>
</div>

<script>
function openExpModal() { openModal('expModal'); }
function openBroadcastModal() { openModal('broadcastModal'); }

function submitExpRate() {
    const rate = parseInt(document.getElementById('inputExpRate').value, 10);
    if (!Number.isInteger(Number(document.getElementById('inputExpRate').value)) || !rate || rate < 1 || rate > 100) {
        showToast('Vui lòng nhập hệ số EXP hợp lệ', 'error');
        return;
    }
    triggerLiveSync('SET_EXP_RATE', { rate: rate }, function() {
        closeModal('expModal');
        setTimeout(() => location.reload(), 1000);
    });
}

function submitBroadcastNotice() {
    const text = document.getElementById('inputNoticeText').value.trim();
    if (!text || text.length > 100) {
        showToast('Vui lòng nhập nội dung thông báo', 'error');
        return;
    }
    triggerLiveSync('SEND_NOTICE', { message: text }, function() {
        closeModal('broadcastModal');
        setTimeout(() => location.reload(), 1000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
