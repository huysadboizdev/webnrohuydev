<?php
// admin/includes/player_data_helper.php
// Helper giải mã & đóng gói chuẩn 100% định dạng Game Server Java (MrBlue.java / PlayerDAO.java)

require_once __DIR__ . '/../../connect.php';
require_once __DIR__ . '/icon_helper.php';

function decode_game_array($value) {
    if (is_string($value)) $value=json_decode($value,true,512,JSON_BIGINT_AS_STRING);
    return is_array($value)?$value:[];
}

/**
 * Giải mã danh sách trang bị (items_body, items_bag, items_box)
 */
function decode_inventory_items($json_str) {
    global $conn;
    $slots = [];
    $raw_array = decode_game_array($json_str ?? '[]');
    if (!is_array($raw_array)) {
        return $slots;
    }

    foreach ($raw_array as $idx => $raw_item) {
        $item_data = is_string($raw_item) ? json_decode($raw_item, true) : $raw_item;
        if (!is_array($item_data) || count($item_data) < 2) {
            $slots[$idx] = ['empty' => true, 'index' => $idx, 'id' => -1, 'quantity' => 0];
            continue;
        }

        $template_id = (int)($item_data[0] ?? -1);
        $quantity = (int)($item_data[1] ?? 0);
        $options_raw = $item_data[2] ?? '[]';
        $create_time = $item_data[3] ?? 0;

        if ($template_id <= -1 || $quantity <= 0) {
            $slots[$idx] = ['empty' => true, 'index' => $idx, 'id' => -1, 'quantity' => 0];
            continue;
        }

        // Lấy thông tin vật phẩm từ item_template
        $stmt = $conn->prepare("SELECT NAME AS name, icon_id, TYPE AS type, description, gender FROM item_template WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $template_id);
        $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Giải mã options
        $options = [];
        $opt_list = is_string($options_raw) ? json_decode($options_raw, true) : $options_raw;
        if (is_array($opt_list)) {
            foreach ($opt_list as $opt_raw) {
                $opt_arr = is_string($opt_raw) ? json_decode($opt_raw, true) : $opt_raw;
                if (is_array($opt_arr) && count($opt_arr) >= 2) {
                    $opt_id = (int)$opt_arr[0];
                    $opt_param = (int)$opt_arr[1];

                    // Lấy tên option từ item_option_template
                    $stmt_opt = $conn->prepare("SELECT NAME AS name FROM item_option_template WHERE id = ? LIMIT 1");
                    $stmt_opt->bind_param("i", $opt_id);
                    $stmt_opt->execute();
                    $opt_meta = $stmt_opt->get_result()->fetch_assoc();
                    $stmt_opt->close();

                    $opt_name = $opt_meta['name'] ?? "Option #$opt_id";
                    $opt_formatted = str_replace('#', number_format($opt_param), $opt_name);

                    $options[] = [
                        'id' => $opt_id,
                        'param' => $opt_param,
                        'text' => $opt_formatted
                    ];
                }
            }
        }

        $slots[$idx] = [
            'empty' => false,
            'index' => $idx,
            'id' => $template_id,
            'name' => $meta['name'] ?? "Vật phẩm #$template_id",
            'icon_id' => $meta['icon_id'] ?? $template_id,
            'icon_url' => get_item_icon_url($meta['icon_id'] ?? $template_id),
            'type' => (int)($meta['type'] ?? 0),
            'gender' => (int)($meta['gender'] ?? 0),
            'description' => $meta['description'] ?? '',
            'quantity' => $quantity,
            'options' => $options,
            'create_time' => $create_time,
            'expected_item' => $item_data
        ];
    }

    return $slots;
}

/**
 * Đóng gói 1 ô đồ chuẩn format Java JSON
 */
function encode_item_slot($template_id, $quantity = 1, $options = [], $timestamp = null) {
    if ($timestamp === null) {
        $timestamp = round(microtime(true) * 1000);
    }
    if ($template_id <= -1 || $quantity <= 0) {
        return "[-1,0,\"[]\",$timestamp]";
    }

    $opt_inner = [];
    foreach ($options as $opt) {
        $id = is_array($opt) ? ($opt['id'] ?? $opt[0] ?? 0) : 0;
        $param = is_array($opt) ? ($opt['param'] ?? $opt[1] ?? 0) : 0;
        $opt_inner[] = "[$id,$param]";
    }
    $opt_json = json_encode($opt_inner);
    return json_encode([(int)$template_id, (int)$quantity, $opt_json, (int)$timestamp]);
}

/**
 * Decode pet data without changing the stored snapshot.
 */
function default_pet_name($type) {
    return [0=>'Đệ tử',1=>'Mabư',2=>'Uub',3=>'Kid Beer',4=>'Kid Jiren'][(int)$type] ?? 'Không xác định';
}

function decode_player_pet($pet_json) {
    $raw = decode_game_array($pet_json ?? '[]');
    if (!is_array($raw) || count($raw) < 4) {
        return null;
    }

    $info = decode_game_array($raw[0] ?? '[]');
    $point = decode_game_array($raw[1] ?? '[]');
    $items_raw = $raw[2] ?? '[]';
    $skills_raw = $raw[3] ?? '[]';

    $pet_skills = [];
    $skill_list = decode_game_array($skills_raw);
    foreach ($skill_list as $slot_index => $sk_slot) {
        $sk = decode_game_array($sk_slot);
        if (is_array($sk) && count($sk) >= 2) {
            $sk_id = (int)$sk[0];
            $sk_point = (int)$sk[1];
            $pet_skills[$slot_index] = [
                'skill_id' => $sk_id,
                'point' => $sk_point,
                'name' => get_skill_name($sk_id)
            ];
        }
    }

    return [
        'has_pet' => true,
        'type' => (int)($info[0] ?? 0),
        'gender' => (int)($info[1] ?? 0),
        'name' => default_pet_name($info[0] ?? 0),
        'power' => (string)($point[1] ?? 0),
        'tiem_nang' => (string)($point[2] ?? 0),
        'hpg' => (int)($point[5] ?? 0),
        'mpg' => (int)($point[6] ?? 0),
        'dameg' => (int)($point[7] ?? 0),
        'defg' => (int)($point[8] ?? 0),
        'critg' => (int)($point[9] ?? 0),
        'items_body' => decode_inventory_items($items_raw),
        'skills' => $pet_skills,
        'raw' => $raw
    ];
}

/**
 * Lấy tên Map từ map_template
 */
function get_map_name($map_id) {
    global $conn;
    $map_id = (int)$map_id;
    $stmt = $conn->prepare("SELECT NAME AS name FROM map_template WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $map_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? "Bản đồ #$map_id";
}

/**
 * Lấy tên Skill từ skill_template
 */
function get_skill_name($skill_id) {
    global $conn;
    $skill_id = (int)$skill_id;
    if ($skill_id == -1) return "Chưa mở khóa";
    $stmt = $conn->prepare("SELECT NAME AS name FROM skill_template WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $skill_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? "Kỹ năng #$skill_id";
}

/**
 * Lấy tên Nhiệm vụ từ task_main_template
 */
function get_task_info($task_id) {
    global $conn;
    $task_id = (int)$task_id;
    $stmt = $conn->prepare("SELECT NAME AS name, detail FROM task_main_template WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [
        'id' => $task_id,
        'name' => $row['name'] ?? "Nhiệm vụ #$task_id",
        'detail' => $row['detail'] ?? ''
    ];
}
