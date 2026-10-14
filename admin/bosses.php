<?php
$page_title='Boss · Cấu hình Live';$active_tab='bosses';
require_once __DIR__.'/includes/header.php';
require_once __DIR__.'/includes/boss_admin_helper.php';
require_once __DIR__.'/includes/icon_helper.php';

$message='';$configs=[];$audit=[];$commands=[];$runtime=[];$restore=null;$available=false;
$live=['fresh'=>false,'age_seconds'=>null,'last_heartbeat'=>null];
$catalog=json_decode(file_get_contents(__DIR__.'/includes/boss_admin_catalog.json'),true,512,JSON_THROW_ON_ERROR);
$avatar_map=file_exists(__DIR__.'/includes/boss_avatar_map.json')?json_decode(file_get_contents(__DIR__.'/includes/boss_avatar_map.json'),true):[];
// The generated catalog stores paths from the local export. Rebase for this deployment.
array_walk_recursive($avatar_map, static function (&$value, $key) {
    if ($key === 'url' && is_string($value) && strpos($value, '/webgoc2026/') === 0) {
        $value = admin_url(substr($value, strlen('/webgoc2026')));
    }
});

try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!verify_csrf_token($_POST['csrf_token']??''))throw new InvalidArgumentException('CSRF không hợp lệ');
        $result=boss_admin_save($_POST,$catalog,$_POST['action']??'',$_POST['request_id']??null);
        $queued_command_id=$result['command_id'];
        $message='Đã lưu cấu hình và tạo lệnh #'.$queued_command_id.'. Đang chờ máy chủ xác nhận; thay đổi chưa được xác nhận áp dụng vào game.';
    }
}catch(Throwable $e){
    $message='Không thể lưu: '.$e->getMessage();
    $restore=$_POST;
}

try{
    foreach(boss_admin_select('SELECT * FROM boss_admin_config','',[]) as $row){
        $configs[$row['boss_key']][(string)$row['phase']]=boss_admin_row_config($row)+['revision'=>(int)$row['revision']];
    }
    foreach(boss_admin_select('SELECT boss_id,phase,hp,damage FROM boss_live_override','',[]) as $old){
        $key='id:'.$old['boss_id'];$phase=(string)$old['phase'];
        if(!isset($configs[$key][$phase])){
            $c=boss_admin_defaults();
            foreach(['hp','damage'] as $k)$c[$k]=$old[$k]===null?null:(int)$old[$k];
            $configs[$key][$phase]=$c+['revision'=>0,'legacy'=>true];
        }
    }
    $audit=boss_admin_select('SELECT id,boss_key,phase,revision,actor_id,created_at FROM boss_admin_audit ORDER BY id DESC LIMIT 300','',[]);
    foreach(boss_admin_select("SELECT id,payload,status,result_message FROM server_command_queue WHERE command_type='RELOAD_BOSS' ORDER BY id DESC LIMIT 300",'',[]) as $command){
        $payload=json_decode($command['payload'],true);
        if(!empty($payload['catalog_patch']['boss_admin']))$commands[(string)$payload['catalog_patch']['revision']]=$command;
    }
    $available=true;
}catch(Throwable $e){
    $message.=' Cấu hình chưa sẵn sàng: '.$e->getMessage();
}

try{
    $status=boss_admin_read_status();
    $runtime=$status['runtime'];$commands=$status['commands'];$live=$status['live'];$configs=$status['configs'];
}catch(Throwable $ignored){}

$stat_total=count($catalog);$stat_quest=0;$stat_limited=0;
foreach($catalog as $e){if(!empty($e['quest']))$stat_quest++;if(!empty($e['limited']))$stat_limited++;}
$stat_general=$stat_total-$stat_quest-$stat_limited;$stat_custom=0;
foreach($configs as $bykey){
    foreach($bykey as $c){
        if(empty($c['legacy']) && ($c['hp']!==null || $c['damage']!==null || ($c['defense']??null)!==null || ($c['critical']??null)!==null || $c['cap_damage']!==null || $c['cap_percent']!==null || ($c['drop_mode']??'default')!=='default' || !empty($c['drops']))){
            $stat_custom++;
        }
    }
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/assets/css/boss-admin.css?v=<?= time() ?>">

<div class="boss-hero">
    <div class="boss-hero-title">
        <span>👹</span>
        <span>Quản Lý & Cấu Hình Boss Live</span>
    </div>
    <p class="boss-hero-desc">
        Hệ thống điều khiển toàn bộ boss trong game: hiển thị chỉ số gốc từ Source code Java + SQL, cân chỉnh nhanh HP/Dame/Giáp/Crit và nạp Live tức thì vào server mà không cần khởi động lại.
    </p>

    <div class="boss-chips" aria-label="Thống kê boss">
        <div class="boss-chip">
            <div class="boss-chip-icon">👾</div>
            <div><b><?= (int)$stat_total ?></b><span>Tổng loại boss</span></div>
        </div>
        <div class="boss-chip">
            <div class="boss-chip-icon" style="color:#f59e0b">📜</div>
            <div><b style="color:#f59e0b"><?= (int)$stat_quest ?></b><span>Boss nhiệm vụ</span></div>
        </div>
        <div class="boss-chip">
            <div class="boss-chip-icon" style="color:#10b981">⚔️</div>
            <div><b style="color:#10b981"><?= (int)$stat_general ?></b><span>Boss bình thường</span></div>
        </div>
        <div class="boss-chip">
            <div class="boss-chip-icon" style="color:#ef4444">🛡️</div>
            <div><b style="color:#ef4444"><?= (int)$stat_limited ?></b><span>Giới hạn dame</span></div>
        </div>
        <div class="boss-chip">
            <div class="boss-chip-icon" style="color:#38bdf8">⚡</div>
            <div><b style="color:#38bdf8"><?= (int)$stat_custom ?></b><span>Giai đoạn tùy chỉnh</span></div>
        </div>
    </div>
</div>

<?php if($message!==''): ?>
    <div class="boss-alert-box <?= !empty($queued_command_id)?'is-success':'is-error' ?>" role="status" <?= !empty($queued_command_id)?'data-admin-command-id="'.(int)$queued_command_id.'"':'' ?>>
        <?= boss_admin_html($message) ?>
    </div>
<?php endif; ?>

<div id="bossLiveStatus" class="boss-alert-box <?= $live['fresh']?'is-success':'is-error' ?>" role="status" aria-live="polite">
    <?= $live['fresh']?'Server đang gửi heartbeat boss.':'Chưa có heartbeat boss mới. Lệnh đã lưu sẽ chờ server xử lý.' ?>
</div>

<div class="boss-table-container" style="padding: 20px;">
    <div class="boss-toolbar">
        <div class="boss-tool-group">
            <label for="bossSearch">Tìm kiếm Boss</label>
            <input id="bossSearch" placeholder="Tìm theo tên, ID, loại hoặc class (VD: Broly, Android 13, id:-32)...">
        </div>
        <div class="boss-tool-group">
            <label for="bossFilter">Bộ lọc nhóm</label>
            <select id="bossFilter">
                <option value="all">Tất cả boss (<?= (int)$stat_total ?>)</option>
                <option value="quest">Boss nhiệm vụ (<?= (int)$stat_quest ?>)</option>
                <option value="general">Boss bình thường (<?= (int)$stat_general ?>)</option>
                <option value="limited">Có giới hạn dame lên người (<?= (int)$stat_limited ?>)</option>
            </select>
        </div>
    </div>

    <div class="boss-scroll">
        <table id="bossTable">
            <thead>
                <tr>
                    <th style="min-width:260px">Boss / Loại</th>
                    <th style="min-width:200px">Giai đoạn / Map</th>
                    <th style="min-width:220px">HP / Sức đánh</th>
                    <th style="min-width:180px">Giáp / Chí mạng</th>
                    <th style="min-width:170px">Trần Dame lên người</th>
                    <th style="min-width:190px">Đang sống (Live)</th>
                    <th style="min-width:160px">Revision / ACK</th>
                    <th style="min-width:100px;text-align:center">Thao tác</th>
                </tr>
            </thead>
            <tbody id="bossRows"></tbody>
        </table>
    </div>

    <div class="boss-toolbar boss-pager" style="justify-content:space-between;margin-top:16px;margin-bottom:0">
        <button type="button" class="btn btn-outline" id="bossPrev">← Trang trước</button>
        <span id="bossPage" aria-live="polite" style="font-weight:600;color:#94a3b8"></span>
        <button type="button" class="btn btn-outline" id="bossNext">Trang sau →</button>
    </div>
</div>

<!-- Modal Chỉnh Sửa Boss Hiện Đại -->
<dialog id="bossModal" class="boss-dialog" aria-labelledby="bossTitle">
    <div class="boss-modal-head">
        <div class="boss-modal-head-left">
            <div class="boss-modal-avatar">
                <img id="bossModalAvatar" src="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/favicon-32x32.png" alt="Avatar Boss">
            </div>
            <div>
                <h2 id="bossTitle" class="boss-modal-title"></h2>
                <div id="bossKey" class="boss-modal-sub"></div>
            </div>
        </div>
        <button type="button" class="boss-modal-close" id="bossClose" aria-label="Đóng cửa sổ">✕</button>
    </div>

    <div class="boss-modal-body">
        <div id="bossModalAlert" class="boss-alert-box is-error" style="display:none"></div>

        <form id="bossForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?= boss_admin_html(get_csrf_token()) ?>">
            <input type="hidden" name="boss_key">
            <input type="hidden" name="expected_revision">
            <input type="hidden" name="request_id">
            <input type="hidden" name="drops_json">
            <input type="hidden" name="action" value="save">

            <div class="boss-card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <label style="font-size:14px;font-weight:700;display:flex;align-items:center;gap:10px;margin:0;">
                    <span>📌 Giai đoạn (Phase):</span>
                    <select name="phase" id="bossPhase" class="form-control" style="width:auto;min-width:220px;font-weight:700;background:#0f172a;color:#fff"></select>
                </label>
                <div id="bossPhaseBadge" class="boss-badge is-quest" style="font-size:12px;padding:4px 12px"></div>
            </div>

            <div class="boss-nav-tabs" role="tablist" aria-label="Nhóm thiết lập">
                <button class="boss-tab" type="button" data-panel="information" aria-selected="true">📋 ① Thông tin gốc</button>
                <button class="boss-tab" type="button" data-panel="stats" aria-selected="false">⚡ ② Chỉ số & Cân chỉnh Live</button>
                <button class="boss-tab" type="button" data-panel="drops" aria-selected="false">🎁 ③ Rơi đồ (Drop Studio)</button>
            </div>

            <!-- Tab 1: Thông tin gốc -->
            <section data-boss-panel="information">
                <div class="boss-card">
                    <p class="boss-card-title">📖 Dữ liệu mặc định trong Source Code & SQL</p>
                    <dl class="boss-kv" style="display:grid;grid-template-columns:160px 1fr;gap:10px;font-size:13.5px;margin:10px 0;">
                        <dt style="color:#94a3b8">Định danh / Lớp Java:</dt>
                        <dd id="bossInfo" style="margin:0;font-weight:600;color:#f8fafc;font-family:monospace"></dd>
                        <dt style="color:#94a3b8">Chỉ số mặc định gốc:</dt>
                        <dd id="bossDefault" style="margin:0;font-weight:600;color:#38bdf8"></dd>
                        <dt style="color:#94a3b8">Cơ chế rơi đồ gốc:</dt>
                        <dd id="bossDropSummary" style="margin:0;color:#cbd5e1"></dd>
                        <dt style="color:#94a3b8">Tệp mã nguồn:</dt>
                        <dd id="bossSource" style="margin:0;color:#94a3b8;font-family:monospace;font-size:12px"></dd>
                    </dl>
                    <p class="boss-note" style="color:#94a3b8;font-size:13px;line-height:1.6;margin-top:12px;background:rgba(0,0,0,0.2);padding:10px;border-radius:8px">
                        💡 <strong>Lưu ý:</strong> Dữ liệu trên được trích xuất trực tiếp từ file Java của Server. Khi bạn chỉnh sửa ở tab <strong>② Chỉ số</strong>, hệ thống sẽ nạp đè thông số mới ngay lập tức qua cơ chế Live Hot Reload mà không cần sửa code hay biên dịch lại JAR.
                    </p>
                </div>
            </section>

            <!-- Tab 2: Chỉ số & Cân chỉnh Live -->
            <section data-boss-panel="stats" hidden>
                <!-- Thanh đối chiếu số liệu gốc -->
                <div class="boss-base-ref" id="bossBaseStatsRef">
                    <span class="boss-base-tag">🔍 Thông số gốc đối chiếu:</span>
                    <span>HP gốc: <b id="refBaseHp" class="boss-base-val">3.000.000</b></span>
                    <span>•</span>
                    <span>Sức đánh gốc: <b id="refBaseDame" class="boss-base-val">12.055</b></span>
                    <span>•</span>
                    <span>Map: <b id="refBaseMaps" class="boss-base-val">104</b></span>
                </div>

                <div class="boss-card">
                    <p class="boss-card-title">🩸 Máu (HP) & ⚔️ Sức Đánh (Damage)</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <label style="font-size:13px;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px">
                                HP Tùy Chỉnh (Tối đa 2.147.483.647)
                            </label>
                            <input name="hp" id="inputHp" type="number" min="1" max="2147483647" class="form-control" style="background:#0f172a;color:#fff;border-color:var(--boss-border)" placeholder="Để trống = dùng số gốc">
                            <div class="boss-quick-tools" id="quickToolsHp">
                                <span style="font-size:11px;color:#94a3b8;align-self:center">Căn chỉnh nhanh:</span>
                                <button type="button" class="btn-quick" data-target="hp" data-action="base">⟲ Số gốc</button>
                                <button type="button" class="btn-quick" data-target="hp" data-action="add20">+20%</button>
                                <button type="button" class="btn-quick" data-target="hp" data-action="add50">+50%</button>
                                <button type="button" class="btn-quick" data-target="hp" data-action="mul2">x2</button>
                                <button type="button" class="btn-quick" data-target="hp" data-action="mul5">x5</button>
                                <button type="button" class="btn-quick is-reset" data-target="hp" data-action="clear">Xóa rỗng</button>
                            </div>
                        </div>

                        <div>
                            <label style="font-size:13px;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px">
                                Sức Đánh Tùy Chỉnh (Damage)
                            </label>
                            <input name="damage" id="inputDamage" type="number" min="0" max="2147483647" class="form-control" style="background:#0f172a;color:#fff;border-color:var(--boss-border)" placeholder="Để trống = dùng số gốc">
                            <div class="boss-quick-tools" id="quickToolsDamage">
                                <span style="font-size:11px;color:#94a3b8;align-self:center">Căn chỉnh nhanh:</span>
                                <button type="button" class="btn-quick" data-target="damage" data-action="base">⟲ Số gốc</button>
                                <button type="button" class="btn-quick" data-target="damage" data-action="add20">+20%</button>
                                <button type="button" class="btn-quick" data-target="damage" data-action="add50">+50%</button>
                                <button type="button" class="btn-quick" data-target="damage" data-action="mul2">x2</button>
                                <button type="button" class="btn-quick" data-target="damage" data-action="mul5">x5</button>
                                <button type="button" class="btn-quick is-reset" data-target="damage" data-action="clear">Xóa rỗng</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="boss-card">
                    <p class="boss-card-title">🛡️ Giáp (Defense) & 💥 Chí Mạng (Critical %)</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <label style="font-size:13px;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px">
                                Giáp tùy chỉnh (Trừ thẳng vào sát thương nhận vào)
                            </label>
                            <input name="defense" id="inputDefense" type="number" min="0" max="2147483647" class="form-control" style="background:#0f172a;color:#fff;border-color:var(--boss-border)" placeholder="Để trống = 0 (mặc định)">
                            <div class="boss-quick-tools">
                                <span style="font-size:11px;color:#94a3b8;align-self:center">Mẫu:</span>
                                <button type="button" class="btn-quick" data-target="defense" data-val="500">500</button>
                                <button type="button" class="btn-quick" data-target="defense" data-val="1000">1.000</button>
                                <button type="button" class="btn-quick" data-target="defense" data-val="5000">5.000</button>
                                <button type="button" class="btn-quick is-reset" data-target="defense" data-action="clear">Xóa rỗng</button>
                            </div>
                        </div>

                        <div>
                            <label style="font-size:13px;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px">
                                Chí mạng tùy chỉnh % (Tỷ lệ đòn đánh biến thành Crit)
                            </label>
                            <input name="critical" id="inputCritical" type="number" min="0" max="100" class="form-control" style="background:#0f172a;color:#fff;border-color:var(--boss-border)" placeholder="Để trống = 0% (mặc định)">
                            <div class="boss-quick-tools">
                                <span style="font-size:11px;color:#94a3b8;align-self:center">Mẫu:</span>
                                <button type="button" class="btn-quick" data-target="critical" data-val="10">10%</button>
                                <button type="button" class="btn-quick" data-target="critical" data-val="25">25%</button>
                                <button type="button" class="btn-quick" data-target="critical" data-val="50">50%</button>
                                <button type="button" class="btn-quick is-reset" data-target="critical" data-action="clear">Xóa rỗng</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="boss-card">
                    <p class="boss-card-title">⚠️ Giới hạn sát thương Boss đánh lên người chơi</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <label style="font-size:13px;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px">
                                Trần dame tuyệt đối lên người
                            </label>
                            <input name="cap_damage" type="number" min="0" max="2147483647" class="form-control" style="background:#0f172a;color:#fff;border-color:var(--boss-border)" placeholder="Để trống = không giới hạn">
                        </div>
                        <div>
                            <label style="font-size:13px;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px">
                                Trần dame theo % HP tối đa của mục tiêu (0 - 100%)
                            </label>
                            <input name="cap_percent" type="number" min="0" max="100" step="0.001" class="form-control" style="background:#0f172a;color:#fff;border-color:var(--boss-border)" placeholder="VD: 50 = tối đa 50% HP người chơi">
                        </div>
                    </div>
                </div>

                <div class="boss-card">
                    <p class="boss-card-title">🚀 Phương thức nạp Live vào Game</p>
                    <label style="font-size:13.5px;font-weight:600;display:block">
                        Áp dụng HP/Sức đánh:
                        <select name="apply_mode" class="form-control" style="margin-top:6px;background:#0f172a;color:#fff">
                            <option value="alive">Áp dụng ngay cho boss đang sống — Giữ tỷ lệ % HP còn lại</option>
                            <option value="next">Chờ lần xuất hiện tiếp theo</option>
                        </select>
                    </label>
                </div>
            </section>

            <!-- Tab 3: Rơi đồ -->
            <section data-boss-panel="drops" hidden>
                <div class="boss-card">
                    <p class="boss-card-title">🎁 Chính sách phần thưởng & Rơi đồ</p>
                    <label style="font-size:13.5px;font-weight:600;display:block;margin-bottom:12px">
                        Chính sách rơi đồ:
                        <select name="drop_mode" class="form-control" style="margin-top:6px;background:#0f172a;color:#fff">
                            <option value="default">Mặc định — Giữ nguyên cơ chế rơi đồ gốc của server</option>
                            <option value="replace">Thay thế hoàn toàn bằng các quy tắc tùy chọn dưới đây</option>
                        </select>
                    </label>
                    <p class="boss-note" style="color:#94a3b8;font-size:13px;line-height:1.5;margin:0">
                        Vật phẩm nhiệm vụ bắt buộc của game vẫn được giữ nguyên. Tỷ lệ nhóm: các món cùng tên nhóm sẽ chia xác suất theo trọng số; nhóm để trống sẽ roll độc lập.
                    </p>
                </div>

                <div id="bossDropRows"></div>
                <button type="button" class="btn btn-outline" id="bossAddDrop" style="margin-top:10px;width:100%;padding:10px;font-weight:700">
                    ＋ Thêm quy tắc rơi đồ mới
                </button>
            </section>

            <div class="boss-modal-foot">
                <div class="boss-foot-left">
                    <button type="button" class="btn-danger-reset" id="bossReset" <?= !$available?'disabled':'' ?>>
                        Đặt lại về mặc định
                    </button>
                    <select id="bossHistory" name="audit_id" class="form-control" style="width:auto;max-width:220px;font-size:12.5px;background:#0f172a;color:#fff"></select>
                    <button type="button" class="btn-history-restore" id="bossRollback" <?= !$available?'disabled':'' ?>>
                        Khôi phục bản này
                    </button>
                </div>
                <div class="boss-foot-right">
                    <button type="submit" class="btn-save-live" id="bossSubmit" <?= !$available?'disabled':'' ?>>
                        <span>⚡</span>
                        <span>Lưu & Kích Hoạt Live</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</dialog>

<script id="bossBoot" type="application/json"><?= json_encode([
    'catalog'=>$catalog,
    'configs'=>$configs,
    'audit'=>$audit,
    'commands'=>$commands,
    'runtime'=>$runtime,
    'live'=>$live,
    'restore'=>$restore,
    'available'=>$available,
    'avatars'=>$avatar_map,
    'message'=>$message,
    'is_error'=>($message!=='' && empty($queued_command_id))
],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/assets/js/boss-admin.js?v=<?= time() ?>"></script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
