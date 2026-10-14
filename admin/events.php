<?php
$page_title='Điều khiển sự kiện';
$active_tab='events';
require_once __DIR__.'/includes/header.php';
?>
<div class="card">
    <div class="card-header"><div class="card-title">Sự kiện trong game</div><button type="button" class="btn btn-outline" id="eventRefresh">Làm mới</button></div>
    <div class="card-body">
        <p>Tắt sự kiện sẽ thu hồi nguyên liệu, vé và vật phẩm tiêu hao được liệt kê. Giữ toàn bộ quà đã nhận, cải trang, pet và chỉ số. Rương chờ kỳ bảo trì tiếp theo; chưa bật lại khi còn job dọn.</p>
        <p id="eventMessage" role="status" aria-live="polite">Đang đọc trạng thái từ máy chủ…</p>
        <div id="eventList"></div>
    </div>
</div>
<script defer src="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/assets/js/events.js?v=<?= filemtime(__DIR__.'/assets/js/events.js') ?>"></script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
