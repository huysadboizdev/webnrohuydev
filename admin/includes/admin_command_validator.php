<?php
function admin_integer($value, $min=0, $max=2147483647) {
    if (!is_scalar($value) || !preg_match('/^-?\d+$/D',(string)$value) || filter_var($value,FILTER_VALIDATE_INT) === false || $value < $min || $value > $max) throw new InvalidArgumentException('Invalid integer');
    return (int)$value;
}
function admin_long_string($value, $signed=false) {
    $v=(string)$value;
    if (!preg_match($signed ? '/^-?\d{1,19}$/D' : '/^\d{1,19}$/D',$v)) throw new InvalidArgumentException('Invalid long');
    $n=ltrim(ltrim($v,'-'),'0');$n=$n===''?'0':$n;
    if(strlen($n)>19 || (strlen($n)===19 && strcmp($n,'9223372036854775807')>0)) throw new InvalidArgumentException('Long overflow');
    return $v;
}
function validate_admin_command($type, $p) {
    if (in_array($type, ['MAINTENANCE_START','MAINTENANCE_OPEN'], true)) {
        if (!is_array($p) || count($p)!==0) throw new InvalidArgumentException('Maintenance commands require an empty payload');
        return [];
    }
    if ($type === 'EVENT_SET') {
        $keys=['hung_vuong','summer','halloween','christmas','lunar_new_year','trung_thu','womens_day','teachers_day','valentine','top_up'];
        if (!is_array($p) || array_diff(array_keys($p), ['event_key','enabled','revision']) || count($p)!==3
            || !in_array($p['event_key']??null,$keys,true) || !is_bool($p['enabled']??null)
            || !is_int($p['revision']??null) || $p['revision']<0 || $p['revision']>9007199254740991) {
            throw new InvalidArgumentException('Invalid event key, boolean, revision or field');
        }
        return ['event_key'=>$p['event_key'],'enabled'=>$p['enabled'],'revision'=>$p['revision']];
    }
    $types=['GIFT_ITEM','TOPUP_PLAYER','UPDATE_STATS','DELETE_ITEM','UPDATE_ITEM_QTY','CHANGE_TASK','UPGRADE_MAGIC_TREE','UPDATE_PET','WARP_HOME','HEAL_FULL','KICK_PLAYER','UPDATE_ACCOUNT','ACCOUNT_ACTION','CONSIGN_CANCEL','RELOAD_GIFTCODE','RELOAD_MOB','RELOAD_BOSS','RELOAD_SHOP','RELOAD_NOTIFY','RELOAD_ALL','SET_EXP_RATE','SEND_NOTICE'];
    if (!in_array($type,$types,true)||!is_array($p)) throw new InvalidArgumentException('Unsupported command');
    if($type==='SET_EXP_RATE')return ['rate'=>admin_integer($p['rate']??0,1,100)];
    if($type==='SEND_NOTICE'){
        if(!is_string($p['message']??null)||trim($p['message'])===''||preg_match_all('/./us',$p['message'])>100)throw new InvalidArgumentException('Thông báo thế giới tối đa 100 ký tự');
        return ['message'=>$p['message']];
    }
    if(strpos($type,'RELOAD_')===0) {
        $allowed=['mob_id','bulk','item_shop_id','code','text','catalog_patch'];
        if(array_diff(array_keys($p),$allowed))throw new InvalidArgumentException('Invalid reload field');
        if(isset($p['mob_id']))$p['mob_id']=admin_integer($p['mob_id'],0,32767);
        if(isset($p['item_shop_id']))$p['item_shop_id']=admin_integer($p['item_shop_id'],1);
        return $p;
    }
    $out=[];$targetKey=in_array($type,['UPDATE_ACCOUNT','ACCOUNT_ACTION'],true)?'account_id':($type==='CONSIGN_CANCEL'?'listing_id':'player_id');
    $out[$targetKey]=admin_integer($p[$targetKey]??0,1);
    if(in_array($type,['GIFT_ITEM','DELETE_ITEM','UPDATE_ITEM_QTY'],true)){
        $out['target']=$p['target']??'bag';
        if(!in_array($out['target'],$type==='GIFT_ITEM'?['bag','box']:['bag','box','body'],true))throw new InvalidArgumentException('Invalid inventory target');
    }
    if($type==='GIFT_ITEM'){
        $out['item_id']=admin_integer($p['item_id']??-1,0,32767);$out['quantity']=admin_integer($p['quantity']??0,1);
        $out['options']=[];
        if(!is_array($p['options']??[]))throw new InvalidArgumentException('Invalid options');
        foreach($p['options']??[] as $o){if(!is_array($o))throw new InvalidArgumentException('Invalid option');$out['options'][]=[admin_integer($o['id']??$o[0]??-1,0,32767),admin_integer($o['param']??$o[1]??0)];}
    }
    if(in_array($type,['DELETE_ITEM','UPDATE_ITEM_QTY'],true)){
        $out['slot_index']=admin_integer($p['slot_index']??-1,0,199);
        if(!isset($p['expected_item'])||!is_array($p['expected_item'])||count($p['expected_item'])<4)throw new InvalidArgumentException('Original item identity required; refresh inventory');
        $out['expected_item']=$p['expected_item'];
        if($type==='UPDATE_ITEM_QTY')$out['quantity']=admin_integer($p['quantity']??0,1,1000000);
    }
    if($type==='CHANGE_TASK')$out['task_id']=admin_integer($p['task_id']??-1,0,32767);
    $longFields=$type==='TOPUP_PLAYER'?['add_gold','add_gem','add_ruby','add_thoi_vang']:($type==='UPDATE_ACCOUNT'?['add_vnd','add_thoi_vang','add_tongnap']:($type==='UPDATE_STATS'?['power','tiem_nang','gold']:($type==='UPDATE_PET'?['pet_power','pet_tiem_nang']:[])));
    foreach($longFields as $f)if(array_key_exists($f,$p))$out[$f]=admin_long_string($p[$f],in_array($type,['TOPUP_PLAYER','UPDATE_ACCOUNT'],true));
    $fields=$type==='UPDATE_STATS'?['hpg','mpg','dameg','defg','critg','gem','ruby','limit_power']:($type==='UPDATE_PET'?['pet_type','pet_hpg','pet_mpg','pet_dameg','pet_skill_1','pet_skill_2','pet_skill_3','pet_skill_4']:[]);
    foreach($fields as $f)if(array_key_exists($f,$p))$out[$f]=admin_integer($p[$f],strpos($f,'pet_skill_')===0?-1:0,$f==='limit_power'?127:($f==='pet_type'?4:2147483647));
    if($type==='UPDATE_PET'&&array_key_exists('pet_name',$p))throw new InvalidArgumentException('Không được chỉnh tên đệ tử trong admin');
    if($type==='ACCOUNT_ACTION'){
        $out['action']=$p['action']??'';
        if(!in_array($out['action'],['ban','active','admin','password'],true))throw new InvalidArgumentException('Invalid account action');
        if($out['action']==='password'){if(!is_string($p['value']??null)||strlen($p['value'])<4||strlen($p['value'])>100)throw new InvalidArgumentException('Invalid password');$out['value']=$p['value'];}
        else $out['value']=admin_integer($p['value']??-1,0,1);
    }
    if (array_key_exists('kick_player',$p)) {
        $kick=filter_var($p['kick_player'],FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
        if ($kick===null) throw new InvalidArgumentException('Invalid kick flag');
        if ($kick) $out['kick_player']=true;
    }
    return $out;
}
