<?php
// admin/gameplay.php
$page_title = 'Gameplay: Kỹ Năng, Nhiệm Vụ & Danh Hiệu';
$active_tab = 'gameplay';
require_once __DIR__ . '/includes/header.php';

$subtab = $_GET['subtab'] ?? 'skills';

// 1. Kỹ Năng
$skills = [];
if ($subtab === 'skills') {
    $res = $conn->query("SELECT *, NAME AS name, nclass_id AS nclass_id FROM skill_template ORDER BY id ASC LIMIT 50");
    if ($res) $skills = $res->fetch_all(MYSQLI_ASSOC);
}

// 2. Nhiệm Vụ
$tasks = [];
if ($subtab === 'tasks') {
    $res = $conn->query("SELECT *, NAME AS name FROM task_main_template ORDER BY id ASC LIMIT 50");
    if ($res) $tasks = $res->fetch_all(MYSQLI_ASSOC);
}

// 3. Danh Hiệu & Huy Hiệu
$badges = [];
if ($subtab === 'badges') {
    $res = $conn->query("SELECT *, NAME AS name, Options AS options FROM data_badges ORDER BY id ASC LIMIT 50");
    if ($res) $badges = $res->fetch_all(MYSQLI_ASSOC);
}

// 4. Pet Rồng
$dragon_pets = [];
if ($subtab === 'dragon') {
    $res = $conn->query("SELECT * FROM dragon_pet_config LIMIT 50");
    if ($res) $dragon_pets = $res->fetch_all(MYSQLI_ASSOC);
}
?>

<!-- Subtab navigation -->
<div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
    <a href="?subtab=skills" class="btn <?= $subtab === 'skills' ? 'btn-primary' : 'btn-outline' ?>">
        ⚡ Kỹ Năng (Skills)
    </a>
    <a href="?subtab=tasks" class="btn <?= $subtab === 'tasks' ? 'btn-primary' : 'btn-outline' ?>">
        📜 Nhiệm Vụ Chính Tuyến
    </a>
    <a href="?subtab=badges" class="btn <?= $subtab === 'badges' ? 'btn-primary' : 'btn-outline' ?>">
        🎖️ Danh Hiệu & Huy Hiệu
    </a>
    <a href="?subtab=dragon" class="btn <?= $subtab === 'dragon' ? 'btn-primary' : 'btn-outline' ?>">
        🐉 Pet Rồng Thần
    </a>
</div>

<?php if ($subtab === 'skills'): ?>
<!-- Skills Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>⚡</span>
            <span>Danh Sách Kỹ Năng (`skill_template`)</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Chiêu Thức</th>
                        <th>Cấp Tối Đa</th>
                        <th>Mana Tiêu Thao</th>
                        <th>Hành Tinh</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($skills)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">Không có dữ liệu chiêu thức.</td></tr>
                <?php else: ?>
                    <?php foreach ($skills as $sk): ?>
                        <tr>
                            <td>#<?= (int)$sk['id'] ?></td>
                            <td><strong style="color:var(--text-primary);"><?= htmlspecialchars($sk['name']) ?></strong></td>
                            <td><span class="badge badge-primary"><?= (int)($sk['max_point'] ?? 7) ?> Cấp</span></td>
                            <td><?= (int)($sk['mana_use_type'] ?? 0) ?></td>
                            <td>
                                <span class="badge badge-purple">
                                    <?= ((int)($sk['nclass_id'] ?? 0) === 0 ? 'Trái Đất' : (((int)($sk['nclass_id'] ?? 0) === 1 ? 'Namếc' : 'Xayda'))) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($subtab === 'tasks'): ?>
<!-- Tasks Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>📜</span>
            <span>Nhiệm Vụ Chính Tuyến (`task_main_template`)</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Nhiệm Vụ</th>
                        <th>Mô Tả Chi Tiết</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($tasks)): ?>
                    <tr><td colspan="3" style="text-align:center; padding:30px; color:var(--text-muted);">Không có dữ liệu nhiệm vụ.</td></tr>
                <?php else: ?>
                    <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td>#<?= (int)$t['id'] ?></td>
                            <td><strong style="color:#f59e0b;"><?= htmlspecialchars($t['name']) ?></strong></td>
                            <td style="color:var(--text-secondary);"><?= htmlspecialchars($t['detail'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($subtab === 'badges'): ?>
<!-- Badges Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>🎖️</span>
            <span>Danh Sách Danh Hiệu (`data_badges`)</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Danh Hiệu</th>
                        <th>Chỉ Số Thưởng</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($badges)): ?>
                    <tr><td colspan="3" style="text-align:center; padding:30px; color:var(--text-muted);">Không có danh hiệu nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($badges as $b): ?>
                        <tr>
                            <td>#<?= (int)$b['id'] ?></td>
                            <td><strong style="color:#ec4899;"><?= htmlspecialchars($b['name'] ?? "Danh hiệu #{$b['id']}") ?></strong></td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($b['options'] ?? '{}') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($subtab === 'dragon'): ?>
<!-- Dragon Pet Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>🐉</span>
            <span>Cấu Hình Pet Rồng Thần (`dragon_pet_config`)</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cấp Độ</th>
                        <th>Chỉ Số</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($dragon_pets)): ?>
                    <tr><td colspan="3" style="text-align:center; padding:30px; color:var(--text-muted);">Chưa có cấu hình pet rồng.</td></tr>
                <?php else: ?>
                    <?php foreach ($dragon_pets as $dp): ?>
                        <tr>
                            <td>#<?= (int)($dp['id'] ?? 1) ?></td>
                            <td>Level <?= (int)($dp['level'] ?? 1) ?></td>
                            <td style="font-family:monospace;"><?= htmlspecialchars(json_encode($dp, JSON_UNESCAPED_UNICODE)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
