<?php
function validate_boss_override($p) {
    $ids=json_decode(file_get_contents(__DIR__.'/boss_ids.json'),true,512,JSON_THROW_ON_ERROR);
    $id=admin_integer($p['boss_id']??0,-2147483647,2147483647);
    if(!array_key_exists((string)$id,$ids))throw new InvalidArgumentException('Boss ID không hợp lệ');
    $phase=admin_integer($p['phase']??0,0,31);
    $hp=($p['hp']??'')===''?null:admin_integer($p['hp'],1,2147483647);
    $damage=($p['damage']??'')===''?null:admin_integer($p['damage'],0,2147483647);
    if($hp===null&&$damage===null)throw new InvalidArgumentException('Điền HP hoặc sức đánh; dùng Xóa để trở về mặc định');
    return ['boss_id'=>$id,'phase'=>$phase,'hp'=>$hp,'damage'=>$damage];
}
