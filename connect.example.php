<?php
// connect.php
$ip_sv = 'localhost';
$dbname_sv = 'team2026';
$user_sv = 'root';
$pass_sv = '';

// Legacy aliases: keep every old file using the same DB config from this file.
$db_host = $ip_sv;
$db_name = $dbname_sv;
$db_user = $user_sv;
$db_pass = $pass_sv;

$testflight_url = 'https://testflight.apple.com/join/FWEJoZEB';
$box_zalo_url = 'https://zalo.me/g/njvxgh490';

$thesieure_url = 'https://thesieure.com/chargingws/v2';
$thesieure_partner_id = '';
$thesieure_partner_key = '';


$conn = new mysqli($ip_sv, $user_sv, $pass_sv, $dbname_sv);

if ($conn->connect_error) {
    die("Lỗi kết nối database: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!defined('WEB_ROOT')) {
    $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $dir_root = str_replace('\\', '/', realpath(__DIR__));
    if ($doc_root && strpos($dir_root, $doc_root) === 0) {
        $sub = trim(substr($dir_root, strlen($doc_root)), '/');
        define('WEB_ROOT', $sub !== '' ? '/' . $sub : '');
    } else {
        define('WEB_ROOT', '/webgoc2026');
    }
}
$base_url = WEB_ROOT;
?>
