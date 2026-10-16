<?php
require __DIR__.'/../includes/giftcode_helper.php';
function check_update($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
check_update(function_exists('giftcode_assert_update_options'),'update option guard missing');
$old=[['id'=>1,'quantity'=>10,'options'=>[['id'=>0,'param'=>0],['id'=>50,'param'=>20]]]];
$next=[['id'=>1,'quantity'=>15,'options'=>array_reverse($old[0]['options'])],['id'=>2,'quantity'=>1,'options'=>[]]];
giftcode_assert_update_options($next,[json_encode($old)]);
giftcode_assert_update_options([], [json_encode($old)]);
giftcode_assert_update_options($next, []);
$history=[];for($i=0;$i<201;$i++)$history[]=['id'=>$i,'quantity'=>1,'options'=>[]];
giftcode_assert_update_options([['id'=>200,'quantity'=>2,'options'=>[]]],[json_encode($history)]);
$next[0]['options'][0]['param']=21;
foreach([[json_encode($old)],[null],['broken']] as $claims){
 try{giftcode_assert_update_options($next,$claims);throw new RuntimeException('unsafe update accepted');}catch(InvalidArgumentException $e){}
}
// An item removed from the current catalog remains protected by delivered history.
try{giftcode_assert_update_options([['id'=>1,'quantity'=>1,'options'=>[]]],[json_encode($old)]);throw new RuntimeException('readded option change accepted');}catch(InvalidArgumentException $e){}
$historical441=[['id'=>441,'quantity'=>1,'options'=>[['id'=>30,'param'=>0]]]];
giftcode_assert_update_options($historical441,[json_encode($historical441)]);
$historical441WithExtra=[['id'=>441,'quantity'=>1,'options'=>[['id'=>30,'param'=>0],['id'=>95,'param'=>5]]]];
giftcode_assert_update_options($historical441WithExtra,[json_encode($historical441)]);
try{giftcode_assert_update_options([['id'=>441,'quantity'=>1,'options'=>[]]],[json_encode($historical441)]);throw new RuntimeException('changed #441 options accepted');}catch(InvalidArgumentException $e){check_update(str_contains($e->getMessage(),'Cần giữ: #30:0; form đang gửi: không có option'),'option lock should explain required and submitted options');}
$row=['code'=>'ABC','count_left'=>2,'detail'=>json_encode($old),'expired'=>'2037-01-01 00:00:00'];
$fp=giftcode_fingerprint($row);$row['count_left']=1;
check_update($fp!==giftcode_fingerprint($row),'quota mutation must invalidate form');
$row['count_left']=2;$row['detail']=json_encode([['id'=>1,'quantity'=>10,'options'=>array_reverse($old[0]['options'])]],JSON_PRETTY_PRINT);
check_update($fp===giftcode_fingerprint($row),'fingerprint must ignore option order and JSON whitespace');
echo "giftcode_update_test PASS\n";
