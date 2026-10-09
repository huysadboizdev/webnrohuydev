<?php
require __DIR__.'/../admin/includes/icon_helper.php';
$catalog=json_decode(file_get_contents(__DIR__.'/../admin/includes/boss_admin_catalog.json'),true,512,JSON_THROW_ON_ERROR);
$avatars=json_decode(file_get_contents(__DIR__.'/../admin/includes/boss_avatar_map.json'),true,512,JSON_THROW_ON_ERROR);
$expected=[
    'class:nro.models.boss.Boss_mini.AnTrom'=>2254,
    'class:nro.models.boss.Boss_mini.Odo'=>3995,
    'id:-1822'=>2893,
    'id:-82282'=>2924,
    'id:-386998'=>15070,
    'id:-79'=>7146,
];
foreach($expected as $key=>$icon){
    if(($avatars[$key]['icon_id']??null)!==$icon)throw new RuntimeException('Portrait must match visible game outfit: '.$key);
}
foreach($catalog as $boss){
    $avatar=$avatars[$boss['key']]??null;
    if(!is_int($avatar['icon_id']??null))throw new RuntimeException('Missing resolved resource icon: '.$boss['key']);
    $url=get_boss_avatar_url($boss['key']);
    if($url==='/webgoc2026/favicon-32x32.png')throw new RuntimeException('Boss must use real resource: '.$boss['key']);
    $file=__DIR__.'/..'.substr($url,strlen('/webgoc2026'));
    if(!is_valid_admin_icon($file))throw new RuntimeException('Invalid boss resource: '.$url);
}
$keys=['id:-925','id:-203','class:nro.models.boss.Boss_mini.AnTrom','class:nro.models.boss.Boss_mini.MatTroi'];
if(count(array_unique(array_map('get_boss_avatar_url',$keys)))!==4)throw new RuntimeException('Screenshot bosses must have distinct portraits');
if($avatars['class:nro.models.boss.HuntingTrio.HuntingTrioBoss#Pi Láp']['head_id']!==612||$avatars['class:nro.models.boss.HuntingTrio.HuntingTrioBoss#Su']['head_id']!==618)throw new RuntimeException('Hunting portraits must follow game outfits');
echo 'Boss resources PASS '.count($catalog)." catalog portraits,distinct screenshots,source mappings\n";
