<?php
require __DIR__.'/admin_queue_contract_test.php';
class ShopPriceDB extends mysqli {
 public $shop=[];public $updates=0;
 function prepare($sql){return new ShopPriceStatement($this,$sql);}
}
class ShopPriceStatement {
 private $db;private $sql;
 function __construct($d,$s){$this->db=$d;$this->sql=$s;}
 function bind_param($t,&...$p){}function close(){}
 function execute(){if(strpos($this->sql,'UPDATE')===0)$this->db->updates++;return true;}
 function get_result(){return new FakeResult($this->db->shop?:null);}
}
$conn=new ShopPriceDB();
function forbiddenPrice($cost,$type){global $conn;try{admin_validate_shop_price(1,$cost,$type);throw new Exception('Forbidden shop edit accepted');}catch(InvalidArgumentException $e){}check($conn->updates===0,'Guard cannot mutate SQL');}
$conn->shop=['tag_name'=>'THIEN_SU','npc_id'=>56,'type_shop'=>3,'temp_id'=>650,'icon_spec'=>7743];
admin_validate_shop_price(1,10,3);forbiddenPrice(11,3);forbiddenPrice(10,2);
$conn->shop=['tag_name'=>'BILL','npc_id'=>55,'type_shop'=>3,'temp_id'=>650,'icon_spec'=>7743];
admin_validate_shop_price(1,100,3);forbiddenPrice(10,3);
$conn->shop['temp_id']=649;forbiddenPrice(100,3);
$conn->shop=['tag_name'=>'SANTA_HONG_NGOC','npc_id'=>39,'type_shop'=>3,'temp_id'=>933,'icon_spec'=>7743];
foreach([933=>100,934=>100,1820=>100,935=>10,1775=>50000] as $template=>$price){$conn->shop['temp_id']=$template;admin_validate_shop_price(1,$price,3);forbiddenPrice($price+1,3);}
$conn->shop['temp_id']=100;admin_validate_shop_price(1,123,3);forbiddenPrice(123,0);
$conn->shop['tag_name']='NORMAL';admin_validate_shop_price(1,123,1);
$conn->shop=[];forbiddenPrice(123,1);
echo "fixed shop pricing guard OK\n";
