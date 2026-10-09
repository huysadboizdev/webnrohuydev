<?php
require __DIR__.'/../admin/includes/admin_command_validator.php';
require __DIR__.'/../admin/includes/boss_live_helper.php';
function rejected($p){try{validate_boss_override($p);}catch(InvalidArgumentException $e){return;}throw new Exception('invalid override accepted');}
$p=validate_boss_override(['boss_id'=>-28,'phase'=>0,'hp'=>500,'damage'=>'']);
if($p['hp']!==500||$p['damage']!==null)throw new Exception('nullable default lost');
rejected(['boss_id'=>999999,'hp'=>500]);rejected(['boss_id'=>-28,'hp'=>0]);rejected(['boss_id'=>-28,'hp'=>500,'phase'=>32]);rejected(['boss_id'=>-28]);
validate_admin_command('RELOAD_BOSS',[]);
echo "boss override validation/defaults/reload mapping PASS\n";
