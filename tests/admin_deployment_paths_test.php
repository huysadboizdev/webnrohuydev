<?php
define('WEB_ROOT', $argv[1] ?? '');
require __DIR__ . '/../admin/includes/icon_helper.php';
function expect_url($actual, $expected) {
    if ($actual !== $expected) throw new RuntimeException("Expected $expected, got $actual");
}
expect_url(get_item_icon_url(-1), WEB_ROOT . '/favicon-32x32.png');
expect_url(get_boss_avatar_url('missing'), WEB_ROOT . '/favicon-32x32.png');
expect_url(admin_url('/admin/assets/css/admin.css'), WEB_ROOT . '/admin/assets/css/admin.css');
echo "Admin deployment paths OK: " . (WEB_ROOT ?: '/') . "\n";
