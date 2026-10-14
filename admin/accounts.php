<?php
// admin/accounts.php
$page_title = 'Quản Lý Tài Khoản (Accounts)';
$active_tab = 'accounts';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/live_sync_helper.php';

$message = '';
$message_type = 'success';

// Xử lý Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 try {
  if(!verify_csrf_token($_POST['csrf_token']??''))throw new InvalidArgumentException('Invalid CSRF token');
  require_once __DIR__.'/includes/admin_command_validator.php';
  $action=$_POST['action']??'';$payload=['account_id'=>$_POST['account_id']??0];
  if($action==='update_money') {
   $type='UPDATE_ACCOUNT';foreach(['vnd','thoi_vang','tongnap'] as $f)$payload['add_'.$f]=$_POST[$f.'_add']??'0';
  } else {
   $type='ACCOUNT_ACTION';$actions=['toggle_ban'=>'ban','toggle_active'=>'active','toggle_admin'=>'admin','change_password'=>'password'];
   $payload['action']=$actions[$action]??'';
   $payload['value']=$action==='change_password'?($_POST['new_password']??''):($_POST[$payload['action'].'_status']??-1);
  }
  $payload=validate_admin_command($type,$payload);
  $id=dispatch_server_command($type,$payload,$logged_username,$_POST['request_id']??null);
  if(!$id)throw new RuntimeException('Cannot enqueue command');
  $message='Đã xếp hàng lệnh #'.$id.'; chờ máy chủ xử lý.';$queued_command_id=$id;
 }catch(Throwable $e){$message='Không thể xếp hàng: '.$e->getMessage();$message_type='danger';}
}

$search = trim($_GET['q'] ?? '');
$filter_role = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($search !== '') {
    $where_clauses[] = "(username LIKE ? OR id = ?)";
    $search_like = "%$search%";
    $search_id = (int)$search;
    $params[] = $search_like;
    $params[] = $search_id;
    $types .= "si";
}

if ($filter_role === 'admin') {
    $where_clauses[] = "(is_admin = 1 OR admin = 1)";
} elseif ($filter_role === 'banned') {
    $where_clauses[] = "ban = 1";
}

$where_sql = implode(" AND ", $where_clauses);

// Đếm tổng
$stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM account WHERE $where_sql");
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_rows = (int)$stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Lấy danh sách
$stmt = $conn->prepare("SELECT id, username, is_admin, admin, active, ban, vnd, tongnap, thoi_vang, create_time FROM account WHERE $where_sql ORDER BY id DESC LIMIT ? OFFSET ?");
$types_with_limit = $types . "ii";
$params_with_limit = array_merge($params, [$limit, $offset]);
$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php if ($message): ?>
    <div style="background:var(--<?= $message_type ?>-light); border:1px solid var(--<?= $message_type ?>); color:var(--text-primary); padding:12px 18px; border-radius:var(--radius-md); margin-bottom:20px; font-weight:500;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Search & Filter Card -->
<div class="card">
    <div class="card-body">
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
            <div style="flex:1; min-width:200px;">
                <input type="text" name="q" class="form-control" placeholder="Tìm kiếm theo Username hoặc ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div style="width:160px;">
                <select name="role" class="form-control">
                    <option value="">Tất cả tài khoản</option>
                    <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Chỉ Admin</option>
                    <option value="banned" <?= $filter_role === 'banned' ? 'selected' : '' ?>>Tài khoản bị khóa</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Tìm kiếm</button>
            <?php if ($search !== '' || $filter_role !== ''): ?>
                <a href="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/accounts.php" class="btn btn-outline">Xóa lọc</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Account Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>👥</span>
            <span>Danh Sách Tài Khoản (<?= number_format($total_rows) ?> kết quả)</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Vai Trò</th>
                        <th>Số Dư VND</th>
                        <th>Tổng Nạp</th>
                        <th>Thỏi Vàng</th>
                        <th>Kích Hoạt</th>
                        <th>Trạng Thái</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($accounts)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; color:var(--text-muted); padding:30px;">
                            Không tìm thấy tài khoản nào phù hợp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($accounts as $acc): ?>
                        <tr>
                            <td>#<?= (int)$acc['id'] ?></td>
                            <td>
                                <strong style="color:var(--text-primary); font-size:14px;"><?= htmlspecialchars($acc['username']) ?></strong>
                            </td>
                            <td>
                                <?php if (($acc['is_admin'] ?? 0) == 1 || ($acc['admin'] ?? 0) == 1): ?>
                                    <span class="badge badge-danger">ADMIN</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">Member</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#10b981; font-weight:700;"><?= number_format((int)$acc['vnd']) ?>đ</td>
                            <td><?= number_format((int)$acc['tongnap']) ?>đ</td>
                            <td><?= number_format((int)$acc['thoi_vang']) ?> 🟡</td>
                            <td>
                                <?php if (($acc['active'] ?? 0) == 1): ?>
                                    <span class="badge badge-success">Đã kích hoạt</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Chưa kích hoạt</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($acc['ban'] ?? 0) == 1): ?>
                                    <span class="badge badge-danger">ĐANG KHÓA</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Bình thường</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="btn btn-sm btn-outline" onclick='openEditModal(<?= json_encode($acc) ?>)'>
                                        ✏️ Sửa
                                    </button>

                                    <!-- Quick Ban/Unban -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn <?= $acc['ban'] == 1 ? 'mở khóa' : 'khóa' ?> tài khoản này?');">
                                        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle_ban">
                                        <input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                                        <input type="hidden" name="ban_status" value="<?= $acc['ban'] == 1 ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $acc['ban'] == 1 ? 'btn-success' : 'btn-danger' ?>">
                                            <?= $acc['ban'] == 1 ? 'Mở Khóa' : 'Khóa' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="padding:16px 20px; display:flex; justify-content:center; gap:6px;">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?q=<?= urlencode($search) ?>&role=<?= urlencode($filter_role) ?>&page=<?= $i ?>" 
                       class="btn btn-sm <?= $page === $i ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Chỉnh Sửa Chi Tiết -->
<div class="modal-backdrop" id="editAccountModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-size:16px; font-weight:700;">⚙️ Quản Trị Tài Khoản: <span id="modalUsername" style="color:var(--primary);"></span></h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('editAccountModal')">✕</button>
        </div>
        <div class="modal-body">
            <!-- Cộng Trừ Tiền -->
            <form method="POST" style="margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid var(--border-color);">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="update_money">
                <input type="hidden" name="account_id" id="moneyAccId">

                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">💰 Cộng / Trừ Số Dư</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div>
                        <label class="form-label">Cộng/Trừ VND (+ / -):</label>
                        <input type="number" name="vnd_add" class="form-control" value="0" step="1000">
                    </div>
                    <div>
                        <label class="form-label">Cộng/Trừ Thỏi Vàng:</label>
                        <input type="number" name="thoi_vang_add" class="form-control" value="0">
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label class="form-label">Cộng Tổng Nạp (+):</label>
                    <input type="number" name="tongnap_add" class="form-control" value="0" step="1000">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Cập Nhật Số Dư</button>
            </form>

            <!-- Đổi Mật Khẩu -->
            <form method="POST" style="margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid var(--border-color);">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="account_id" id="passAccId">

                <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:12px;">🔑 Đổi Mật Khẩu</h4>
                <div class="form-group">
                    <input type="text" name="new_password" class="form-control" placeholder="Nhập mật khẩu mới..." required minlength="4">
                </div>
                <button type="submit" class="btn btn-warning" style="width:100%;">Đổi Mật Khẩu Ngay</button>
            </form>

            <!-- Phân Quyền & Kích Hoạt -->
            <div style="display:flex; gap:10px;">
                <form method="POST" style="flex:1;">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="toggle_admin">
                    <input type="hidden" name="account_id" id="adminAccId">
                    <input type="hidden" name="admin_status" id="adminStatusVal">
                    <button type="submit" id="btnAdminToggle" class="btn btn-outline" style="width:100%;"></button>
                </form>

                <form method="POST" style="flex:1;">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="account_id" id="activeAccId">
                    <input type="hidden" name="active_status" id="activeStatusVal">
                    <button type="submit" id="btnActiveToggle" class="btn btn-outline" style="width:100%;"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openEditModal(acc) {
    document.getElementById('modalUsername').innerText = `${acc.username} (#${acc.id})`;
    document.getElementById('moneyAccId').value = acc.id;
    document.getElementById('passAccId').value = acc.id;
    document.getElementById('adminAccId').value = acc.id;
    document.getElementById('activeAccId').value = acc.id;

    // Admin status
    const isAdmin = (acc.is_admin == 1 || acc.admin == 1);
    document.getElementById('adminStatusVal').value = isAdmin ? 0 : 1;
    document.getElementById('btnAdminToggle').innerText = isAdmin ? '🔻 Hạ Quyền Admin' : '⭐ Nâng Lên Admin';
    document.getElementById('btnAdminToggle').className = isAdmin ? 'btn btn-danger' : 'btn btn-success';

    // Active status
    const isActive = (acc.active == 1);
    document.getElementById('activeStatusVal').value = isActive ? 0 : 1;
    document.getElementById('btnActiveToggle').innerText = isActive ? '🔒 Hủy Kích Hoạt' : '✅ Kích Hoạt Thành Viên';

    openModal('editAccountModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
