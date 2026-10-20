<?php
// Read only: the Java migration/coordinator owns this singleton and its heartbeat.
function admin_read_server_maintenance($db) {
    $unavailable=['available'=>false,'heartbeat_stale'=>true,'state'=>null,'history'=>[],
        'message'=>'Chưa đọc được trạng thái bảo trì. Kiểm tra migration và kết nối SQL.'];
    try {
        $result=$db->query('SELECT phase, revision, command_id, server_run, heartbeat_at, TIMESTAMPDIFF(SECOND, heartbeat_at, CURRENT_TIMESTAMP) AS heartbeat_age_seconds, message, updated_at FROM server_maintenance_state WHERE id = 1');
        if (!$result || !($state=$result->fetch_assoc())) return $unavailable;
        if (!in_array($state['phase'],['OPEN','DRAINING','MAINTENANCE','FAILED'],true)) return $unavailable;
        $state['revision']=(string)$state['revision'];
        $state['command_id']=$state['command_id']===null?null:(string)$state['command_id'];
        $age=$state['heartbeat_age_seconds']===null?null:(int)$state['heartbeat_age_seconds'];
        $state['heartbeat_age_seconds']=$age;
        $history=[];
        $rows=$db->query("SELECT id, command_type, created_by, request_id, status, result_message, created_at, executed_at FROM server_command_queue WHERE command_type IN ('MAINTENANCE_START','MAINTENANCE_OPEN') ORDER BY id DESC LIMIT 20");
        if (!$rows) return $unavailable;
        while ($row=$rows->fetch_assoc()) {$row['id']=(string)$row['id'];$history[]=$row;}
        return ['available'=>true,'heartbeat_stale'=>$age===null || $age<0 || $age>30,'state'=>$state,'history'=>$history,'message'=>''];
    } catch (Throwable $error) {return $unavailable;}
}
