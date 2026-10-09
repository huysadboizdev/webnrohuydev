<?php
foreach(['player_actions','live_sync'] as $endpoint)foreach(['guest'=>403,'ordinary'=>403,'banned'=>403,'method'=>405,'missing'=>403,'wrong'=>403,'unknown'=>400,'valid'=>202] as $case=>$expected){
 $cmd=[PHP_BINARY,'-n',__DIR__.'/admin_api_fixture.php',$case,$endpoint];
 $p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 fclose($pipes[0]);$body=stream_get_contents($pipes[1]);$meta=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($p);
 $data=json_decode($body,true);$result=json_decode($meta,true);
 if(!$data||!$result||$result['http']!==$expected||$result['writes']!==($case==='valid'?1:0))throw new Exception("$endpoint/$case: $body $meta");
 if($case==='valid'&&($data['status']!=='queued'||$data['command_id']!==1))throw new Exception('Queue response must not claim completion');
}
echo "16 API authentication/method/CSRF/allowlist/queued contracts OK\n";
