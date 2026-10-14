<?php
require_once __DIR__ . '/url_helper.php';
// admin/includes/icon_helper.php
// Quản lý và cung cấp URL icon vật phẩm cho Web Admin Control Panel

if (!defined('ADMIN_ROOT')) {
    define('ADMIN_ROOT', dirname(__DIR__));
}

function get_item_icon_url($icon_id) {
    $icon_id = (int)$icon_id;
    if ($icon_id < 0) {
        return '' . rtrim(admin_url(''), '/') . '/favicon-32x32.png';
    }

    $web_dest_dir = ADMIN_ROOT . '/assets/icons';
    if (!is_dir($web_dest_dir)) {
        @mkdir($web_dest_dir, 0777, true);
    }

    $web_icon_file = $web_dest_dir . '/' . $icon_id . '.png';
    $web_relative_url = '' . rtrim(admin_url(''), '/') . '/admin/assets/icons/' . $icon_id . '.png';

    if (is_valid_admin_icon($web_icon_file)) {
        return $web_relative_url;
    }

    // 1. Kiểm tra nguồn SRC/data/icon/x1
    $src_x1 = 'C:/Users/Admin/Downloads/nroteam/Teamobi2026/SRC/data/icon/x1/' . $icon_id . '.png';
    if (file_exists($src_x1)) {
        if (@copy($src_x1, $web_icon_file) && is_valid_admin_icon($web_icon_file)) return $web_relative_url;
    }

    // 2. Kiểm tra nguồn SRC/data/icon/x2
    $src_x2 = 'C:/Users/Admin/Downloads/nroteam/Teamobi2026/SRC/data/icon/x2/' . $icon_id . '.png';
    if (file_exists($src_x2)) {
        if (@copy($src_x2, $web_icon_file) && is_valid_admin_icon($web_icon_file)) return $web_relative_url;
    }

    // 3. Kiểm tra nguồn web images/avatar
    $avatar_file = dirname(ADMIN_ROOT) . '/images/avatar/' . $icon_id . '.png';
    if (file_exists($avatar_file)) {
        if (@copy($avatar_file, $web_icon_file) && is_valid_admin_icon($web_icon_file)) return $web_relative_url;
    }

    $avatar_small = dirname(ADMIN_ROOT) . '/images/avatar/small' . $icon_id . '.png';
    if (file_exists($avatar_small)) {
        if (@copy($avatar_small, $web_icon_file) && is_valid_admin_icon($web_icon_file)) return $web_relative_url;
    }

    return '' . rtrim(admin_url(''), '/') . '/favicon-32x32.png';
}

function is_valid_admin_icon($path) {
    if (!is_file($path) || !is_readable($path) || filesize($path) === 0) return false;
    $info=@getimagesize($path);
    return is_array($info) && $info[0] > 0 && $info[1] > 0 && ($info[2] ?? 0) === IMAGETYPE_PNG;
}

function get_boss_avatar_url($bossKey, $headId = null) {
    static $map = null;
    if ($map === null) $map = json_decode(file_get_contents(__DIR__.'/boss_avatar_map.json'), true) ?: [];
    $row = $map[$bossKey] ?? null;
    // A head part ID is not an icon ID. Use the resource resolved from SQL.
    $icon = $row['icon_id'] ?? null;
    if (is_int($icon) && $icon >= 0 && ($headId === null || (int)$headId === (int)$row['head_id'])) {
        $path = ADMIN_ROOT.'/assets/boss-icons/'.$icon.'.png';
        if (is_valid_admin_icon($path)) return '' . rtrim(admin_url(''), '/') . '/admin/assets/boss-icons/'.$icon.'.png';
    }
    return '' . rtrim(admin_url(''), '/') . '/favicon-32x32.png';
}
