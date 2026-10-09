<?php
// admin/website_settings.php
$page_title = 'Cấu Hình Website & Thông Báo In-Game';
$active_tab = 'settings';
require_once __DIR__ . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/website_downloads.php';

$message = '';
$message_type = 'success';

// Xử lý Cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $message = 'Mã bảo mật CSRF không hợp lệ.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_notify') {
            $notify_text = trim($_POST['notify_text'] ?? '');
            $notify_name = trim($_POST['notify_name'] ?? 'Thông báo máy chủ');

            try {
                $patch=['notify_name'=>$notify_name,'notify_text'=>$notify_text];
                $queued_command_id=admin_catalog_transaction(function()use($conn,$notify_name,$notify_text){
                    $res=$conn->query('SELECT id FROM notify LIMIT 1');
                    if(!$res)throw new RuntimeException('Cannot read notify');
                    if($res->num_rows>0)admin_execute_statement('UPDATE notify SET name = ?, text = ? LIMIT 1','ss',[$notify_name,$notify_text]);
                    else admin_execute_statement('INSERT INTO notify (name, text) VALUES (?, ?)','ss',[$notify_name,$notify_text]);
                },'RELOAD_NOTIFY',['text'=>$notify_text,'catalog_patch'=>$patch],$_POST['request_id']??null);
                $message='Đã lưu và xếp hàng lệnh #'.$queued_command_id.'; đang chờ xử lý.';
            } catch(Throwable $e){$message='Không thể lưu thông báo';$message_type='danger';}
        } elseif ($action === 'update_settings') {
            $title = trim($_POST['title'] ?? '');
            $server_name = trim($_POST['server_name'] ?? '');
            $fanpage = trim($_POST['fanpage'] ?? '');
            $zalo = trim($_POST['zalo'] ?? '');
            try {
                website_save_settings($conn, [
                    'Title' => $title, 'ServerName' => $server_name,
                    'Fanpage' => $fanpage, 'Zalo' => $zalo,
                    'Android' => $_POST['android'] ?? '',
                    'Windows' => $_POST['windows'] ?? '',
                    'IPhone' => $_POST['iphone'] ?? '',
                ]);
                $message = 'Đã lưu! Các nút tải game trên Website sẽ dùng link mới khi tải lại trang.';
            } catch (InvalidArgumentException $e) {
                $message = $e->getMessage();
                $message_type = 'danger';
            } catch (Throwable $e) {
                $message = 'Không thể lưu cài đặt Website. Vui lòng thử lại.';
                $message_type = 'danger';
                error_log('Cannot save website settings');
            }
        }
    }
}

// Lấy thông tin notify
$notify_row = ['name' => 'Thông báo', 'text' => ''];
$res_notify = $conn->query("SELECT * FROM notify LIMIT 1");
if ($res_notify && $res_notify->num_rows > 0) {
    $notify_row = $res_notify->fetch_assoc();
}

// Lấy thông tin settings
$settings_row = [];
$res_set = $conn->query("SELECT * FROM settings LIMIT 1");
if ($res_set && $res_set->num_rows > 0) {
    $settings_row = $res_set->fetch_assoc();
}
?>

<?php if ($message): ?>
    <div style="background:var(--<?= $message_type ?>-light); border:1px solid var(--<?= $message_type ?>); color:var(--text-primary); padding:12px 18px; border-radius:var(--radius-md); margin-bottom:20px; font-weight:500;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Box Thông Báo In-game -->
<div class="card" style="border:1px solid rgba(59, 130, 246, 0.3);">
    <div class="card-header" style="background:rgba(59, 130, 246, 0.05);">
        <div class="card-title" style="color:#3b82f6;">
            <span>📢</span>
            <span>Thông Báo Chạy Chữ In-Game (`notify`)</span>
        </div>
        <button type="button" class="btn btn-sm btn-primary" onclick="triggerLiveSync('RELOAD_NOTIFY', {})">
            ⚡ Live Reload Thông Báo
        </button>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="action" value="update_notify">

            <div class="form-group">
                <label class="form-label">Tiêu Đề Thông Báo:</label>
                <input type="text" name="notify_name" class="form-control" value="<?= htmlspecialchars($notify_row['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Nội Dung Chạy Chữ (Hiển thị góc trên màn hình người chơi):</label>
                <textarea name="notify_text" class="form-control" rows="3" required><?= htmlspecialchars($notify_row['text'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Lưu & Bắn Live Vào Game</button>
        </form>
    </div>
</div>

<!-- Box Cài Đặt Website -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>⚙️</span>
            <span>Cấu Hình Thông Tin Website & Link Tải Game (`settings`)</span>
        </div>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="action" value="update_settings">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label class="form-label">Tên Tiêu Đề Web (Title):</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($settings_row['Title'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Tên Máy Chủ (Server Name):</label>
                    <input type="text" name="server_name" class="form-control" value="<?= htmlspecialchars($settings_row['ServerName'] ?? '') ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label class="form-label">Fanpage Link:</label>
                    <input type="text" name="fanpage" class="form-control" value="<?= htmlspecialchars($settings_row['Fanpage'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Zalo Link / SĐT Hỗ Trợ:</label>
                    <input type="text" name="zalo" class="form-control" value="<?= htmlspecialchars($settings_row['Zalo'] ?? '') ?>">
                </div>
            </div>

            <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin:18px 0 10px;">📲 Link Tải Game Các Nền Tảng</h4>
            <p class="form-label">Dán link tải mod (http:// hoặc https://), rồi bấm Lưu Cấu Hình Website. Để trống để ẩn nút tải của nền tảng đó. Có thể dùng link tải trực tiếp, Google Drive, MediaFire hoặc TestFlight.</p>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:18px;">
                <div>
                    <label class="form-label">Link APK (Android):</label>
                    <input type="url" name="android" class="form-control" placeholder="https://.../game.apk" value="<?= htmlspecialchars($settings_row['Android'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label class="form-label">Link Bản PC (Windows):</label>
                    <input type="url" name="windows" class="form-control" placeholder="https://.../game.zip" value="<?= htmlspecialchars($settings_row['Windows'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label class="form-label">Link Bản iOS (iPhone):</label>
                    <input type="url" name="iphone" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($settings_row['IPhone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-success">Lưu Cấu Hình Website</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
