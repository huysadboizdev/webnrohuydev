<?php
// WEB_ROOT is resolved by connect.php for the current Apache document root.
function admin_url($path) {
    return (defined('WEB_ROOT') ? WEB_ROOT : '/webgoc2026') . '/' . ltrim($path, '/');
}
