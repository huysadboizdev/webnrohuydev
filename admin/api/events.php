<?php
define('ADMIN_JSON_API',true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/../auth_guard.php';
require_once __DIR__.'/../includes/event_helper.php';
if ($_SERVER['REQUEST_METHOD']!=='GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['status'=>'error','message'=>'GET required']);
    exit;
}
try {
    echo json_encode(['status'=>'success','data'=>admin_read_events($conn),'observed_at'=>gmdate('c')],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['status'=>'error','message'=>'Chưa thể đọc trạng thái sự kiện. Kiểm tra migration và máy chủ.']);
}
