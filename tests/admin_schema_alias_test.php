<?php
require __DIR__.'/admin_queue_contract_test.php';
require __DIR__.'/../admin/includes/player_data_helper.php';
class SchemaDatabase extends mysqli {
 function prepare($sql){return new SchemaStatement($sql);}
}
class SchemaStatement {
 private $sql;function __construct($s){$this->sql=$s;}function bind_param($t,&...$v){}function execute(){return true;}function close(){}
 function get_result(){return new FakeResult(strpos($this->sql,'NAME AS name')!==false?['name'=>'Actual template name']:['NAME'=>'Actual template name']);}
}
$conn=new SchemaDatabase();
check(get_skill_name(0)==='Actual template name','skill schema NAME alias');
check(get_map_name(21)==='Actual template name','map schema NAME alias');
check(get_task_info(1)['name']==='Actual template name','task schema NAME alias');
echo "schema name aliases OK\n";
