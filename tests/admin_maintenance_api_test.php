<?php
require __DIR__.'/../admin/includes/admin_command_validator.php';
foreach (['MAINTENANCE_START','MAINTENANCE_OPEN'] as $type) {
    try {$payload=validate_admin_command($type,[]);} catch (Throwable $e) {throw new Exception('Empty maintenance payload must be accepted: '.$e->getMessage());}
    if ($payload!==[]) throw new Exception('Unexpected maintenance payload');
    foreach ([['command'=>'shutdown'],['path'=>'x'],['revision'=>1],null,'',1] as $bad) {
        try {validate_admin_command($type,$bad);throw new Exception('Accepted arbitrary maintenance payload');}
        catch (InvalidArgumentException $expected) {}
    }
}
require __DIR__.'/../admin/includes/server_maintenance_helper.php';
class MaintenanceRows {private $rows;function __construct($rows){$this->rows=$rows;}function fetch_assoc(){return array_shift($this->rows);}}
class MaintenanceDB {
    public $queries=[]; public $missing=false;public $age=5;
    function query($sql) {
        $this->queries[]=$sql;
        if ($this->missing) throw new RuntimeException('Missing migration');
        if (strpos($sql,'server_maintenance_state')!==false) return new MaintenanceRows([['phase'=>'MAINTENANCE','revision'=>'9007199254740993','command_id'=>'22','server_run'=>'run','heartbeat_at'=>'2026-10-20 12:00:00','heartbeat_age_seconds'=>$this->age,'message'=>'Saved','updated_at'=>'2026-10-20 12:00:00']]);
        return new MaintenanceRows([['id'=>'22','command_type'=>'MAINTENANCE_START','created_by'=>'admin','request_id'=>'unique','status'=>'completed','result_message'=>'accepted','created_at'=>'today','executed_at'=>'today']]);
    }
}
$db=new MaintenanceDB();$data=admin_read_server_maintenance($db);
if (!$data['available'] || $data['heartbeat_stale'] || $data['state']['revision']!=='9007199254740993' || $data['history'][0]['created_by']!=='admin') throw new Exception('Fresh state/history contract');
$db->age=31;if (!admin_read_server_maintenance($db)['heartbeat_stale']) throw new Exception('Old heartbeat must be stale');
$db->age=null;if (!admin_read_server_maintenance($db)['heartbeat_stale']) throw new Exception('Missing heartbeat must be stale');
$db->missing=true;if (admin_read_server_maintenance($db)['available']) throw new Exception('Missing migration must be unavailable');
foreach ($db->queries as $sql) if (!preg_match('/^SELECT /',$sql)) throw new Exception('Status reader attempted mutation');
echo "Maintenance strict payload/read-only state/history/stale/missing schema OK\n";
foreach (['maintenance_start'=>202,'maintenance_open'=>202,'maintenance_fields'=>400,'maintenance_csrf'=>403,'guest'=>403,'ordinary'=>403,'banned'=>403] as $case=>$expected) {
    $process=proc_open([PHP_BINARY,'-n',__DIR__.'/admin_api_fixture.php',$case,'live_sync'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$body=stream_get_contents($pipes[1]);$meta=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
    $result=json_decode($body,true);$info=json_decode($meta,true);
    if ($code!==0 || !$result || !$info || $info['http']!==$expected || $info['writes']!==($expected===202?1:0)) throw new Exception("$case: $body $meta");
    if ($expected===202 && $result['status']!=='queued') throw new Exception('Enqueue must not report completed');
}
foreach (['guest'=>403,'ordinary'=>403,'banned'=>403,'method'=>405,'maintenance_get'=>503] as $case=>$expected) {
    $process=proc_open([PHP_BINARY,'-n',__DIR__.'/admin_api_fixture.php',$case,'server_status'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$body=stream_get_contents($pipes[1]);$meta=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);$info=json_decode($meta,true);
    if ($code!==0 || !$info || $info['http']!==$expected || $info['writes']!==0) throw new Exception("status/$case: $body $meta");
}
echo "Maintenance queue/CSRF/admin/read-only API contracts OK\n";
