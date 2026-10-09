<?php
require __DIR__.'/../admin/includes/admin_command_validator.php';
function assertReject($fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new Exception('invalid request accepted');}
assertReject(fn()=>validate_admin_command('UNKNOWN',[]));
assertReject(fn()=>validate_admin_command('UPDATE_PET',['player_id'=>1,'pet_name'=>'Custom']));
assertReject(fn()=>validate_admin_command('DELETE_ITEM',['player_id'=>1,'target'=>'bag','slot_index'=>0]));
assertReject(fn()=>validate_admin_command('GIFT_ITEM',['player_id'=>1,'target'=>'evil','item_id'=>1,'quantity'=>1]));
assertReject(fn()=>validate_admin_command('ACCOUNT_ACTION',['account_id'=>1,'action'=>'password','value'=>str_repeat('x',101)]));
$p=validate_admin_command('UPDATE_PET',['player_id'=>1,'pet_skill_1'=>'0','pet_power'=>'9007199254740993']);
if($p['pet_skill_1']!==0||$p['pet_power']!=='9007199254740993')throw new Exception('pet zero/long lost');
$p=validate_admin_command('UPDATE_STATS',['player_id'=>1,'limit_power'=>'4']);
if($p['limit_power']!==4||isset($p['hpg']))throw new Exception('patch fields changed');
echo "player command validation OK\n";
