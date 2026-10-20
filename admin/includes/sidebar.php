<?php
// admin/includes/sidebar.php
$active_tab = $active_tab ?? 'dashboard';
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/" class="sidebar-logo">
            <span style="font-size:24px;">🐉</span>
            <span>NRO Control Panel</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Tổng quan</div>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/index.php" class="nav-link <?= $active_tab === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span>Dashboard & Live Sync</span>
        </a>

        <div class="nav-section-title">Người chơi & Tài khoản</div>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/accounts.php" class="nav-link <?= $active_tab === 'accounts' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            <span>Tài khoản (Account)</span>
        </a>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/players.php" class="nav-link <?= $active_tab === 'players' ? 'active' : '' ?>">
            <span class="nav-icon">⚡</span>
            <span>Nhân vật & Chỉ số</span>
        </a>

        <div class="nav-section-title">Vật phẩm & Quà tặng</div>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/giftcodes.php" class="nav-link <?= $active_tab === 'giftcodes' ? 'active' : '' ?>">
            <span class="nav-icon">🎁</span>
            <span>Giftcode Studio</span>
        </a>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/shops.php" class="nav-link <?= $active_tab === 'shops' ? 'active' : '' ?>">
            <span class="nav-icon">🛒</span>
            <span>Shop NPC & Ký Gửi</span>
        </a>

        <div class="nav-section-title">Máy chủ & Gameplay</div>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/server.php" class="nav-link <?= $active_tab === 'server' ? 'active' : '' ?>">
            <span class="nav-icon">🛠️</span><span>Máy chủ / Bảo trì</span>
        </a>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/events.php" class="nav-link <?= $active_tab === 'events' ? 'active' : '' ?>">
            <span class="nav-icon">🎉</span><span>Sự kiện</span>
        </a>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/bosses.php" class="nav-link <?= $active_tab === 'bosses' ? 'active' : '' ?>">
            <span class="nav-icon">👹</span><span>Boss & Cấu hình Live</span>
        </a>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/mobs.php" class="nav-link <?= $active_tab === 'mobs' ? 'active' : '' ?>">
            <span class="nav-icon">👾</span>
            <span>Quái vật & Rate EXP</span>
        </a>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/gameplay.php" class="nav-link <?= $active_tab === 'gameplay' ? 'active' : '' ?>">
            <span class="nav-icon">📜</span>
            <span>Nhiệm vụ & Skill & Pet</span>
        </a>

        <div class="nav-section-title">Tài chính & Hệ thống</div>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/transactions.php" class="nav-link <?= $active_tab === 'transactions' ? 'active' : '' ?>">
            <span class="nav-icon">💳</span>
            <span>Doanh thu & Thẻ cào</span>
        </a>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/website_settings.php" class="nav-link <?= $active_tab === 'settings' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span>
            <span>Thông báo & Cấu hình</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/" class="btn btn-sm btn-outline" style="flex:1; margin-right:6px;" target="_blank">
            🌐 Xem Web
        </a>
        <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/app/logout.php" class="btn btn-sm btn-danger" style="flex:1;">
            🚪 Thoát
        </a>
    </div>
</aside>
