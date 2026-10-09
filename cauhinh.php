<?php
require_once __DIR__ . '/connect.php';
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_domain = $protocol . $host . (WEB_ROOT ? WEB_ROOT . '/' : '/');
$_IP = '127.0.0.1'; // IP hiển thị ở phần cuối trang

// API
$w_cuphap_momo = 'Mr Blue'; // cú pháp
$_qrmomo = 'img/qrmomo.png'; // link ảnh qr code
$_phonemomo = '0392920228'; // số điện thoại momo
$_momo = 'Momo'; // ngân hàng momo
$_nganhang = 'Mbbank - Ngân Hàng Quân Đội'; // ngân hàng quân đội mbbank
$_taikhoanmm = 'Mr Blue'; // tên tài khoản

?>
