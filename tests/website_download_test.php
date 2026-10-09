<?php
function check_download($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
$helper = __DIR__ . '/../includes/website_downloads.php';
check_download(is_file($helper), 'Missing configurable download links helper');
require $helper;
foreach (['', 'https://example.com/mod.apk?a=1&b=2', 'http://example.com/game.zip', 'https://testflight.apple.com/join/example'] as $url) {
    check_download(website_download_url($url) === $url, 'Valid link rejected');
}
check_download(website_download_url(' https://example.com/mod.apk ') === 'https://example.com/mod.apk', 'Link must be trimmed');
foreach (['javascript:alert(1)', 'data:text/html,test', '//example.com/file', 'not a URL', 'https://', ['bad']] as $url) {
    try { website_download_url($url); throw new RuntimeException('Unsafe link accepted'); }
    catch (InvalidArgumentException $expected) {}
}
$download_settings = ['Android'=>'https://example.com/mod.apk?a=1&b=2','Windows'=>'','IPhone'=>'javascript:alert(1)'];
$base_url = '/webgoc2026';
ob_start(); require __DIR__.'/../includes/download_buttons.php'; $html = ob_get_clean();
check_download(strpos($html, 'href="https://example.com/mod.apk?a=1&amp;b=2"') !== false, 'Configured Android URL missing or not escaped');
check_download(strpos($html, 'Tải game Android') !== false, 'Android download label missing');
check_download(strpos($html, 'Tải game PC') === false, 'Empty PC URL must hide button');
check_download(strpos($html, 'javascript:') === false, 'Invalid stored URL must never render');
check_download(strpos($html, '/down/JAR.jar') !== false, 'Existing Java download lost');
foreach (['index.php','trang-chu.php','forum.php'] as $page) {
    $source = file_get_contents(__DIR__.'/../'.$page);
    check_download(strpos($source, 'includes/download_buttons.php') !== false, "$page must use configured buttons");
    check_download(strpos($source, '/down/NRO.apk') === false && strpos($source, '/down/PC.rar') === false, "$page retains fixed platform downloads");
}
echo "website download validation, rendering and page integration OK\n";
