<?php
// admin/api/search_items.php
define('ADMIN_JSON_API',true);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../includes/icon_helper.php';

$action = $_GET['action'] ?? 'items';

if ($action === 'items') {
    $q = trim($_GET['q'] ?? '');
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

    if ($q !== '') {
        $stmt = $conn->prepare("SELECT id, NAME AS name, description, icon_id, TYPE AS type, gender FROM item_template WHERE NAME LIKE ? OR id = ? ORDER BY id ASC LIMIT ?");
        $q_like = "%$q%";
        $q_id = (int)$q;
        $stmt->bind_param("sii", $q_like, $q_id, $limit);
    } else {
        $stmt = $conn->prepare("SELECT id, NAME AS name, description, icon_id, TYPE AS type, gender FROM item_template ORDER BY id ASC LIMIT ?");
        $stmt->bind_param("i", $limit);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $items = [];

    foreach ($rows as $r) {
        $items[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'description' => $r['description'] ?? '',
            'icon_id' => (int)$r['icon_id'],
            'icon_url' => get_item_icon_url($r['icon_id'])
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $items]);
    exit();
}

if ($action === 'options') {
    $q=trim($_GET['q']??'');$limit=min(252,max(1,(int)($_GET['limit']??252)));$offset=max(0,(int)($_GET['offset']??0));
    $stmt=$conn->prepare('SELECT id, NAME AS name FROM item_option_template WHERE NAME LIKE ? OR id = ? ORDER BY id ASC LIMIT ? OFFSET ?');
    $like='%'.$q.'%';$id=(int)$q;$stmt->bind_param('siii',$like,$id,$limit,$offset);
    $stmt->execute();
    $options = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $options]);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
