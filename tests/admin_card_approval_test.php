<?php
require_once __DIR__ . '/../admin/includes/card_approval.php';
function check_card($ok, $message) { if (!$ok) throw new RuntimeException($message); }
class CardResult { function __construct(private $row) {} function fetch_assoc() {return $this->row;} }
class CardStatement {
 public $affected_rows=0; private $params=[];
 function __construct(private $db,private $sql) {}
 function bind_param($types,&...$values){foreach($values as &$v)$this->params[]=&$v;}
 function execute(){
  if(str_starts_with($this->sql,'UPDATE account')){if($this->db->failCredit)throw new RuntimeException('credit failed');$this->db->account['vnd']+=$this->params[0];$this->db->account['tongnap']+=$this->params[1];$this->affected_rows=1;}
  if(str_starts_with($this->sql,'UPDATE napthe')){$this->db->card['status']=1;$this->affected_rows=1;}
  return true;
 }
 function get_result(){return new CardResult(str_contains($this->sql,'FROM napthe')?$this->db->card:$this->db->account);}
 function close(){}
}
class CardDatabase {
 public $card=['id'=>7,'status'=>0,'amount'=>100,'user_nap'=>'alice'];
 public $account=['id'=>42,'username'=>'alice','vnd'=>10,'tongnap'=>20];
 public $failCredit=false,$queued=[],$queries=[],$commitCount=0,$rollbackCount=0;private $backup;
 function begin_transaction(){$this->backup=[$this->card,$this->account,$this->queued];return true;}
 function prepare($sql){$this->queries[]=$sql;return new CardStatement($this,$sql);}
 function commit(){$this->commitCount++;return true;}
 function rollback(){[$this->card,$this->account,$this->queued]=$this->backup;$this->rollbackCount++;return true;}
}
function enqueue_card($db,$type,$payload,$actor,$request){$db->queued[]=[$type,$payload,$actor,$request];return 99;}
$db=new CardDatabase();$result=approve_admin_card($db,7,'admin:1','enqueue_card');
check_card($db->account['vnd']===110&&$db->account['tongnap']===120&&$db->card['status']===1,'card and money committed together using user_nap');
check_card($result['command_id']===99&&$db->queued[0][1]['refresh_only']===true,'refresh enqueued within approval');
check_card(preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$db->queued[0][3])===1,'deterministic card request ID satisfies queue validator for single digit card IDs');
check_card(count(array_filter($db->queries,fn($query)=>str_contains($query,'FOR UPDATE')))===2,'card and account each locked before credit');
try{approve_admin_card($db,7,'admin:1','enqueue_card');throw new RuntimeException('duplicate approved');}catch(DomainException $expected){}
check_card($db->account['vnd']===110&&count($db->queued)===1,'duplicate never credits');
$db=new CardDatabase();$db->failCredit=true;try{approve_admin_card($db,7,'admin:1','enqueue_card');throw new RuntimeException('credit failure accepted');}catch(RuntimeException $expected){}
check_card($db->card['status']===0&&$db->account['vnd']===10&&$db->rollbackCount===1,'credit failure rolls status back');
$db=new CardDatabase();$db->account=null;try{approve_admin_card($db,7,'admin:1','enqueue_card');throw new RuntimeException('missing account accepted');}catch(DomainException $expected){}
check_card($db->card['status']===0,'missing account leaves card pending');
$db=new CardDatabase();try{approve_admin_card($db,7,'admin:1',function(){throw new RuntimeException('queue failed');});throw new RuntimeException('queue failure accepted');}catch(RuntimeException $expected){}
check_card($db->account['vnd']===10&&$db->card['status']===0,'enqueue failure rolls back credit');
$db=new CardDatabase();$db->card['status']=2;try{approve_admin_card($db,7,'admin:1','enqueue_card');throw new RuntimeException('failed card accepted');}catch(DomainException $expected){}
check_card($db->card['status']===2&&$db->account['vnd']===10,'failed card is not pending');
$db=new CardDatabase();$db->account['vnd']=2147483640;try{approve_admin_card($db,7,'admin:1','enqueue_card');throw new RuntimeException('balance overflow accepted');}catch(DomainException $expected){}
check_card($db->card['status']===0&&$db->account['vnd']===2147483640,'overflow rejected before status transition');
echo "PASS card owner, duplicate approval, row locks, missing account, credit/queue rollback, refresh command\n";
