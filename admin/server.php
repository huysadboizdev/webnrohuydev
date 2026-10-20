<?php
$page_title='Máy chủ / Bảo trì';
$active_tab='server';
require_once __DIR__.'/includes/header.php';
?>
<div class="card" id="serverMaintenance">
    <div class="card-header"><div class="card-title">Máy chủ / Bảo trì</div><button type="button" class="btn btn-outline" id="maintenanceRefresh">Làm mới</button></div>
    <div class="card-body">
        <p>Bảo trì sẽ chặn đăng nhập, lưu dữ liệu rồi ngắt toàn bộ người chơi. JVM tiếp tục chạy để nhận lệnh mở lại. Không tự mở lại sau 5 phút.</p>
        <p id="maintenanceState" role="status" aria-live="polite">Đang đọc trạng thái…</p>
        <p id="maintenanceHeartbeat"></p>
        <p id="maintenanceDetail"></p>
        <p id="maintenanceMessage" role="status" aria-live="polite"></p>
        <div class="btn-group">
            <button type="button" class="btn btn-danger" id="maintenanceStart" disabled>Bảo trì ngay</button>
            <button type="button" class="btn btn-primary" id="maintenanceOpen" disabled>Mở lại server</button>
            <button type="button" class="btn btn-outline" id="maintenanceRetry" hidden>Kiểm tra / gửi lại cùng yêu cầu</button>
        </div>
        <p>Heartbeat cũ có thể do máy chủ dừng, treo hoặc mất SQL. Khi trạng thái gần nhất là OPEN/FAILED, bạn vẫn có thể xếp hàng yêu cầu bảo trì; nếu JVM đã treo hoặc SQL mất kết nối thì yêu cầu chưa thể đá người chơi cho tới khi server xử lý lại. Lệnh đang chờ không có nghĩa là đã lưu và ngắt xong.</p>
    </div>
</div>
<div class="card"><div class="card-header"><div class="card-title">Lịch sử lệnh bảo trì</div></div><div class="card-body" id="maintenanceHistory"></div></div>
<script defer src="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/assets/js/server-maintenance.js?v=<?= filemtime(__DIR__.'/assets/js/server-maintenance.js') ?>"></script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
