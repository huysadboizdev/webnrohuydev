<?php
// admin/includes/header.php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/icon_helper.php';
require_once __DIR__ . '/live_sync_helper.php';

$page_title = $page_title ?? 'Admin Control Panel';
$active_tab = $active_tab ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="admin-csrf-token" content="<?= htmlspecialchars(get_csrf_token(), ENT_QUOTES) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?> - Ngoc Rong Control Panel</title>
    <link rel="icon" href="/webgoc2026/favicon-32x32.png" type="image/png">
    <link rel="stylesheet" href="/webgoc2026/admin/assets/css/admin.css?v=<?= time() ?>">
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar Include -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button type="button" class="mobile-toggle" id="mobileToggle" aria-label="Toggle Menu">
                    ☰
                </button>
                <h1 class="header-title"><?= htmlspecialchars($page_title) ?></h1>
            </div>
            <div class="header-right">
                <button type="button" class="btn btn-sm btn-outline" onclick="triggerLiveSync('RELOAD_ALL', {})" title="Đồng bộ tất cả dữ liệu vào game">
                    ⚡ Live Reload All
                </button>
                <div class="user-badge">
                    <div class="avatar-badge">A</div>
                    <span style="font-weight:600; font-size:13px;"><?= htmlspecialchars($logged_username) ?></span>
                </div>
            </div>
        </header>

        <main class="admin-content">
