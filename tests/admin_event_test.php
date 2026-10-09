<?php
require __DIR__.'/../admin/includes/admin_command_validator.php';
function checkEvent($ok,$message){if(!$ok)throw new Exception($message);}
$payload=['event_key'=>'summer','enabled'=>false,'revision'=>4];
checkEvent(validate_admin_command('EVENT_SET',$payload)===$payload,'event disable payload accepted');
foreach ([['event_key'=>'unknown'],['enabled'=>'false'],['revision'=>-1],['revision'=>1.5],['revision'=>'4'],['sql'=>'DELETE'],['run_id'=>8]] as $change) {
    try {validate_admin_command('EVENT_SET',array_replace($payload,$change));throw new Exception('invalid event payload accepted');}
    catch(InvalidArgumentException $expected){}
}
require __DIR__.'/../admin/includes/event_helper.php';
$event=admin_event_public_row(['event_key'=>'summer','name'=>'Summer','supported'=>'1','reason'=>'','consumed_ids'=>'[1,2]','kept_ids'=>'[3]','desired_enabled'=>'0','applied_enabled'=>'1','revision'=>'4','run_id'=>'7','status'=>'DISABLING','last_error'=>null,'updated_at'=>'2026-10-08 00:00:00','heartbeat_at'=>null,'secret'=>'hidden']);
checkEvent($event['consumed_ids']===[1,2] && $event['kept_ids']===[3],'item roles exposed separately');
checkEvent($event['desired_enabled']===false && $event['applied_enabled']===true,'requested and runtime state distinct');
checkEvent(!isset($event['secret']) && $event['revision']===4,'only public fields exposed');
checkEvent($event['heartbeat_at']===null,'missing heartbeat never implies healthy server');
$fresh=admin_event_public_row(['heartbeat_age_seconds'=>'12','consumed_ids'=>'[]','kept_ids'=>'[]']);
checkEvent($fresh['heartbeat_age_seconds']===12,'heartbeat age computed by database, without browser timezone');
echo "event validation/public state OK\n";
