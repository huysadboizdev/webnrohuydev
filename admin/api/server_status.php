<?php
define('ADMIN_JSON_API',true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/../auth_guard.php';
require_once __DIR__.'/../includes/server_maintenance_helper.php';
if ($_SERVER['REQUEST_METHOD']!=='GET') {
    http_response_code(405);header('Allow: GET');
    echo json_encode(['status'=>'error','message'=>'GET required']);exit;
}
$data=admin_read_server_maintenance($conn);
http_response_code($data['available']?200:503);
echo json_encode(['status'=>$data['available']?'success':'unavailable','data'=>$data,'observed_at'=>gmdate('c')],JSON_UNESCAPED_UNICODE);
