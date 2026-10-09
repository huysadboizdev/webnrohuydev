<?php
require __DIR__.'/../connect.php';
require __DIR__.'/../includes/website_downloads.php';
function expect_download_db($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
// Shadow the live table only in this connection. No real settings are modified.
$before = hash('sha256', json_encode(website_read_settings($conn)));
$schema = $conn->query('SHOW CREATE TABLE settings')->fetch_assoc()['Create Table'];
$conn->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema));
try {
    $values = [
        'Title'=>'Download fixture', 'ServerName'=>'Fixture', 'Fanpage'=>'', 'Zalo'=>'',
        'Android'=>'https://example.com/download/mod.apk?token='.str_repeat('a', 150),
        'Windows'=>'https://example.com/game.zip',
        'IPhone'=>'https://testflight.apple.com/join/example',
    ];
    website_save_settings($conn, $values);
    expect_download_db(website_read_settings($conn)['Android'] === $values['Android'], 'Long URL truncated on save');
    website_save_settings($conn, $values);
    $count = $conn->query('SELECT COUNT(*) AS n FROM settings')->fetch_assoc()['n'];
    expect_download_db((int)$count === 1, 'Saving unchanged settings must not insert duplicates');
    $download_settings = website_read_settings($conn); $base_url = '/webgoc2026';
    ob_start(); require __DIR__.'/../includes/download_buttons.php'; $html = ob_get_clean();
    foreach (['Android','Windows','IPhone'] as $platform) {
        expect_download_db(strpos($html, htmlspecialchars($values[$platform], ENT_QUOTES, 'UTF-8')) !== false, 'Saved URL missing from buttons');
    }
    $bad = $values; $bad['Windows'] = 'javascript:alert(1)'; $bad['Title'] = 'Must not save';
    try { website_save_settings($conn, $bad); throw new RuntimeException('Invalid link accepted'); }
    catch (InvalidArgumentException $expected) {}
    expect_download_db(website_read_settings($conn)['Title'] === $values['Title'], 'Invalid link partially saved');
    $values['Windows'] = '';
    website_save_settings($conn, $values);
    expect_download_db(website_read_settings($conn)['Windows'] === '', 'Cannot clear link');
} finally {
    $conn->query('DROP TEMPORARY TABLE settings');
}
expect_download_db(hash('sha256', json_encode(website_read_settings($conn))) === $before, 'Real settings changed');
echo "website downloads DB insert, update, long URLs, invalid-link atomicity and live-settings preservation OK\n";
