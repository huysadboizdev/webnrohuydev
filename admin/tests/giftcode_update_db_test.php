<?php
// All writes are restricted to a unique disposable codex_gift_test_* schema.
require __DIR__.'/../includes/live_sync_helper.php';
require __DIR__.'/../includes/giftcode_helper.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$schema='codex_gift_test_web_'.bin2hex(random_bytes(6));
if(!preg_match('/^codex_gift_test_[a-z0-9_]+$/D',$schema))throw new RuntimeException('Unsafe test schema');
$conn->query('CREATE DATABASE `'.$schema.'`');$conn->select_db($schema);
function db_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function expect_rejection(callable $fn,string $message): void {try{$fn();throw new RuntimeException($message);}catch(InvalidArgumentException $e){}}
try{
 $conn->query('CREATE TABLE giftcode(id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin UNIQUE,count_left INT NOT NULL,detail LONGTEXT NOT NULL,expired DATETIME NOT NULL) ENGINE=InnoDB');
 $conn->query('CREATE TABLE giftcode_claim(account_id INT NOT NULL,canonical_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,delivered_detail LONGTEXT NULL,PRIMARY KEY(account_id,canonical_code)) ENGINE=InnoDB');
 $conn->query('CREATE TABLE item_template(id INT PRIMARY KEY,type INT NOT NULL) ENGINE=InnoDB');
 $conn->query('CREATE TABLE item_option_template(id INT PRIMARY KEY) ENGINE=InnoDB');
 $conn->query("CREATE TABLE server_command_queue(id INT AUTO_INCREMENT PRIMARY KEY,command_type VARCHAR(100),payload LONGTEXT,status VARCHAR(30),created_by VARCHAR(100),request_id VARCHAR(64) UNIQUE,actor_account_id INT) ENGINE=InnoDB");
 $conn->query('INSERT INTO item_template VALUES(1,0),(2,0),(3,13)');$conn->query('INSERT INTO item_option_template VALUES(0),(30),(50),(95)');
 $logged_user_id=123;$logged_username='isolated-test';
 $old=giftcode_validate('TEST',2,'2037-01-01T00:00','[{"id":1,"quantity":10,"options":[{"id":0,"param":0},{"id":50,"param":20}]}]');
 $request=admin_new_request_id();$payload=['code'=>'TEST'];
 $queued=admin_catalog_transaction(fn()=>giftcode_insert($conn,$old),'RELOAD_GIFTCODE',$payload,$request);
 $row=$conn->query('SELECT * FROM giftcode')->fetch_assoc();$id=(int)$row['id'];$fp=giftcode_fingerprint($row);
 db_check(admin_catalog_transaction(fn()=>throw new RuntimeException('idempotent retry reapplied mutation'),'RELOAD_GIFTCODE',$payload,$request)===$queued,'retry must return original queue');
 $q=$conn->prepare('INSERT INTO giftcode_claim VALUES(1,?,?)');$q->bind_param('ss',$old['code'],$old['detail']);$q->execute();$q->close();
 $next=giftcode_validate('TEST',2,'2037-01-01T00:00','[{"id":1,"quantity":15,"options":[{"id":50,"param":20},{"id":0,"param":0},{"id":95,"param":5}]},{"id":2,"quantity":1,"options":[]}]');
 admin_catalog_transaction(fn()=>giftcode_update($conn,$id,$next,$fp),'RELOAD_GIFTCODE',['code'=>'TEST','catalog_patch'=>['version'=>2]],admin_new_request_id());
 expect_rejection(fn()=>admin_catalog_transaction(fn()=>giftcode_update($conn,$id,$old,$fp),'RELOAD_GIFTCODE',[],admin_new_request_id()),'stale update accepted');
 $row=$conn->query('SELECT * FROM giftcode')->fetch_assoc();$fp=giftcode_fingerprint($row);$before=$row;
 $changed=$next;$changed['detail']='[{"id":1,"quantity":15,"options":[{"id":50,"param":21},{"id":0,"param":0},{"id":95,"param":5}]}]';
 expect_rejection(fn()=>admin_catalog_transaction(fn()=>giftcode_update($conn,$id,$changed,$fp),'RELOAD_GIFTCODE',[],admin_new_request_id()),'changed delivered options accepted');
 db_check($conn->query('SELECT * FROM giftcode')->fetch_assoc()===$before,'rejected edit must rollback');
 // Legacy catalog may already differ from the options delivered to earlier claimants.
 $q=$conn->prepare('UPDATE giftcode SET detail=? WHERE id=?');$q->bind_param('si',$changed['detail'],$id);$q->execute();$q->close();
 $row=$conn->query('SELECT * FROM giftcode')->fetch_assoc();$fp=giftcode_fingerprint($row);
 $sameCurrent=giftcode_validate('TEST',3,'2037-01-01T00:00',$changed['detail']);
 admin_catalog_transaction(fn()=>giftcode_update($conn,$id,$sameCurrent,$fp),'RELOAD_GIFTCODE',[],admin_new_request_id());
 $row=$conn->query('SELECT * FROM giftcode')->fetch_assoc();$fp=giftcode_fingerprint($row);
 $changeAgain=$sameCurrent;$changeAgain['detail']='[{"id":1,"quantity":15,"options":[{"id":50,"param":22},{"id":0,"param":0},{"id":95,"param":5}]}]';
 expect_rejection(fn()=>admin_catalog_transaction(fn()=>giftcode_update($conn,$id,$changeAgain,$fp),'RELOAD_GIFTCODE',[],admin_new_request_id()),'new option change accepted after legacy mismatch');
 $conn->query('UPDATE giftcode SET count_left=1');
 expect_rejection(fn()=>admin_catalog_transaction(fn()=>giftcode_update($conn,$id,$next,$fp),'RELOAD_GIFTCODE',[],admin_new_request_id()),'claim quota overwrite accepted');
 $row=$conn->query('SELECT * FROM giftcode')->fetch_assoc();$fp=giftcode_fingerprint($row);
 $bad=$next;$bad['detail']='[{"id":3,"quantity":1,"options":[]}]';
 expect_rejection(fn()=>admin_catalog_transaction(fn()=>giftcode_update($conn,$id,$bad,$fp),'RELOAD_GIFTCODE',[],admin_new_request_id()),'unsupported catalog reward accepted');
 $conn->query('DELETE FROM giftcode');
 expect_rejection(fn()=>admin_catalog_transaction(fn()=>giftcode_insert($conn,$changed),'RELOAD_GIFTCODE',[],admin_new_request_id()),'recreated delivered options accepted');
 admin_catalog_transaction(fn()=>giftcode_insert($conn,$next),'RELOAD_GIFTCODE',['code'=>'TEST','catalog_patch'=>['version'=>3]],admin_new_request_id());
 $conn->query("INSERT INTO giftcode_claim VALUES(2,'TEST',NULL)");$row=$conn->query('SELECT * FROM giftcode')->fetch_assoc();$id=(int)$row['id'];$fp=giftcode_fingerprint($row);
 expect_rejection(fn()=>admin_catalog_transaction(fn()=>giftcode_update($conn,$id,$next,$fp),'RELOAD_GIFTCODE',[],admin_new_request_id()),'missing historical baseline accepted');
 db_check((int)$conn->query('SELECT COUNT(*) n FROM server_command_queue')->fetch_assoc()['n']===4,'only accepted mutations may queue reload');
 // A catalog validation read establishes an old RR snapshot before recreation.
 // A concurrent first claim and catalog deletion must still protect its options.
 $race=$old;$race['code']='RACE';
 admin_catalog_transaction(fn()=>giftcode_insert($conn,$race),'RELOAD_GIFTCODE',['code'=>'RACE'],admin_new_request_id());
 $other=new mysqli($ip_sv,$user_sv,$pass_sv,$schema);$other->set_charset('utf8mb4');
 $conn->begin_transaction();giftcode_validate_catalog($conn,$race);
 $other->begin_transaction();$other->query("SELECT id FROM giftcode WHERE code='RACE' FOR UPDATE");
 $q=$other->prepare("INSERT INTO giftcode_claim VALUES(3,'RACE',?)");$q->bind_param('s',$old['detail']);$q->execute();$q->close();
 $other->query("DELETE FROM giftcode WHERE code='RACE'");$other->commit();
 $race['detail']=$changed['detail'];
 try{expect_rejection(fn()=>giftcode_insert($conn,$race),'stale RR snapshot bypassed recreated option guard');}finally{$conn->rollback();$other->close();}
 // Render the actual admin page against this disposable schema; no real session/account edits.
 $conn->query('CREATE TABLE account(id INT PRIMARY KEY,username VARCHAR(100),is_admin INT,admin INT,ban INT) ENGINE=InnoDB');
 $conn->query("INSERT INTO account VALUES(123,'isolated-test',1,1,0)");
 $conn->query('ALTER TABLE item_template ADD name VARCHAR(255),ADD icon_id INT DEFAULT 0');
 $conn->query('ALTER TABLE item_option_template ADD name VARCHAR(255)');
 $conn->query("UPDATE item_template SET name='<img onerror=alert(1)>'");
 $conn->query("UPDATE item_option_template SET name='Value # <script>alert(1)</script>'");
 $sessionDir=__DIR__.'/../../.analysis/giftcode-test-sessions';if(!is_dir($sessionDir))mkdir($sessionDir,0777,true);
 session_save_path($sessionDir);session_start();$_SESSION['user_id']=123;$_SERVER['REQUEST_METHOD']='GET';
 ob_start();require __DIR__.'/../giftcodes.php';$html=ob_get_clean();
 db_check(str_contains($html,'data-edit-giftcode='),'actual page missing edit');
 db_check(str_contains($html,'&lt;img onerror=alert(1)&gt;'),'catalog item escaped');
 db_check(str_contains($html,'Value 20 &lt;script&gt;alert(1)&lt;/script&gt;'),'catalog options displayed and escaped');
 db_check(str_contains($html,'giữ nguyên mọi option cũ; có thể thêm option mới'),'option lock policy hint rendered');
 db_check(str_contains($html,'\\u003Cscript\\u003E'),'script bootstrap must encode catalog markup');
 db_check(str_contains($html,'id="optionRows"')&&str_contains($html,'name="expected_fingerprint"'),'editor and conflict fields rendered');
 session_destroy();
 echo "giftcode_update_db_test PASS: create/update/recreate, option lock, quota conflict, retry, rollback, catalog and NULL baseline\n";
 echo "giftcode actual-page render PASS: edit UI/options/catalog escaping/JSON bootstrap\n";
}finally{$conn->select_db('information_schema');$conn->query('DROP DATABASE `'.$schema.'`');}
