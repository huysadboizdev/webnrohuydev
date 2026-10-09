<?php
define('ADMIN_JSON_API',true);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../auth_guard.php';
require_once __DIR__.'/../includes/live_sync_helper.php';
require_once __DIR__.'/../includes/admin_command_validator.php';
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['check_id'])){
 $cmd=get_server_command_by_id((int)$_GET['check_id']);
 if(!$cmd){http_response_code(404);echo json_encode(['status'=>'error','message'=>'Command not found']);}
 else echo json_encode(['status'=>'success','data'=>array_intersect_key($cmd,array_flip(['id','command_type','status','result_message','created_at','executed_at']))]);
 exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['status'=>'error','message'=>'POST required']);exit;}
$data=json_decode(file_get_contents('php://input'),true);if(!is_array($data))$data=$_POST;
if(!verify_csrf_token($data['csrf_token']??'')){http_response_code(403);echo json_encode(['status'=>'error','message'=>'Invalid CSRF token']);exit;}
try{
 $type=$data['command_type']??'';$payload=validate_admin_command($type,$data['payload']??[]);
 $data['request_id']=$data['request_id']??admin_new_request_id();
 $id=dispatch_server_command($type,$payload,$logged_username,$data['request_id']);
 if(!$id)throw new RuntimeException('Enqueue failed');
 http_response_code(202);echo json_encode(['status'=>'queued','command_id'=>$id,'request_id'=>$data['request_id']??null,'message'=>'Đang chờ máy chủ xử lý']);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['status'=>'error','message'=>'Cannot enqueue command or request ID conflicts']);}
