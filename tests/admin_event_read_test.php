<?php
require __DIR__.'/../admin/includes/event_helper.php';
class EventRows {
    private $rows;
    function __construct($rows){$this->rows=$rows;}
    function fetch_assoc(){return array_shift($this->rows);}
}
class EventReadDB {
    public $queries=[];
    function query($sql) {
        $this->queries[]=$sql;
        if (strpos($sql,'SHOW COLUMNS')===0) throw new RuntimeException('cleanup table missing');
        return new EventRows([['event_key'=>'summer','name'=>'Summer','supported'=>1,'reason'=>'','consumed_ids'=>'[1]','kept_ids'=>'[2]','revision'=>null,'status'=>null]]);
    }
}
$db=new EventReadDB();
$events=admin_read_events($db);
if(count($events)!==1 || $events[0]['cleanup_available']!==false || $events[0]['state_available']!==false)throw new Exception('missing cleanup table/state must disable controls without hiding catalog');
foreach($db->queries as $sql) if(!preg_match('/^(SELECT|SHOW) /',$sql))throw new Exception('read API attempted SQL mutation');
echo "event read-only schema fallback OK\n";
