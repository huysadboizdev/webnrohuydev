<?php
// Every connection here is fake. This script never loads mysqli extension.
$scenario=$argv[1];$endpoint=$argv[2];
class mysqli {
 public $connect_error=null; public $writes=0;public $insert_id=1;
 function __construct(...$a){}function set_charset($c){}
 function prepare($sql){return new APIStatement($this,$sql);}
}
class APIStatement {
 public $insert_id=1;private $db;private $sql;
 function __construct($d,$s){$this->db=$d;$this->sql=$s;}
 function bind_param($t,&...$p){}
 function execute(){if(strpos($this->sql,'INSERT')===0)$this->db->writes++;return true;}
 function close(){}
 function get_result(){global $scenario;return new APIResult(strpos($this->sql,'FROM account')!==false?['id'=>42,'username'=>'actual-admin','is_admin'=>$scenario==='ordinary'?0:1,'admin'=>0,'ban'=>$scenario==='banned'?1:0]:null);}
}
class APIResult {private $row;function __construct($r){$this->row=$r;}function fetch_assoc(){return $this->row;}}
session_start();$_SESSION=['user_id'=>42,'admin_csrf_token'=>'correct'];
if($scenario==='guest')$_SESSION=[];
$_SERVER['REQUEST_METHOD']=$scenario==='method'?'PUT':'POST';
if($scenario==='maintenance_get')$_SERVER['REQUEST_METHOD']='GET';
$_POST=['csrf_token'=>'correct','request_id'=>'550e8400-e29b-41d4-a716-446655440000','action'=>'heal_full','player_id'=>1,'command_type'=>'HEAL_FULL','payload'=>['player_id'=>1]];
if($scenario==='missing')unset($_POST['csrf_token']);
if($scenario==='wrong')$_POST['csrf_token']='wrong';
if($scenario==='unknown'){$_POST['action']='unknown';$_POST['command_type']='UNSUPPORTED';}
if(strpos($scenario,'maintenance_')===0){
 $_POST['command_type']=$scenario==='maintenance_open'?'MAINTENANCE_OPEN':'MAINTENANCE_START';$_POST['payload']=[];
 if($scenario==='maintenance_fields')$_POST['payload']=['shell'=>'shutdown'];
 if($scenario==='maintenance_csrf')$_POST['csrf_token']='wrong';
}
if(strpos($scenario,'event_')===0){
 $_POST['command_type']='EVENT_SET';$_POST['payload']=['event_key'=>'summer','enabled'=>false,'revision'=>4];
 if($scenario==='event_bad_bool')$_POST['payload']['enabled']='false';
 if($scenario==='event_bad_revision')$_POST['payload']['revision']=-1;
 if($scenario==='event_unknown')$_POST['payload']['event_key']='arbitrary';
 if($scenario==='event_csrf')$_POST['csrf_token']='wrong';
}
register_shutdown_function(function(){global $conn;fwrite(STDERR,json_encode(['http'=>http_response_code(),'writes'=>$conn->writes]));});
require __DIR__.'/../admin/api/'.$endpoint.'.php';
