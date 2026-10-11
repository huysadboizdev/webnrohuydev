<?php
// Obsolete admin pages under static assets must never execute upload or SQL actions.
foreach (['assets/fontawesome-free/css/bootstrap-min-css.php','images/avatar/zoom.php'] as $relative) {
    $source=file_get_contents(__DIR__.'/../'.$relative);
    if(strpos($source,'http_response_code(403)')===false || strpos($source,'move_uploaded_file')!==false || strpos($source,'connect.php')!==false)
        throw new RuntimeException('Legacy asset admin endpoint must be disabled: '.$relative);
}
echo "Legacy asset admin endpoints disabled PASS\n";
