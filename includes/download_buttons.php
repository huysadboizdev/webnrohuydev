<?php
require_once __DIR__ . '/website_downloads.php';
if (!isset($download_settings)) {
    try { $download_settings = website_read_settings($conn); }
    catch (Throwable $e) { $download_settings = []; error_log('Cannot read website download settings'); }
}
$download_platforms = [
    'Android' => ['image' => 'play.png', 'label' => 'Tải game Android'],
    'Windows' => ['image' => 'pc.png', 'label' => 'Tải game PC'],
    'IPhone' => ['image' => 'ip.png', 'label' => 'Tải game iPhone'],
];
?>
<div id="columns" style="text-align:center; display:flex; justify-content:center; gap:20px; flex-wrap:wrap;">
    <figure>
        <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>/down/JAR.jar?v=20260213-1348" download title="Tải game Java">
            <img height="35" src="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>/images/jar.png" alt="Tải game Java">
        </a>
    </figure>
    <?php foreach ($download_platforms as $platform => $button):
        try { $download_url = website_download_url($download_settings[$platform] ?? ''); }
        catch (InvalidArgumentException $e) { continue; }
        if ($download_url === '') continue;
    ?>
    <figure>
        <a href="<?= htmlspecialchars($download_url, ENT_QUOTES, 'UTF-8') ?>" title="<?= $button['label'] ?>">
            <img height="35" src="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>/images/<?= $button['image'] ?>" alt="<?= $button['label'] ?>">
        </a>
        <figcaption><?= $button['label'] ?></figcaption>
    </figure>
    <?php endforeach; ?>
</div>
