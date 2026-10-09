<?php
$fixture=__DIR__.'/icon-fixture';
@mkdir($fixture.'/assets/icons',0777,true);
define('ADMIN_ROOT',$fixture);
require __DIR__.'/../admin/includes/icon_helper.php';
$empty=$fixture.'/assets/icons/32766.png';file_put_contents($empty,'');
try{
 if(get_item_icon_url(32766)!=='/webgoc2026/favicon-32x32.png')throw new Exception('Empty icon must use existing fallback');
 if(!is_file(__DIR__.'/../favicon-32x32.png'))throw new Exception('Fallback must exist');
 echo "icon validation/fallback OK\n";
}finally{unlink($empty);rmdir($fixture.'/assets/icons');rmdir($fixture.'/assets');rmdir($fixture);}
