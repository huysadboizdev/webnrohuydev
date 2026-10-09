<?php
// Run with php -n: fake mysqli prevents any production connection.
class mysqli {
 public $connect_error = null; public $rows=[]; public $insert_id=0; public $fail=false;
 function __construct(...$args) {} function set_charset($c) {}
 function prepare($sql) { return new FakeStatement($this,$sql); }
}
class FakeStatement {
 public $insert_id=0; private $db; private $sql; private $p=[]; private $row;
 function __construct($d,$s){$this->db=$d;$this->sql=$s;}
 function bind_param($t,&...$p){$this->p=&$p;}
 function execute(){
  if($this->db->fail)return false;
  if(strpos($this->sql,'INSERT')===0){
   foreach($this->db->rows as $r)if(isset($this->p[3])&&$r['request_id']===$this->p[3])return false;
   $this->insert_id=++$this->db->insert_id;
   $this->db->rows[]=['id'=>$this->insert_id,'command_type'=>$this->p[0],'payload'=>$this->p[1],'created_by'=>$this->p[2],'request_id'=>$this->p[3]??null,'actor_account_id'=>$this->p[4]??null];
  } else foreach($this->db->rows as $r)if($r['request_id']===$this->p[0])$this->row=$r;
  return true;
 }
 function get_result(){return new FakeResult($this->row??null);} function close(){}
}
class FakeResult {private $row; function __construct($r){$this->row=$r;} function fetch_assoc(){return $this->row;}}
require __DIR__.'/../admin/includes/live_sync_helper.php';
$logged_user_id=42;$logged_username='real-admin';
function check($ok,$msg){if(!$ok)throw new Exception($msg);}
$key='550e8400-e29b-41d4-a716-446655440000';
$first=dispatch_server_command('HEAL_FULL',['player_id'=>1],'spoof',$key);
check($first===1,'first enqueue returns ID');
check($conn->rows[0]['request_id']===$key,'request ID must persist');
check($conn->rows[0]['actor_account_id']===42,'actor ID must persist');
check($conn->rows[0]['created_by']==='real-admin','creator must be authenticated username');
check(dispatch_server_command('HEAL_FULL',['player_id'=>1],'spoof',$key)===$first,'retry deduplicates');
check(dispatch_server_command('HEAL_FULL',['player_id'=>2],'spoof',$key)===false,'different payload cannot reuse key');
$emptyKey='550e8400-e29b-41d4-a716-446655440002';
$emptyId=dispatch_server_command('RELOAD_ALL',[],'admin',$emptyKey);
check($conn->rows[1]['payload']==='{}','empty command payload must be JSON object for server codec');
check(dispatch_server_command('RELOAD_ALL',[],'admin',$emptyKey)===$emptyId,'empty object retry deduplicates');
$conn->fail=true;
check(dispatch_server_command('HEAL_FULL',['player_id'=>1])===false,'execute failure cannot report success');
echo "queue contract OK\n";
