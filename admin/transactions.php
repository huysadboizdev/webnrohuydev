<?php
// admin/transactions.php
$page_title = 'Doanh Thu & Lịch Sử Nạp Tiền';
$active_tab = 'transactions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/live_sync_helper.php';
require_once __DIR__ . '/includes/card_approval.php';

$message = '';
$message_type = 'success';

// Xử lý Duyệt thẻ thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $message = 'Mã bảo mật CSRF không hợp lệ.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'approve_card') {
            $card_id = (int)($_POST['card_id'] ?? 0);
            try {
                $approved = approve_admin_card($conn, $card_id, 'admin:' . (int)$logged_user_id,
                    function ($db, $type, $payload, $actor, $requestId) {
                        return dispatch_server_command($type, $payload, $actor, $requestId);
                    });
                $message = "Đã duyệt thẻ #$card_id và cộng {$approved['amount']} VND cho '{$approved['username']}'. Đồng bộ phiên đang chờ, lệnh #{$approved['command_id']}.";
            } catch (DomainException $error) {
                $message = $error->getMessage(); $message_type = 'danger';
            } catch (Throwable $error) {
                error_log('ADMIN_CARD_APPROVAL_FAILED card=' . $card_id . ' actor=' . (int)$logged_user_id);
                $message = 'Chưa xác nhận được giao dịch. Hãy kiểm tra trạng thái thẻ trước khi thử lại.';
                $message_type = 'danger';
            }
        }
    }
}

// Thống kê Doanh thu
$total_card = 0;
$total_bank = 0;

$res = $conn->query("SELECT SUM(amount) AS total FROM napthe WHERE status = 1");
if ($res) $total_card = (float)($res->fetch_assoc()['total'] ?? 0);

$res = $conn->query("SELECT SUM(amount) AS total FROM bank_transfers WHERE LOWER(CAST(status AS CHAR)) IN ('1', 'success') AND is_credited = 1");
if ($res) $total_bank = (float)($res->fetch_assoc()['total'] ?? 0);

$subtab = $_GET['subtab'] ?? 'cards';

// Danh sách thẻ cào
$cards = [];
if ($subtab === 'cards') {
    $res = $conn->query("SELECT * FROM napthe ORDER BY id DESC LIMIT 40");
    if ($res) $cards = $res->fetch_all(MYSQLI_ASSOC);
}

// Danh sách bank transfers
$transfers = [];
if ($subtab === 'bank') {
    $res = $conn->query("SELECT * FROM bank_transfers ORDER BY id DESC LIMIT 40");
    if ($res) $transfers = $res->fetch_all(MYSQLI_ASSOC);
}
?>

<?php if ($message): ?>
    <div style="background:var(--<?= $message_type ?>-light); border:1px solid var(--<?= $message_type ?>); color:var(--text-primary); padding:12px 18px; border-radius:var(--radius-md); margin-bottom:20px; font-weight:500;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Metrics Grid -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">💳</div>
        <div class="metric-info">
            <h3>Tổng Nạp Thẻ Cào</h3>
            <div class="value"><?= number_format($total_card) ?>đ</div>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">🏦</div>
        <div class="metric-info">
            <h3>Tổng Nạp Ngân Hàng</h3>
            <div class="value"><?= number_format($total_bank) ?>đ</div>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">💰</div>
        <div class="metric-info">
            <h3>Tổng Doanh Thu Hợp Nhất</h3>
            <div class="value"><?= number_format($total_card + $total_bank) ?>đ</div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex; gap:10px; margin-bottom:20px;">
    <a href="?subtab=cards" class="btn <?= $subtab === 'cards' ? 'btn-primary' : 'btn-outline' ?>">
        💳 Thẻ Cào Điện Thoại (`napthe`)
    </a>
    <a href="?subtab=bank" class="btn <?= $subtab === 'bank' ? 'btn-primary' : 'btn-outline' ?>">
        🏦 Chuyển Khoản Ngân Hàng (`bank_transfers`)
    </a>
</div>

<?php if ($subtab === 'cards'): ?>
<!-- Cards Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>💳</span>
            <span>Lịch Sử Gạch Thẻ Cào</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tài Khoản</th>
                        <th>Nhà Mạng</th>
                        <th>Mệnh Giá</th>
                        <th>Mã Thẻ / Seri</th>
                        <th>Trạng Thái</th>
                        <th>Thời Gian</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($cards)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted);">Chưa có giao dịch thẻ cào nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($cards as $c): ?>
                        <tr>
                            <td>#<?= (int)$c['id'] ?></td>
                            <td><strong style="color:var(--text-primary);"><?= htmlspecialchars($c['user_nap'] ?? '') ?></strong></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($c['telco'] ?? 'VIETTEL') ?></span></td>
                            <td style="color:#10b981; font-weight:700;"><?= number_format((int)($c['amount'] ?? 0)) ?>đ</td>
                            <td style="font-family:monospace; font-size:12px;">
                                Pin: <?= htmlspecialchars($c['code'] ?? '') ?><br>
                                Seri: <?= htmlspecialchars($c['serial'] ?? '') ?>
                            </td>
                            <td>
                                <?php if (($c['status'] ?? 0) == 1): ?>
                                    <span class="badge badge-success">Thành công</span>
                                <?php elseif (($c['status'] ?? 0) == 2): ?>
                                    <span class="badge badge-danger">Sai thẻ / Thất bại</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Đang chờ xử lý</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($c['created_at'] ?? '') ?></td>
                            <td>
                                <?php if ((string)($c['status'] ?? '') === '0'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Xác nhận duyệt thẻ thủ công và cộng tiền cho người chơi?');">
                                        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                        <input type="hidden" name="action" value="approve_card">
                                        <input type="hidden" name="card_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success">✅ Duyệt</button>
                                    </form>
                                <?php elseif ((string)($c['status'] ?? '') === '1'): ?>
                                    <span style="color:var(--text-muted); font-size:12px;">Đã duyệt</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:12px;">Không thể duyệt</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Bank Transfers Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>🏦</span>
            <span>Lịch Sử Chuyển Khoản Ngân Hàng</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tài Khoản / ID</th>
                        <th>Số Tiền</th>
                        <th>Nội Dung</th>
                        <th>Mã GD</th>
                        <th>Thời Gian</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($transfers)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">Chưa có giao dịch chuyển khoản nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($transfers as $bt): ?>
                        <tr>
                            <td>#<?= (int)($bt['id'] ?? 0) ?></td>
                            <td><strong><?= htmlspecialchars($bt['username'] ?? $bt['user_id'] ?? 'N/A') ?></strong></td>
                            <td style="color:#10b981; font-weight:700;"><?= number_format((int)($bt['amount'] ?? 0)) ?>đ</td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($bt['content'] ?? $bt['description'] ?? '') ?></td>
                            <td style="color:var(--text-muted); font-size:12px;"><?= htmlspecialchars($bt['tran_id'] ?? $bt['transaction_id'] ?? '') ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($bt['created_at'] ?? $bt['time'] ?? '') ?></td>
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
