<?php
foreach(['event_valid'=>202,'event_bad_bool'=>400,'event_bad_revision'=>400,'event_unknown'=>400,'event_csrf'=>403] as $case=>$expected){
 $process=proc_open([PHP_BINARY,'-n',__DIR__.'/admin_api_fixture.php',$case,'live_sync'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 fclose($pipes[0]);$body=stream_get_contents($pipes[1]);$meta=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
 $data=json_decode($body,true);$result=json_decode($meta,true);
 if($code!==0 || !$data || !$result || $result['http']!==$expected || $result['writes']!==($case==='event_valid'?1:0))throw new Exception("$case: $body $meta");
 if($case==='event_valid'&&$data['status']!=='queued')throw new Exception('EVENT_SET must return queued, never applied success');
}
foreach(['guest'=>403,'ordinary'=>403,'banned'=>403,'method'=>405,'valid'=>405] as $case=>$expected){
 $process=proc_open([PHP_BINARY,'-n',__DIR__.'/admin_api_fixture.php',$case,'events'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 fclose($pipes[0]);$body=stream_get_contents($pipes[1]);$meta=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
 $result=json_decode($meta,true);
 if($code!==0 || !$result || $result['http']!==$expected || $result['writes']!==0)throw new Exception("events/$case: $body $meta");
}
echo "10 event queue/CSRF/allowlist/auth/read-only method contracts OK\n";
