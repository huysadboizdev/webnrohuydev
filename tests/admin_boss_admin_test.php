<?php
$helper=__DIR__.'/../admin/includes/boss_admin_helper.php';
if(!is_file($helper)){fwrite(STDERR,"FAIL: boss admin validator missing\n");exit(1);}require $helper;
$catalog=[['key'=>'id:1','phases'=>[['phase'=>0],['phase'=>2]]]];
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function rejects($p,$catalog){try{boss_admin_validate($p,$catalog);}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected rejection');}
$base=['boss_key'=>'id:1','phase'=>'2','expected_revision'=>'0','hp'=>'','damage'=>'0','cap_percent'=>'5.125','drops_json'=>'[]'];
$v=boss_admin_validate($base,$catalog);
check($v['hp']===null&&$v['damage']===0&&$v['cap_percent']==='5.125','Unset and zero are distinct');
rejects(array_merge($base,['phase'=>1]),$catalog);
rejects(array_merge($base,['hp'=>'2147483648']),$catalog);
rejects(array_merge($base,['cap_percent'=>'100.001']),$catalog);
rejects(array_merge($base,['cap_percent'=>'1e2']),$catalog);
rejects(array_merge($base,['defense'=>'-1']),$catalog);
rejects(array_merge($base,['critical'=>'101']),$catalog);
$v=boss_admin_validate(array_merge($base,['defense'=>'1000','critical'=>'15']),$catalog);
check($v['defense']===1000&&$v['critical']===15,'Defense/crit retained');
rejects(array_merge($base,['drops_json'=>'{}']),$catalog);
rejects(array_merge($base,['drops_json'=>'[null]']),$catalog);
rejects(array_merge($base,['drops_json'=>'[{"item_id":1,"quantity_min":1,"quantity_max":1,"chance_bp":100,"options":{}}]']),$catalog);
$drop=['group'=>'a','item_id'=>1,'quantity_min'=>1,'quantity_max'=>2,'chance_bp'=>125,'weight'=>3,'options'=>[['id'=>50,'param'=>20]]];
$v=boss_admin_validate(array_merge($base,['drop_mode'=>'replace','drops_json'=>json_encode([$drop])]),$catalog);
check($v['drops'][0]['chance_bp']===125,'Fractional percent retained');
rejects(array_merge($base,['drops_json'=>json_encode([$drop,array_merge($drop,['chance_bp'=>126])])]),$catalog);
rejects(array_merge($base,['drops_json'=>json_encode([array_merge($drop,['options'=>[['id'=>50,'param'=>20],['id'=>50,'param'=>1]]])])]),$catalog);
check(boss_admin_defaults()['hp']===null,'Reset keeps defaults unset');
check(boss_admin_defaults()['apply_mode']==='alive','New edits and resets must apply to living bosses by default');
check(function_exists('boss_admin_request_hash'),'Idempotent request fingerprint missing');
check(boss_admin_request_hash($v,'save',0)!==boss_admin_request_hash($v,'reset',0),'Action belongs to retry fingerprint');
check(boss_admin_revision(7,7)===8,'Optimistic revision advances');
try{boss_admin_revision(7,6);throw new RuntimeException('Stale revision accepted');}catch(InvalidArgumentException $e){}
check(!boss_admin_heartbeat_is_fresh(null),'No heartbeat must not report connected');
check(boss_admin_heartbeat_is_fresh(0)&&boss_admin_heartbeat_is_fresh(44),'Recent heartbeat is connected');
check(!boss_admin_heartbeat_is_fresh(45)&&!boss_admin_heartbeat_is_fresh(11898),'Expired heartbeat is disconnected');
check(!boss_admin_heartbeat_is_fresh(-1),'Future heartbeat must not report connected');
echo "Boss admin validation tests passed\n";
