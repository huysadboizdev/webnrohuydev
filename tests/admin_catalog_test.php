<?php
require __DIR__.'/admin_queue_contract_test.php';
$conn->fail=false;
class CatalogDB extends mysqli {
 public $value=0;private $before=0;
 function begin_transaction(){$this->before=$this->value;return true;}
 function commit(){return true;}
 function rollback(){$this->value=$this->before;return true;}
}
$conn=new CatalogDB();
$ok=admin_catalog_transaction(function()use($conn){$conn->value=99;},'RELOAD_SHOP',[]);
check($ok===1&&$conn->value===99,'catalog commits update with reload');
$conn->fail=true;
try{admin_catalog_transaction(function()use($conn){$conn->value=50;},'RELOAD_SHOP',[]);throw new Exception('expected failure');}catch(RuntimeException $e){}
check($conn->value===99,'enqueue failure rolls back catalog update');
$conn->fail=false;
$key='550e8400-e29b-41d4-a716-446655440001';
$id=admin_catalog_transaction(function()use($conn){$conn->value*=2;},'RELOAD_MOB',['bulk'=>true,'catalog_patch'=>['factor'=>2]],$key);
$id2=admin_catalog_transaction(function()use($conn){$conn->value*=2;},'RELOAD_MOB',['bulk'=>true,'catalog_patch'=>['factor'=>2]],$key);
check($id===$id2&&$conn->value===198,'retry must not multiply catalog twice');
try{admin_catalog_transaction(function()use($conn){$conn->value*=3;},'RELOAD_MOB',['bulk'=>true,'catalog_patch'=>['factor'=>3]],$key);throw new Exception('expected conflict');}catch(RuntimeException $e){}
check($conn->value===198,'conflicting request cannot mutate catalog');
echo "catalog transaction OK\n";
