<?php
require __DIR__.'/admin_queue_contract_test.php';
require __DIR__.'/../admin/includes/player_data_helper.php';
$pet=[ [0,1,'$Pet',2,3,4], [4,'9007199254740993','9223372036854775807',1000,900,2000,2000,100,17,9], [], [[0,3,5,6],[-1,0,0,0],[8,4,5,6]] ];
$decoded=decode_player_pet(json_encode($pet));
check($decoded['power']==='9007199254740993','long power stays exact');
check($decoded['skills'][0]['skill_id']===0&&$decoded['skills'][0]['point']===3,'skill zero/level preserved');
check($decoded['defg']===17&&$decoded['critg']===9,'untouched fields decoded');
foreach ([0=>'Đệ tử',1=>'Mabư',2=>'Uub',3=>'Kid Beer',4=>'Kid Jiren'] as $type=>$name) {
    $pet[0]=[$type,1,'',2,3,4];
    check(decode_player_pet(json_encode($pet))['name']===$name,'empty pet name follows type');
    $pet[0][2]='$Old name';
    check(decode_player_pet(json_encode($pet))['name']===$name,'old pet name follows type');
}
echo "pet mixed JSON decode and default names OK\n";
