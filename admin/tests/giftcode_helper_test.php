<?php
$helper=__DIR__.'/../includes/giftcode_helper.php';
if(is_file($helper))require $helper;
if(!function_exists('giftcode_canonical'))throw new RuntimeException('Canonical giftcode identity is missing');
function check($v,$m){if(!$v)throw new RuntimeException($m);}
check(giftcode_canonical(" \tabc\r\n")==='ABC','ASCII trim/uppercase');
foreach(['','a b',"ab\0c","\u{00E9}",str_repeat('A',101)] as $code){try{giftcode_canonical($code);throw new RuntimeException('invalid accepted');}catch(InvalidArgumentException $e){}}
$v=giftcode_validate(' abc ','-1','2037-12-31T17:00','[{"id":-2,"quantity":2147483647,"options":[]}]');
check($v['code']==='ABC'&&$v['count']===-1&&$v['expired']==='2037-12-31 17:00:00','normalized creation');
foreach(['[]','[{"id":1,"quantity":0}]','[{"id":1,"quantity":2147483648}]','[{"id":1,"quantity":1},{"id":1,"quantity":2}]','[{"id":1,"quantity":1,"options":[{"id":30,"param":0},{"id":30,"param":1}]}]'] as $detail){try{giftcode_validate('X',1,'2037-12-31T17:00',$detail);throw new RuntimeException('invalid detail accepted');}catch(InvalidArgumentException $e){}}
foreach(['-2','2147483648','1e2','1.0'] as $count){try{giftcode_validate('X',$count,'2037-12-31T17:00','[{"id":1,"quantity":1}]');throw new RuntimeException('invalid count accepted');}catch(InvalidArgumentException $e){}}
try{giftcode_validate('X',1,'2037-02-30T17:00','[{"id":1,"quantity":1}]');throw new RuntimeException('impossible date accepted');}catch(InvalidArgumentException $e){}
echo "giftcode_helper_test PASS\n";
// Multiple options round-trip exactly, including valid zero identifiers and values.
$detail=json_encode([['id'=>1,'quantity'=>1,'options'=>[['id'=>0,'param'=>0],['id'=>50,'param'=>20],['id'=>77,'param'=>20]]]]);
check(giftcode_validate('X',1,'2037-12-31T17:00',$detail)['detail']===$detail,'multiple option round-trip');
$options=[];for($i=0;$i<101;$i++)$options[]=['id'=>$i,'param'=>0];
try{giftcode_validate('X',1,'2037-12-31T17:00',json_encode([['id'=>1,'quantity'=>1,'options'=>$options]]));throw new RuntimeException('101 options accepted');}catch(InvalidArgumentException $e){}
foreach(['-1','1.5','2147483648'] as $param){try{giftcode_validate('X',1,'2037-12-31T17:00',json_encode([['id'=>1,'quantity'=>1,'options'=>[['id'=>0,'param'=>$param]]]]));throw new RuntimeException('bad option param accepted');}catch(InvalidArgumentException $e){}}
echo "giftcode option bounds and round-trip PASS\n";
