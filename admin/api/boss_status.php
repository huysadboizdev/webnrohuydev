<?php
define('ADMIN_JSON_API',true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/../auth_guard.php';
require_once __DIR__.'/../includes/boss_admin_helper.php';
// Release the session lock so five-second status reads cannot block saves.
session_write_close();
if($_SERVER['REQUEST_METHOD']!=='GET'){
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['status'=>'error','message'=>'GET required']);
    exit;
}
try{
    echo json_encode(['status'=>'success','data'=>boss_admin_read_status()],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    http_response_code(503);
    echo json_encode(['status'=>'error','message'=>'Không thể đọc trạng thái boss. Cấu hình đã lưu vẫn được giữ nguyên.']);
}
