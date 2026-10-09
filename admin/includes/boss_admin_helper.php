<?php
require_once __DIR__.'/admin_command_validator.php';
require_once __DIR__.'/live_sync_helper.php';
function boss_admin_html($value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function boss_admin_heartbeat_is_fresh(?int $age): bool {return $age!==null&&$age>=0&&$age<45;}
function boss_admin_read_status(): array {
    $runtime=boss_admin_select('SELECT boss_key,phase,alive_count,current_hp,max_hp,damage,applied_revision,updated_at,TIMESTAMPDIFF(SECOND,updated_at,NOW()) AS heartbeat_age FROM boss_admin_runtime','',[]);
    $heartbeat=boss_admin_select('SELECT MAX(updated_at) AS last_heartbeat,TIMESTAMPDIFF(SECOND,MAX(updated_at),NOW()) AS age_seconds FROM boss_admin_runtime','',[])[0];
    $age=$heartbeat['age_seconds']===null?null:(int)$heartbeat['age_seconds'];
    $commands=[];
    foreach(boss_admin_select("SELECT id,payload,status,result_message FROM server_command_queue WHERE command_type='RELOAD_BOSS' ORDER BY id DESC LIMIT 300",'',[]) as $command){
        $payload=json_decode($command['payload'],true);
        if(!empty($payload['catalog_patch']['boss_admin'])&&isset($payload['catalog_patch']['revision'])){
            unset($command['payload']);
            $commands[(string)$payload['catalog_patch']['revision']]=$command;
        }
    }
    return ['runtime'=>$runtime,'commands'=>$commands,'configs'=>boss_admin_read_configs(),'live'=>['fresh'=>boss_admin_heartbeat_is_fresh($age),'age_seconds'=>$age,'last_heartbeat'=>$heartbeat['last_heartbeat']]];
}
function boss_admin_read_configs(): array {
    $configs=[];
    foreach(boss_admin_select('SELECT * FROM boss_admin_config','',[]) as $row)
        $configs[$row['boss_key']][(string)$row['phase']]=boss_admin_row_config($row)+['revision'=>(int)$row['revision']];
    foreach(boss_admin_select('SELECT boss_id,phase,hp,damage FROM boss_live_override','',[]) as $row){
        $key='id:'.$row['boss_id'];$phase=(string)$row['phase'];
        if(!isset($configs[$key][$phase])){
            $c=boss_admin_defaults();$c['apply_mode']='next';
            foreach(['hp','damage'] as $k)$c[$k]=$row[$k]===null?null:(int)$row[$k];
            $configs[$key][$phase]=$c+['revision'=>0,'legacy'=>true];
        }
    }
    return $configs;
}
function boss_admin_defaults(): array {return ['hp'=>null,'damage'=>null,'cap_damage'=>null,'cap_percent'=>null,'defense'=>null,'critical'=>null,'apply_mode'=>'alive','drop_mode'=>'default','drops'=>[]];}
function boss_admin_revision($current,$expected): int {
    $current=admin_integer($current,0,9007199254740990);$expected=admin_integer($expected,0,9007199254740990);
    if($current!==$expected)throw new InvalidArgumentException('Cấu hình đã thay đổi. Tải lại trang trước khi sửa.');
    return $current+1;
}
function boss_admin_validate(array $p,array $catalog): array {
    $key=$p['boss_key']??null;$entry=null;
    if(!is_string($key))throw new InvalidArgumentException('Boss không hợp lệ');
    foreach($catalog as $candidate)if($candidate['key']===$key){$entry=$candidate;break;}
    if(!$entry)throw new InvalidArgumentException('Boss không nằm trong danh mục');
    $phase=admin_integer($p['phase']??-1,0,2147483647);
    if(!in_array($phase,array_column($entry['phases'],'phase'),true))throw new InvalidArgumentException('Giai đoạn không tồn tại');
    $out=['boss_key'=>$key,'phase'=>$phase,'expected_revision'=>admin_integer($p['expected_revision']??-1,0,9007199254740990)]+boss_admin_defaults();
    foreach(['hp','damage','cap_damage'] as $field){$value=$p[$field]??'';$out[$field]=$value===''||$value===null?null:admin_integer($value,$field==='hp'?1:0,2147483647);}
    $defense=$p['defense']??'';$out['defense']=$defense===''||$defense===null?null:admin_integer($defense,0,2147483647);
    $critical=$p['critical']??'';$out['critical']=$critical===''||$critical===null?null:admin_integer($critical,0,100);
    $percent=$p['cap_percent']??'';
    if($percent!==''&&$percent!==null){
        if(!is_scalar($percent)||!preg_match('/^\d{1,3}(?:\.\d{1,3})?$/D',(string)$percent)||(float)$percent>100)throw new InvalidArgumentException('Giới hạn % phải từ 0 đến 100, tối đa 3 chữ số thập phân');
        $out['cap_percent']=number_format((float)$percent,3,'.','');
    }
    foreach(['apply_mode'=>['next','alive'],'drop_mode'=>['default','replace']] as $field=>$choices){$out[$field]=$p[$field]??$out[$field];if(!in_array($out[$field],$choices,true))throw new InvalidArgumentException('Chế độ không hợp lệ');}
    $json=$p['drops_json']??'[]';if(!is_string($json)||strlen($json)>262144)throw new InvalidArgumentException('Dữ liệu rơi đồ quá lớn');
    try{$raw=json_decode($json,false,32,JSON_THROW_ON_ERROR);$rules=json_decode($json,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('JSON rơi đồ không hợp lệ');}
    if(!is_array($rules)||!preg_match('/^\s*\[/',$json)||count($rules)>200)throw new InvalidArgumentException('Rơi đồ phải là danh sách tối đa 200 quy tắc');
    $groups=[];
    foreach($rules as $index=>$rule){
        if(!($raw[$index] instanceof stdClass)||(isset($raw[$index]->options)&&!is_array($raw[$index]->options)))throw new InvalidArgumentException('Mỗi quy tắc là object, options là danh sách');
        if(!is_array($rule)||array_diff(array_keys($rule),['group','item_id','quantity_min','quantity_max','chance_bp','weight','options']))throw new InvalidArgumentException('Trường rơi đồ không hợp lệ');
        $group=$rule['group']??'';if(!is_string($group)||!preg_match('/^[A-Za-z0-9_-]{0,64}$/D',$group))throw new InvalidArgumentException('Tên nhóm dùng chữ/số/_/- tối đa 64 ký tự');
        $r=['group'=>$group,'item_id'=>admin_integer($rule['item_id']??-1,0,32767),'quantity_min'=>admin_integer($rule['quantity_min']??0,1),'quantity_max'=>admin_integer($rule['quantity_max']??0,1),'chance_bp'=>admin_integer($rule['chance_bp']??-1,0,10000),'weight'=>admin_integer($rule['weight']??1,1),'options'=>[]];
        if($r['quantity_max']<$r['quantity_min'])throw new InvalidArgumentException('Số lượng max nhỏ hơn min');
        if($group!==''){if(isset($groups[$group])&&$groups[$group]!==$r['chance_bp'])throw new InvalidArgumentException('Các món cùng nhóm phải dùng cùng tỷ lệ nhóm');$groups[$group]=$r['chance_bp'];}
        $options=$rule['options']??[];if(!is_array($options)||!array_is_list($options)||count($options)>100)throw new InvalidArgumentException('Options phải là danh sách tối đa 100');$seen=[];
        foreach($options as $op){if(!is_array($op)||array_diff(array_keys($op),['id','param']))throw new InvalidArgumentException('Option không hợp lệ');$id=admin_integer($op['id']??-1,0,255);if(isset($seen[$id]))throw new InvalidArgumentException('Trùng option');$seen[$id]=true;$r['options'][]=['id'=>$id,'param'=>admin_integer($op['param']??-1,0)];}
        $out['drops'][]=$r;
    }
    return $out;
}
function boss_admin_select($sql,$types,$values): array {
    global $conn;$stmt=$conn->prepare($sql);if(!$stmt)throw new RuntimeException('Không thể đọc cấu hình');
    if($types!=='')$stmt->bind_param($types,...$values);
    if(!$stmt->execute())throw new RuntimeException('Không thể đọc cấu hình');$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
function boss_admin_row_config(?array $row): array {
    if(!$row)return boss_admin_defaults();$out=boss_admin_defaults();
    foreach(['hp','damage','cap_damage','defense','critical'] as $k)$out[$k]=($row[$k]??null)===null?null:(int)$row[$k];
    foreach(['cap_percent','apply_mode','drop_mode'] as $k)$out[$k]=$row[$k];
    $out['drops']=json_decode($row['drops'],true,32,JSON_THROW_ON_ERROR);return $out;
}
function boss_admin_request_hash(array $config,string $action,int $auditId): string {
    return hash('sha256',json_encode([$action,$auditId,$config],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function boss_admin_save(array $p,array $catalog,string $action,?string $requestId): array {
    global $logged_user_id;
    $actor=admin_actor_id();
    if($actor<=0)throw new InvalidArgumentException('Thiếu danh tính quản trị viên. Đăng nhập lại trước khi lưu.');
    if(!in_array($action,['save','reset','rollback'],true))throw new InvalidArgumentException('Thao tác không hợp lệ');
    if($action!=='save')$p=array_merge($p,['hp'=>'','damage'=>'','cap_damage'=>'','cap_percent'=>'','defense'=>'','critical'=>'','drops_json'=>'[]']);
    $v=boss_admin_validate($p,$catalog);$revision=0;
    if($requestId===null||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$requestId))throw new InvalidArgumentException('Request ID không hợp lệ');
    $auditId=$action==='rollback'?admin_integer($p['audit_id']??0,1):0;
    $hash=boss_admin_request_hash($v,$action,$auditId);
    $prior=boss_admin_select('SELECT command_type,payload,actor_account_id FROM server_command_queue WHERE request_id=?','s',[$requestId]);
    if($prior){
        $old=json_decode($prior[0]['payload'],true,32,JSON_THROW_ON_ERROR);
        if($prior[0]['command_type']!=='RELOAD_BOSS'||(int)$prior[0]['actor_account_id']!==$actor||($old['catalog_patch']['request_hash']??'')!==$hash)throw new InvalidArgumentException('Request ID đã dùng cho thao tác khác');
        $revision=admin_integer($old['catalog_patch']['revision']??-1,1,9007199254740990);
    }
    // Array references preserve the revision assigned under the global state lock.
    $payload=['catalog_patch'=>['boss_admin'=>true,'revision'=>&$revision,'request_hash'=>$hash]];
    $id=admin_catalog_transaction(function()use($v,$action,$p,$catalog,&$revision,$actor){
        $state=boss_admin_select('SELECT revision FROM boss_admin_state WHERE id=1 FOR UPDATE','',[]);
        if(!$state)throw new RuntimeException('Chưa cài migration Boss Admin');
        $rows=boss_admin_select('SELECT * FROM boss_admin_config WHERE boss_key=? AND phase=? FOR UPDATE','si',[$v['boss_key'],$v['phase']]);$row=$rows[0]??null;
        boss_admin_revision($row['revision']??0,$v['expected_revision']);
        $revision=boss_admin_revision($state[0]['revision'],$state[0]['revision']);
        $before=boss_admin_row_config($row);$after=$action==='save'?array_intersect_key($v,boss_admin_defaults()):boss_admin_defaults();
        if(!$row&&preg_match('/^id:(-?\d+)$/D',$v['boss_key'],$match)){
            $legacy=boss_admin_select('SELECT hp,damage FROM boss_live_override WHERE boss_id=? AND phase=? FOR UPDATE','ii',[(int)$match[1],$v['phase']]);
            if($legacy)foreach(['hp','damage'] as $k)$before[$k]=$legacy[0][$k]===null?null:(int)$legacy[0][$k];
        }
        if($action==='rollback'){
            $history=boss_admin_select('SELECT before_json FROM boss_admin_audit WHERE id=? AND boss_key=? AND phase=?','isi',[admin_integer($p['audit_id']??0,1),$v['boss_key'],$v['phase']]);
            if(!$history)throw new InvalidArgumentException('Không tìm thấy bản lịch sử');
            $old=json_decode($history[0]['before_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($old))throw new InvalidArgumentException('Lịch sử không hợp lệ');
            $old['drops_json']=json_encode($old['drops']??[],JSON_THROW_ON_ERROR);
            $validated=boss_admin_validate(array_merge($old,['boss_key'=>$v['boss_key'],'phase'=>$v['phase'],'expected_revision'=>$v['expected_revision']]),$catalog);
            $after=array_intersect_key($validated,boss_admin_defaults());
        }
        // Verify IDs against the live template catalog, without trusting client search.
        foreach($after['drops'] as $drop){
            if(!boss_admin_select('SELECT id FROM item_template WHERE id=?','i',[$drop['item_id']]))throw new InvalidArgumentException('Không tồn tại vật phẩm #'.$drop['item_id']);
            foreach($drop['options'] as $op)if(!boss_admin_select('SELECT id FROM item_option_template WHERE id=?','i',[$op['id']]))throw new InvalidArgumentException('Không tồn tại option #'.$op['id']);
        }
        $drops=json_encode($after['drops'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        admin_execute_statement('INSERT INTO boss_admin_config(boss_key,phase,hp,damage,cap_damage,cap_percent,defense,critical,apply_mode,drop_mode,drops,revision) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE hp=VALUES(hp),damage=VALUES(damage),cap_damage=VALUES(cap_damage),cap_percent=VALUES(cap_percent),defense=VALUES(defense),critical=VALUES(critical),apply_mode=VALUES(apply_mode),drop_mode=VALUES(drop_mode),drops=VALUES(drops),revision=VALUES(revision)','siiiisiisssi',[$v['boss_key'],$v['phase'],$after['hp'],$after['damage'],$after['cap_damage'],$after['cap_percent'],$after['defense'],$after['critical'],$after['apply_mode'],$after['drop_mode'],$drops,$revision]);
        admin_execute_statement('UPDATE boss_admin_state SET revision=? WHERE id=1','i',[$revision]);
        admin_execute_statement('INSERT INTO boss_admin_audit(boss_key,phase,revision,actor_id,before_json,after_json) VALUES(?,?,?,?,?,?)','siiiss',[$v['boss_key'],$v['phase'],$revision,$actor,json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);
    },'RELOAD_BOSS',$payload,$requestId);
    return ['command_id'=>$id,'revision'=>$revision];
}
