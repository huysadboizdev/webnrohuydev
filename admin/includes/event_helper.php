<?php
// Read-only event projection. Inventory mutations belong to the game server.
function admin_event_public_row(array $row) {
    $out=[];
    foreach (['event_key','name','reason','status','last_error','updated_at','heartbeat_at'] as $field) $out[$field]=$row[$field]??null;
    foreach (['supported','desired_enabled','applied_enabled'] as $field) $out[$field]=(bool)(int)($row[$field]??0);
    foreach (['revision','run_id'] as $field) $out[$field]=(int)($row[$field]??0);
    $out['heartbeat_age_seconds']=isset($row['heartbeat_age_seconds'])?(int)$row['heartbeat_age_seconds']:null;
    foreach (['consumed_ids','kept_ids'] as $field) {
        $ids=json_decode($row[$field]??'[]',true);
        if (!is_array($ids) || array_filter($ids,static function($id){return !is_int($id) || $id<0;})) throw new RuntimeException('Invalid event item catalog');
        $out[$field]=array_values($ids);
    }
    return $out;
}
function admin_read_events($db) {
    $result=$db->query('SELECT c.event_key,c.name,c.supported,c.reason,c.consumed_ids,c.kept_ids,s.desired_enabled,s.applied_enabled,s.revision,s.run_id,s.status,s.last_error,s.updated_at,s.heartbeat_at,TIMESTAMPDIFF(SECOND,s.heartbeat_at,CURRENT_TIMESTAMP) AS heartbeat_age_seconds FROM game_event_catalog c LEFT JOIN game_event_state s ON s.event_key=c.event_key ORDER BY c.event_key');
    if (!$result) throw new RuntimeException('Event schema unavailable');
    $events=[];
    while ($row=$result->fetch_assoc()) {
        $event=admin_event_public_row($row);
        // Missing state fails closed until the server initializes the event.
        $event['state_available']=$row['revision']!==null;
        $event['cleanup_jobs']=[];
        $events[$event['event_key']]=$event;
    }
    // Older deployments may not have the cleanup table yet.
    try {$columns=$db->query("SHOW COLUMNS FROM game_event_cleanup_job");}
    catch (Throwable $error) {$columns=false;}
    $available=[];
    if ($columns) while ($column=$columns->fetch_assoc()) $available[]=$column['Field'];
    $cleanupAvailable=count(array_diff(['id','event_key','run_id','status','processed_players','error_message'],$available))===0;
    if ($cleanupAvailable) {
        $jobs=$db->query('SELECT id,event_key,run_id,status,processed_players,error_message FROM game_event_cleanup_job ORDER BY id DESC');
        if (!$jobs) throw new RuntimeException('Cannot inspect cleanup jobs');
        while ($job=$jobs->fetch_assoc()) if (isset($events[$job['event_key']])) {
            foreach (['id','run_id','processed_players'] as $field) $job[$field]=(int)$job[$field];
            $events[$job['event_key']]['cleanup_jobs'][]=$job;
        }
    }
    foreach ($events as &$event) $event['cleanup_available']=$cleanupAvailable;
    unset($event);
    return array_values($events);
}
