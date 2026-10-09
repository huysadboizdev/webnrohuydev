<?php
// Isolated random database only; never select or mutate the application database.
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli('localhost','root','');$conn->set_charset('utf8mb4');
$testDb='boss_admin_test_'.bin2hex(random_bytes(8));
if(!preg_match('/^boss_admin_test_[a-f0-9]{16}$/D',$testDb))throw new RuntimeException('Unsafe test database');
$conn->query('CREATE DATABASE `'.$testDb.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');$conn->select_db($testDb);
function dbcheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
    $migrations=array_slice($argv,1);if(!$migrations)throw new RuntimeException('Pass migration SQL files as arguments');
    foreach($migrations as $migration){if(!is_file($migration))throw new RuntimeException('Missing migration: '.$migration);
    $conn->multi_query(file_get_contents($migration));do{if($result=$conn->store_result())$result->free();}while($conn->more_results()&&$conn->next_result());}
    $conn->query("CREATE TABLE server_command_queue(id INT AUTO_INCREMENT PRIMARY KEY,command_type VARCHAR(40),payload LONGTEXT,status VARCHAR(20),result_message TEXT NULL,created_by VARCHAR(80),request_id VARCHAR(64) UNIQUE,actor_account_id INT) ENGINE=InnoDB");
    $conn->query('CREATE TABLE item_template(id INT PRIMARY KEY) ENGINE=InnoDB');$conn->query('INSERT INTO item_template VALUES(1)');
    $conn->query('CREATE TABLE item_option_template(id INT PRIMARY KEY) ENGINE=InnoDB');$conn->query('INSERT INTO item_option_template VALUES(50)');
    $conn->query('CREATE TABLE boss_live_override(boss_id INT,phase INT,hp INT,damage INT) ENGINE=InnoDB');$conn->query('INSERT INTO boss_live_override VALUES(-32,0,500,5)');
    // Load unmodified production functions while suppressing the live connect include.
    $sync=file_get_contents(__DIR__.'/../admin/includes/live_sync_helper.php');
    $sync=str_replace("require_once __DIR__ . '/../../connect.php';",'',$sync);eval(substr($sync,5));
    // eval does not register live_sync_helper.php as included. Strip its second
    // include from this test-only copy; never reconnect to the application DB.
    require_once __DIR__.'/../admin/includes/admin_command_validator.php';
    $helper=file_get_contents(__DIR__.'/../admin/includes/boss_admin_helper.php');
    $helper=str_replace("require_once __DIR__.'/live_sync_helper.php';",'',$helper);
    $helper=str_replace("require_once __DIR__.'/admin_command_validator.php';",'',$helper);
    eval(substr($helper,5));
    dbcheck(boss_admin_read_status()['live']['fresh']===false,'Empty runtime must not report connected');
    $conn->query("INSERT INTO boss_admin_runtime(boss_key,phase,alive_count,applied_revision,server_run) VALUES('id:-32',0,1,0,'test')");
    $status=boss_admin_read_status();
    dbcheck($status['live']['fresh']===true&&count($status['runtime'])===1,'Fresh runtime status');
    $conn->query('UPDATE boss_admin_runtime SET updated_at=NOW()-INTERVAL 60 SECOND');
    dbcheck(boss_admin_read_status()['live']['fresh']===false,'Stale runtime must not report connected');
    $conn->query('UPDATE boss_admin_runtime SET updated_at=NOW()+INTERVAL 60 SECOND');
    dbcheck(boss_admin_read_status()['live']['fresh']===false,'Future runtime must not report connected');
    $conn->query('DELETE FROM boss_admin_runtime');
    $logged_user_id=42;$logged_username='test-admin';$_SESSION=['user_id'=>42];
    $catalog=[['key'=>'id:-32','phases'=>[['phase'=>0]]]];
    $p=['boss_key'=>'id:-32','phase'=>0,'expected_revision'=>0,'hp'=>1000,'damage'=>0,'cap_percent'=>'5.125','defense'=>1000,'critical'=>15,'drop_mode'=>'replace','drops_json'=>'[{"item_id":1,"quantity_min":1,"quantity_max":2,"chance_bp":125,"weight":1,"group":"","options":[{"id":50,"param":20}]}]'];
    $request='boss-admin-test-request-01';
    $first=boss_admin_save($p,$catalog,'save',$request);
    dbcheck($first['revision']===1,'Save increments global revision');
    $row=$conn->query('SELECT * FROM boss_admin_config')->fetch_assoc();dbcheck((int)$row['hp']===1000&&(int)$row['damage']===0&&$row['cap_percent']==='5.125'&&(int)$row['defense']===1000&&(int)$row['critical']===15,'Stats persisted');
    $queued=$conn->query('SELECT * FROM server_command_queue')->fetch_assoc();$payload=json_decode($queued['payload'],true);
    dbcheck($queued['status']==='pending'&&(int)$queued['actor_account_id']===42&&$payload['catalog_patch']['revision']===1,'Atomic pending queue and actor');
    dbcheck(boss_admin_read_status()['commands'][1]['status']==='pending','Read status exposes pending ACK');
    dbcheck(boss_admin_read_status()['configs']['id:-32'][0]['hp']===1000,'Status refresh includes current config values');
    $audit=$conn->query('SELECT * FROM boss_admin_audit')->fetch_assoc();dbcheck((int)$audit['actor_id']===42,'Audit actor from admin session');
    dbcheck(json_decode($audit['before_json'],true)['hp']===500,'First edit captures legacy fallback before state');
    $retry=boss_admin_save($p,$catalog,'save',$request);dbcheck($retry===$first&&(int)$conn->query('SELECT COUNT(*) n FROM boss_admin_audit')->fetch_assoc()['n']===1,'Retry does not mutate again');
    try{boss_admin_save(array_merge($p,['hp'=>2000]),$catalog,'save',$request);throw new RuntimeException('Request conflict accepted');}catch(InvalidArgumentException $expected){}
    try{boss_admin_save($p,$catalog,'save','boss-admin-test-stale-01');throw new RuntimeException('Stale row accepted');}catch(InvalidArgumentException $expected){}
    dbcheck((int)$conn->query('SELECT revision FROM boss_admin_state')->fetch_assoc()['revision']===1,'Rejected saves preserve global revision');
    $p['expected_revision']=1;$reset=boss_admin_save($p,$catalog,'reset','boss-admin-test-reset-01');dbcheck($reset['revision']===2,'Reset creates revision');
    $row=$conn->query('SELECT * FROM boss_admin_config')->fetch_assoc();dbcheck($row['hp']===null&&$row['damage']===null&&$row['defense']===null&&$row['critical']===null&&$row['drop_mode']==='default'&&$row['apply_mode']==='alive','Reset restores defaults immediately for living bosses');
    $resetAudit=$conn->query('SELECT id FROM boss_admin_audit ORDER BY id DESC LIMIT 1')->fetch_assoc()['id'];
    $p['expected_revision']=2;$p['audit_id']=$resetAudit;$rollback=boss_admin_save($p,$catalog,'rollback','boss-admin-test-rollback-01');
    dbcheck($rollback['revision']===3&&(int)$conn->query('SELECT hp FROM boss_admin_config')->fetch_assoc()['hp']===1000&&(int)$conn->query('SELECT defense,critical FROM boss_admin_config')->fetch_assoc()['defense']===1000,'Rollback restores before state in new revision');
    dbcheck((int)$conn->query('SELECT COUNT(*) n FROM boss_admin_audit')->fetch_assoc()['n']===3,'History append only');
    // Queue failure must roll back config, global revision and audit together.
    $conn->query("CREATE TRIGGER fail_queue BEFORE INSERT ON server_command_queue FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test queue failure'");
    $p['expected_revision']=3;$p['hp']=2000;
    try{boss_admin_save($p,$catalog,'save','boss-admin-test-failure-01');throw new RuntimeException('Queue failure accepted');}catch(RuntimeException $expected){dbcheck($expected->getMessage()!=='Queue failure accepted','Queue must fail');}
    dbcheck((int)$conn->query('SELECT hp FROM boss_admin_config')->fetch_assoc()['hp']===1000&&(int)$conn->query('SELECT revision FROM boss_admin_state')->fetch_assoc()['revision']===3&&(int)$conn->query('SELECT COUNT(*) n FROM boss_admin_audit')->fetch_assoc()['n']===3,'Queue failure is atomic');
    $conn->query('DROP TRIGGER fail_queue');
    unset($logged_user_id);$_SESSION=['user_id'=>42];
    $sessionSave=boss_admin_save($p,$catalog,'save','boss-admin-test-session-01');
    $sessionQueue=$conn->query('SELECT actor_account_id FROM server_command_queue WHERE id='.(int)$sessionSave['command_id'])->fetch_assoc();
    dbcheck((int)$sessionQueue['actor_account_id']===42,'Queue identity must match the audit session identity');
    unset($logged_user_id);$_SESSION=[];$p['expected_revision']=4;
    try{boss_admin_save($p,$catalog,'save','boss-admin-test-noactor-01');throw new RuntimeException('Anonymous save accepted');}catch(InvalidArgumentException $expected){}
    dbcheck((int)$conn->query('SELECT revision FROM boss_admin_state')->fetch_assoc()['revision']===4,'Missing admin identity must not mutate config');
    echo "Boss admin isolated DB save/reset/rollback/retry/stale/queue failure/identity/status passed\n";
}finally{$conn->query('DROP DATABASE `'.$testDb.'`');$conn->close();}
