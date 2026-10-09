<?php
// admin/includes/live_sync_helper.php
// Quản lý việc đẩy và kiểm tra trạng thái lệnh Live Sync (Hot Reload)

require_once __DIR__ . '/../../connect.php';

function admin_actor_id(): int {
    global $logged_user_id;
    return (int)($logged_user_id ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
}

function admin_new_request_id() {
    $bytes=random_bytes(16);
    $bytes[6]=chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8]=chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex=bin2hex($bytes);
    return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
}

function dispatch_server_command($command_type, $payload = [], $creator = 'admin', $requestId = null) {
    global $conn, $logged_user_id, $logged_username;
    if (!$conn || !($conn instanceof mysqli)) {
        return false;
    }

    $requestId = $requestId ?? admin_new_request_id();
    if (!is_string($requestId) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $requestId)) return false;
    $actor = admin_actor_id();
    if ($actor <= 0) return false;
    $creator = $logged_username ?? $creator;
    // Commands always use a JSON object envelope, including an empty reload.
    // Nested item/option arrays retain their array representation.
    $payload_json = json_encode((object)$payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload_json === false) return false;
    $stmt = $conn->prepare("INSERT INTO server_command_queue (command_type, payload, status, created_by, request_id, actor_account_id) VALUES (?, ?, 'pending', ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssi", $command_type, $payload_json, $creator, $requestId, $actor);
        try { $res = $stmt->execute(); } catch (mysqli_sql_exception $e) { $res = false; }
        $insert_id = $stmt->insert_id;
        $stmt->close();
        if ($res && $insert_id > 0) return $insert_id;
        $lookup = $conn->prepare("SELECT id, command_type, payload, actor_account_id FROM server_command_queue WHERE request_id = ? LIMIT 1");
        if (!$lookup) return false;
        $lookup->bind_param('s', $requestId);
        if (!$lookup->execute()) { $lookup->close(); return false; }
        $row = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if ($row && (int)$row['actor_account_id'] === $actor && $row['command_type'] === $command_type && $row['payload'] === $payload_json) return (int)$row['id'];
        return false;
    }
    return false;
}

function get_recent_server_commands($limit = 10) {
    global $conn;
    $commands = [];
    if (!$conn || !($conn instanceof mysqli)) {
        return $commands;
    }

    $limit = (int)$limit;
    $result = $conn->query("SELECT * FROM server_command_queue ORDER BY id DESC LIMIT $limit");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['command_type'] === 'ACCOUNT_ACTION') {
                $display_payload=json_decode($row['payload'],true);
                if (($display_payload['action']??'') === 'password') {
                    $display_payload['value']='[redacted]';
                    $row['payload']=json_encode($display_payload,JSON_UNESCAPED_UNICODE);
                }
            }
            $commands[] = $row;
        }
    }
    return $commands;
}

function get_server_command_by_id($id) {
    global $conn;
    if (!$conn || !($conn instanceof mysqli)) {
        return null;
    }

    $id = (int)$id;
    $stmt = $conn->prepare("SELECT * FROM server_command_queue WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }
    return null;
}

function admin_catalog_transaction(callable $mutation, $type, $payload, $requestId = null) {
    global $conn, $logged_user_id;
    if (!$conn->begin_transaction()) throw new RuntimeException('Cannot begin catalog transaction');
    try {
        // A form retry must not apply a multiplier twice. The queue payload records
        // all edited fields, so reusing a key for another edit is a conflict.
        if ($requestId !== null) {
            $stmt=$conn->prepare('SELECT id, command_type, payload, actor_account_id FROM server_command_queue WHERE request_id = ? LIMIT 1 FOR UPDATE');
            if (!$stmt) throw new RuntimeException('Cannot inspect request');
            $stmt->bind_param('s',$requestId);
            if (!$stmt->execute()) throw new RuntimeException('Cannot inspect request');
            $existing=$stmt->get_result()->fetch_assoc();$stmt->close();
            if ($existing) {
                $id=dispatch_server_command($type,$payload,'admin',$requestId);
                if (!$id) throw new RuntimeException('Request ID conflicts');
                $conn->rollback();return $id;
            }
        }
        $mutation();
        $id=dispatch_server_command($type,$payload,'admin',$requestId);
        if (!$id) throw new RuntimeException('Cannot enqueue catalog reload');
        if (!$conn->commit()) throw new RuntimeException('Cannot commit catalog update');
        return $id;
    } catch (Throwable $e) {$conn->rollback();throw $e;}
}

function admin_execute_statement($sql, $types, $values) {
    global $conn;
    $stmt=$conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Cannot prepare catalog update');
    $stmt->bind_param($types,...$values);
    if (!$stmt->execute()) {$stmt->close();throw new RuntimeException('Cannot update catalog');}
    $stmt->close();
}

// Call inside the catalog transaction so the row cannot change between
// verifying the custom shop rules and saving its new price.
function admin_validate_shop_price($itemId, $cost, $typeSell) {
    global $conn;
    $stmt=$conn->prepare('SELECT s.tag_name, s.npc_id, s.type_shop, i.temp_id, i.icon_spec FROM item_shop i JOIN tab_shop t ON i.tab_id = t.id JOIN shop s ON t.shop_id = s.id WHERE i.id = ? FOR UPDATE');
    if (!$stmt) throw new RuntimeException('Cannot inspect shop configuration');
    $stmt->bind_param('i',$itemId);
    if (!$stmt->execute()) {$stmt->close();throw new RuntimeException('Cannot inspect shop configuration');}
    $row=$stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$row) throw new InvalidArgumentException('Shop item does not exist');
    $tag=$row['tag_name'];$template=(int)$row['temp_id'];
    if (!in_array($tag,['THIEN_SU','BILL','SANTA_HONG_NGOC'],true)) return;
    $npc=['THIEN_SU'=>56,'BILL'=>55,'SANTA_HONG_NGOC'=>39][$tag];
    if ((int)$row['npc_id']!==$npc || (int)$row['type_shop']!==3 || (int)$row['icon_spec']!==7743 || $typeSell!==3) {
        throw new InvalidArgumentException('Shop này phải giữ cấu hình Hồng ngọc hiện tại');
    }
    $fixed=null;
    if ($tag==='THIEN_SU') $fixed=10;
    elseif ($tag==='BILL') {
        if ($template<650 || $template>662) throw new InvalidArgumentException('Vật phẩm Beerus không hợp lệ');
        $fixed=100;
    } else $fixed=[933=>100,934=>100,1820=>100,935=>10,1775=>50000][$template]??null;
    if ($fixed!==null && $cost!==$fixed) throw new InvalidArgumentException('Giá vật phẩm cố định: '.$fixed.' Hồng ngọc');
}
