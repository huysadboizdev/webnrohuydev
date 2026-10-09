# Comprehensive Player & Account Manager Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây dựng trung tâm quản trị chi tiết người chơi và tài khoản (`player_detail.php`), cho phép soi 100% dữ liệu thực tế (vị trí map, chỉ số chiến đấu, kỹ năng, nhiệm vụ, trang bị trên người, túi đồ, rương đồ, đệ tử kèm icon RPG trực quan), đồng thời hỗ trợ đầy đủ thao tác thêm, sửa, xóa, tặng quà trực tiếp và Live Sync vào game.

**Architecture:** Thiết kế giao diện chi tiết người chơi theo kiến trúc Tabbed Dashboard (Stats, Equipment Doll, Inventory Grid, Pet Studio, Quest & Skills, Linked Account). Dữ liệu nhị phân/JSON phức tạp trong database (`data_point`, `data_location`, `items_body`, `items_bag`, `items_box`, `pet`, `skills`, `data_task`) được giải mã và tái đóng gói tự động bằng các helper chuyên biệt, kết nối trực tiếp với Live Sync Engine để đồng bộ an toàn khi nhân vật online/offline.

**Tech Stack:** PHP 8.2, MySQL / MariaDB (Prepared Statements), Vanilla CSS Grid / RPG Inventory Doll, Vanilla JS / Fetch API.

---

### File Structure Map

```text
webgoc2026/
├── admin/
│   ├── players.php                    # Danh sách người chơi nâng cao (tìm kiếm, lọc, xem nhanh)
│   ├── player_detail.php             # TRUNG TÂM QUẢN TRỊ CHI TIẾT 100% THÔNG TIN NHÂN VẬT
│   ├── includes/
│   │   ├── player_data_helper.php     # Helper giải mã và đóng gói JSON items, pet, point, skills
│   │   └── header.php                 # Header dùng chung
│   ├── api/
│   │   ├── player_actions.php         # API xử lý: Tặng đồ, xóa đồ, đổi skill, đổi nhiệm vụ, buff đệ tử
│   │   └── search_items.php           # API tìm kiếm vật phẩm kèm icon (đã có từ plan trước)
│   └── assets/
│       └── css/
│           └── admin.css              # Bổ sung CSS cho RPG Doll, Slot Grid và Pet Card
```

---

### Task 1: Xây dựng Bộ Giải Mã & Đóng Gói Dữ Liệu Nhân Vật (`player_data_helper.php`)

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\includes\player_data_helper.php`

- [ ] **Step 1: Viết hàm giải mã `items_body`, `items_bag`, `items_box`**
Chuyển đổi chuỗi mảng JSON phức tạp từ database thành mảng cấu trúc rõ ràng kèm tra cứu tên và icon từ `item_template`.
```php
<?php
// admin/includes/player_data_helper.php

function decode_inventory_items($json_str) {
    global $conn;
    $slots = [];
    $raw_array = json_decode($json_str, true);
    if (!is_array($raw_array)) {
        return $slots;
    }

    foreach ($raw_array as $idx => $raw_item) {
        $item_data = is_string($raw_item) ? json_decode($raw_item, true) : $raw_item;
        if (!is_array($item_data) || count($item_data) < 3) {
            $slots[$idx] = ['empty' => true, 'index' => $idx];
            continue;
        }

        $template_id = (int)($item_data[0] ?? -1);
        $quantity = (int)($item_data[1] ?? 0);
        $options_raw = $item_data[2] ?? '[]';
        $create_time = $item_data[3] ?? 0;

        if ($template_id <= -1 || $quantity <= 0) {
            $slots[$idx] = ['empty' => true, 'index' => $idx];
            continue;
        }

        // Lấy thông tin template
        $stmt = $conn->prepare("SELECT name, icon_id, type, description FROM item_template WHERE id = ? LIMIT 1");
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
                    // Lấy tên option
                    $stmt_opt = $conn->prepare("SELECT name FROM item_option_template WHERE id = ? LIMIT 1");
                    $stmt_opt->bind_param("i", $opt_id);
                    $stmt_opt->execute();
                    $opt_meta = $stmt_opt->get_result()->fetch_assoc();
                    $stmt_opt->close();

                    $opt_name = $opt_meta['name'] ?? "Option #$opt_id";
                    $opt_formatted = str_replace('#', $opt_param, $opt_name);

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
            'type' => $meta['type'] ?? 0,
            'quantity' => $quantity,
            'options' => $options,
            'create_time' => $create_time
        ];
    }

    return $slots;
}
```

- [ ] **Step 2: Viết hàm giải mã và đóng gói Đệ tử (`pet`)**
Giải mã 4 phần tử của `pet`: (0: thông tin cơ bản, 1: chỉ số chiến đấu, 2: đồ trang bị, 3: kỹ năng đệ tử).
```php
function decode_player_pet($pet_json) {
    $raw = json_decode($pet_json, true);
    if (!is_array($raw) || count($raw) < 4) {
        return null;
    }

    $info = json_decode($raw[0] ?? '[]', true) ?: [];
    $point = json_decode($raw[1] ?? '[]', true) ?: [];
    $items_raw = $raw[2] ?? '[]';
    $skills_raw = $raw[3] ?? '[]';

    $pet_skills = [];
    $skill_list = json_decode($skills_raw, true) ?: [];
    foreach ($skill_list as $sk_slot) {
        $sk = json_decode($sk_slot, true);
        if (is_array($sk) && count($sk) >= 2) {
            $pet_skills[] = [
                'skill_id' => (int)$sk[0],
                'point' => (int)$sk[1]
            ];
        }
    }

    return [
        'has_pet' => true,
        'type' => (int)($info[0] ?? 0),
        'gender' => (int)($info[1] ?? 0),
        'name' => str_replace('$', '', ($info[2] ?? 'Đệ tử')),
        'power' => (float)($point[1] ?? 0),
        'tiem_nang' => (float)($point[2] ?? 0),
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
```

- [ ] **Step 3: Viết hàm tặng đồ trực tiếp vào Hành trang (`add_item_to_inventory`)**
Tìm ô trống đầu tiên (`template_id == -1`), chèn vật phẩm mới kèm số lượng và option vào mảng `items_bag` rồi cập nhật vào CSDL.

- [ ] **Step 4: Viết hàm xóa hoặc sửa số lượng món đồ trong Hành trang/Rương đồ**

---

### Task 2: Thiết kế Giao Diện Quản Trị Chi Tiết Nhân Vật (`player_detail.php`)

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\player_detail.php`

- [ ] **Step 1: Tạo Header thông tin tổng quan người chơi**
Hiển thị:
- Avatar đầu tóc nhân vật, Tên nhân vật, ID, Account ID, Tên đăng nhập web (`username`).
- Vị trí hiện tại: Lấy `data_location[0]` -> truy vấn tên bản đồ từ `map_template` (Ví dụ: "Map 105: Đồng Nam Karin") kèm toạ độ `X: 684, Y: 408`.
- Cấp độ sức mạnh, tổng vàng, ngọc xanh, hồng ngọc.
- Thanh công cụ hành động tức thì: Nút "Kick Disconnect (Live)", "Đưa Về Làng", "Bơm Đầy Máu/KI", "Nâng Cây Đậu Cấp 10".

- [ ] **Step 2: Xây dựng Tab 1: Chỉ số & Sức mạnh (Stats)**
Form chỉnh sửa chi tiết:
- Sức mạnh (`power`), Tiềm năng (`tiemNang`), Giới hạn sức mạnh (`limitPower`).
- HP gốc, KI gốc, Sức đánh gốc, Giáp gốc, Chí mạng gốc.
- Vàng, Ngọc xanh, Hồng ngọc.
- Đổi tên nhân vật, Đổi hành tinh (Trái Đất / Namếc / Xayda).

- [ ] **Step 3: Xây dựng Tab 2: Trang bị Đang Mặc (`items_body`)**
Hiển thị khung hình búp bê trang bị (RPG Doll):
- 6-10 ô trang bị: Áo, Quần, Găng, Giày, Rada, Cải trang, Phụ kiện.
- Mỗi ô trang bị có hình ảnh Icon game thực tế, tên món đồ, phẩm chất và danh sách các dòng chỉ số Option.
- Nút "Tháo đồ vào rương" và "Xóa trang bị".

- [ ] **Step 4: Xây dựng Tab 3: Hành trang (`items_bag`) & Rương đồ (`items_box`)**
- Grid ô đồ trực quan 8x5 hoặc 10x4.
- Nhấp vào từng ô đồ: Xem popover chi tiết (Tên đồ, số lượng, Option).
- Nút "Xóa đồ này khỏi túi" và "Sửa số lượng".
- **Modal "Tặng Đồ Trực Tiếp (Gift Item)"**:
  - Gõ tìm đồ từ catalog `item_template` (hiển thị icon và tên).
  - Chọn số lượng (1 - 9999).
  - Chọn thêm Option (Sát thương, Giáp, Không thể giao dịch...).
  - Chọn thêm vào Túi đồ hay Rương đồ.
  - Bấm "Tặng Ngay" -> Thêm vào ô trống và thông báo thành công.

- [ ] **Step 5: Xây dựng Tab 4: Đệ tử (Pet Studio)**
- Nếu chưa có đệ tử: Nút "Tạo Đệ Tử Mới" (Chọn loại đệ: Mabư, Kid Jiren, Beerus...).
- Nếu đã có đệ tử:
  - Hiển thị thông tin: Tên đệ tử, Sức mạnh, Tiềm năng, HP, KI, Sức đánh.
  - Form buff chỉ số đệ tử.
  - Danh sách 4 kỹ năng đệ tử: Dropdown chọn đổi kỹ năng bất kỳ (Kame, Masenko, Antomic, Kaioken, Biến khỉ, Thái Dương Hạ San...).
  - Xem đồ đệ tử đang mặc.

- [ ] **Step 6: Xây dựng Tab 5: Kỹ năng & Nhiệm vụ (Skills & Quests)**
- Danh sách 8 kỹ năng nhân vật: Tên skill, Icon, Cấp độ hiện tại. Hỗ trợ tăng max cấp hoặc đổi chiêu thức.
- Thông tin nhiệm vụ đang làm: Tên nhiệm vụ từ `task_main_template`, chi tiết nhiệm vụ.
- Dropdown "Chuyển sang nhiệm vụ..." cho phép nhảy cóc qua bất kỳ nhiệm vụ nào trong game.

- [ ] **Step 7: Xây dựng Tab 6: Tài khoản liên kết (`account`)**
- Xem và đổi mật khẩu tài khoản người chơi.
- Cộng/trừ tiền VND, Thỏi vàng, Tổng nạp.
- Khóa / Mở khóa tài khoản, Kích hoạt thành viên.

---

### Task 3: Xây dựng API Xử Lý Hành Động Người Chơi (`player_actions.php`)

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\api\player_actions.php`

- [ ] **Step 1: Xử lý action `gift_item` (Tặng đồ vào túi/rương)**
Nhận `player_id`, `item_id`, `quantity`, `options`, `target` ('bag' hoặc 'box').
Tìm ô trống (`template_id = -1`), tạo chuỗi JSON món đồ và cập nhật vào CSDL.

- [ ] **Step 2: Xử lý action `delete_item` (Xóa đồ khỏi túi/rương/body)**
Đặt ô `slot_index` về `[-1, 0, "[]", timestamp]` và lưu CSDL.

- [ ] **Step 3: Xử lý action `change_task` (Chuyển nhiệm vụ)**
Cập nhật `data_task = [target_task_id, 0, 0, 0]`.

- [ ] **Step 4: Xử lý action `update_pet` (Buff đệ tử & đổi skill đệ)**
Cập nhật mảng `pet[1]` (chỉ số) và `pet[3]` (kỹ năng đệ tử).

- [ ] **Step 5: Xử lý action `warp_home` (Đưa nhân vật về làng)**
Cập nhật `data_location` về toạ độ làng theo hành tinh: Trái Đất (Map 21, X:300, Y:336), Namếc (Map 22, X:300, Y:336), Xayda (Map 23, X:300, Y:336).

- [ ] **Step 6: Xử lý action `upgrade_magic_tree` (Nâng cây đậu thần lên Cấp 10)**
Cập nhật `data_magic_tree = [10, 20, 0, timestamp, timestamp]`.

---

### Task 4: Nâng Cấp Trang Danh Sách Người Chơi (`admin/players.php`)

**Files:**
- Modify: `c:\xampp\htdocs\webgoc2026\admin\players.php`

- [ ] **Step 1: Bổ sung liên kết trực tiếp vào `player_detail.php?id={id}`**
Thêm nút nổi bật "🔍 Xem Chi Tiết & Chỉnh Sửa" trên từng dòng nhân vật để chuyển thẳng vào trung tâm quản trị toàn diện.
- [ ] **Step 2: Thêm hiển thị Vị trí Map và Đệ tử ngay trên bảng danh sách**

---

### Task 5: Kiểm Thử Toàn Diện & Đảm Bảo Responsive UI/UX

- [ ] **Step 1: Kiểm thử giải mã dữ liệu**
Chạy test script với nhân vật `admin` (ID 1367) và `sadboiz` (ID 1368) để đảm bảo toàn bộ túi đồ, rương đồ, đệ tử và kỹ năng giải mã 100% không bị lỗi JSON.

- [ ] **Step 2: Kiểm thử tặng đồ**
Tặng thử 1 vật phẩm (VD: 50 Thỏi vàng) vào túi đồ của nhân vật -> Kiểm tra mảng `items_bag` trong CSDL được cập nhật đúng chuẩn format của Game Server.

- [ ] **Step 3: Kiểm thử responsive màn hình điện thoại & máy tính**
Kiểm tra tab layout, RPG doll và grid túi đồ trên màn hình 375px (Mobile) đảm bảo không tràn khung, cuộn mượt mà.

---

### Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-10-06-comprehensive-player-account-manager.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration.
**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints.

**Which approach?**
