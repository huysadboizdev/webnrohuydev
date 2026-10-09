<?php
define('ADMIN_JSON_API',true);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../auth_guard.php';
require_once __DIR__.'/../includes/live_sync_helper.php';
require_once __DIR__.'/../includes/admin_command_validator.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['status'=>'error','message'=>'POST required']);exit;}
$data=json_decode(file_get_contents('php://input'),true);if(!is_array($data))$data=$_POST;
if(!verify_csrf_token($data['csrf_token']??'')){http_response_code(403);echo json_encode(['status'=>'error','message'=>'Invalid CSRF token']);exit;}
try{
$map=['gift_item'=>'GIFT_ITEM','topup_player'=>'TOPUP_PLAYER','delete_item'=>'DELETE_ITEM','update_item_qty'=>'UPDATE_ITEM_QTY','change_task'=>'CHANGE_TASK','warp_home'=>'WARP_HOME','upgrade_magic_tree'=>'UPGRADE_MAGIC_TREE','heal_full'=>'HEAL_FULL','update_pet'=>'UPDATE_PET','kick_player'=>'KICK_PLAYER'];
 $type=$map[$data['action']??'']??'';
 if($type==='TOPUP_PLAYER')foreach(['gold','gem','ruby','thoi_vang'] as $f)if(isset($data[$f.'_add']))$data['add_'.$f]=$data[$f.'_add'];
 $payload=validate_admin_command($type,$data);
 $data['request_id']=$data['request_id']??admin_new_request_id();
 $id=dispatch_server_command($type,$payload,$logged_username,$data['request_id']);
 if(!$id)throw new RuntimeException('Enqueue failed');
 http_response_code(202);echo json_encode(['status'=>'queued','command_id'=>$id,'request_id'=>$data['request_id']??null,'message'=>'Đang chờ máy chủ xử lý']);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['status'=>'error','message'=>'Cannot enqueue command or request ID conflicts']);}
