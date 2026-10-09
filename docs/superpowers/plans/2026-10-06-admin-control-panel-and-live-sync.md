# Admin Control Panel & Live Sync Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây dựng trọn vẹn hệ thống Web Admin Control Panel bên trong `webgoc2026` tích hợp hiển thị Icon vật phẩm, giao diện Responsive toàn diện trên mọi thiết bị và hệ thống Live Sync (Hot Reload) để cập nhật dữ liệu vào game tức thì mà không cần khởi động lại máy chủ.

**Architecture:** Web Control Panel được xây dựng theo kiến trúc module hóa an toàn trong `webgoc2026/admin/` với Auth Guard 2 lớp, CSRF token và Prepared Statements. Hệ thống nạp tài nguyên Icon từ bộ icon res có sẵn và kết nối trực tiếp với Game Server Java qua cơ chế Hàng đợi Lệnh Thời Gian Thực (`server_command_queue`) để thực thi Live Reload trong 1-3 giây.

**Tech Stack:** PHP 8.2, MySQL / MariaDB (PDO & MySQLi prepared), HTML5 / CSS3 (Vanilla Responsive Grid & Flexbox, Mobile Drawer), Vanilla JavaScript / Fetch API.

---

### File Structure Map

```text
webgoc2026/
├── admin/
│   ├── auth_guard.php              # Middleware kiểm tra quyền admin và chặn truy cập trái phép
│   ├── includes/
│   │   ├── header.php             # Header, navbar, mobile toggle button & CSS tokens
│   │   ├── sidebar.php            # Sidebar menu 8 phân hệ (responsive drawer trên mobile)
│   │   ├── footer.php             # Footer & scripts
│   │   ├── icon_helper.php        # Hàm helper lấy icon vật phẩm từ icon_id
│   │   └── live_sync_helper.php   # Hàm phát lệnh Live Reload vào server_command_queue
│   ├── assets/
│   │   ├── css/
│   │   │   └── admin.css          # Dark modern theme, CSS variables, mobile media queries
│   │   ├── js/
│   │   │   └── admin.js           # Xử lý mobile menu, modal, live sync AJAX toast
│   │   └── icons/                 # Thư mục lưu trữ/đồng bộ icon res cho web hiển thị
│   ├── index.php                  # Dashboard tổng quan & Điều khiển Live Sync
│   ├── accounts.php               # Phân hệ 1: Quản lý Tài khoản (Ban, Active, VND, Pass)
│   ├── players.php                # Phân hệ 2: Quản lý Nhân vật (data_point, data_inventory, Đồ, Đệ)
│   ├── giftcodes.php              # Phân hệ 3: Giftcode Studio (Chọn item icon trực quan, Live Sync)
│   ├── mobs.php                   # Phân hệ 4: Quản lý Quái vật & EXP Server (mob_template, rates)
│   ├── shops.php                  # Phân hệ 5: Quản lý Shop NPC & Chợ Ký Gửi (item_shop, shop_ky_gui)
│   ├── gameplay.php               # Phân hệ 6: Quản lý Nhiệm vụ, Kỹ năng, Danh hiệu & Pet
│   ├── transactions.php           # Phân hệ 7: Doanh thu, Thẻ cào & Ngân hàng
│   ├── website_settings.php       # Phân hệ 8: Cấu hình Website & Thông báo chạy chữ in-game
│   └── api/
│       ├── live_sync.php          # API nhận yêu cầu phát lệnh reload và kiểm tra trạng thái
│       └── search_items.php       # API tìm kiếm item_template kèm icon cho modal chọn đồ
```

---

### Task 1: Thiết lập Bảng Hàng Đợi Lệnh Live Sync & Đồng bộ Thư Mục Icon

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\includes\icon_helper.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\includes\live_sync_helper.php`

- [ ] **Step 1: Tạo bảng `server_command_queue` trong database `team2026`**
Tạo bảng chứa hàng đợi lệnh để PHP đẩy lệnh vào và Game Server Java quét thực thi.
```sql
CREATE TABLE IF NOT EXISTS `server_command_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `command_type` VARCHAR(50) NOT NULL,
  `payload` TEXT DEFAULT NULL,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `created_by` VARCHAR(50) DEFAULT 'admin',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `executed_at` TIMESTAMP NULL DEFAULT NULL,
  `result_message` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Khởi tạo thư mục `admin/assets/icons` và liên kết tài nguyên Icon**
Tạo helper `icon_helper.php` hỗ trợ đọc icon từ bộ resource icon x1/x2 có sẵn (`C:\Users\Admin\Downloads\nroteam\Teamobi2026\SRC\data\icon\x1` hoặc `webgoc2026/images/avatar/`) và phục vụ hiển thị lên thẻ `<img>`.
```php
<?php
// admin/includes/icon_helper.php
function get_item_icon_url($icon_id) {
    $icon_id = (int)$icon_id;
    if ($icon_id < 0) {
        return '/images/favicon.png';
    }
    // Đường dẫn cache web
    $web_icon_path = __DIR__ . '/../assets/icons/' . $icon_id . '.png';
    if (file_exists($web_icon_path)) {
        return '/webgoc2026/admin/assets/icons/' . $icon_id . '.png';
    }
    // Nếu chưa có, copy từ SRC data/icon/x1 sang assets/icons
    $src_icon_path = 'C:/Users/Admin/Downloads/nroteam/Teamobi2026/SRC/data/icon/x1/' . $icon_id . '.png';
    if (file_exists($src_icon_path)) {
        @copy($src_icon_path, $web_icon_path);
        return '/webgoc2026/admin/assets/icons/' . $icon_id . '.png';
    }
    return '/webgoc2026/images/favicon.png';
}
```

- [ ] **Step 3: Xây dựng `live_sync_helper.php` để đẩy lệnh thời gian thực**
Hàm `dispatch_server_command($type, $payload)` dùng PDO/MySQLi an toàn để ghi lệnh vào `server_command_queue`.
```php
<?php
// admin/includes/live_sync_helper.php
require_once __DIR__ . '/../../connect.php';

function dispatch_server_command($command_type, $payload = [], $creator = 'admin') {
    global $conn;
    $payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO server_command_queue (command_type, payload, status, created_by) VALUES (?, ?, 'pending', ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $command_type, $payload_json, $creator);
        $res = $stmt->execute();
        $insert_id = $stmt->insert_id;
        $stmt->close();
        return $insert_id;
    }
    return false;
}
```

- [ ] **Step 4: Kiểm tra kết nối và tạo bảng qua PHP CLI**
Chạy script kiểm tra xem bảng `server_command_queue` đã được tạo và helper trả về URL icon hợp lệ.

---

### Task 2: Xây dựng Bộ khung Bảo Mật (Auth Guard) & Giao Diện Responsive Dark UI

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\auth_guard.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\assets\css\admin.css`
- Create: `c:\xampp\htdocs\webgoc2026\admin\assets\js\admin.js`
- Create: `c:\xampp\htdocs\webgoc2026\admin\includes\header.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\includes\sidebar.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\includes\footer.php`

- [ ] **Step 1: Xây dựng `auth_guard.php` kiểm soát quyền truy cập nghiêm ngặt**
Chỉ cho phép `$_SESSION['is_admin'] == 1` VÀ xác minh lại trực tiếp từ CSDL `(is_admin = 1 OR admin = 1)`. Chặn triệt để thành viên thường (HTTP 403 Forbidden).
```php
<?php
// admin/auth_guard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../connect.php';

$is_admin = false;
$logged_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if ($logged_user_id && isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT id, username, is_admin, admin, ban FROM account WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $logged_user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user && ($user['is_admin'] == 1 || $user['admin'] == 1) && $user['ban'] == 0) {
            $is_admin = true;
            $_SESSION['is_admin'] = 1;
            $_SESSION['admin'] = 1;
        }
    }
}

if (!$is_admin) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 Forbidden</title><style>body{background:#0f172a;color:#f87171;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;flex-direction:column}a{color:#38bdf8;margin-top:20px;text-decoration:none;border:1px solid #38bdf8;padding:8px 16px;border-radius:6px}</style></head><body><h1>403 Forbidden</h1><p>Bạn không có quyền truy cập trang quản trị Admin Control Panel.</p><a href="/webgoc2026/">Quay lại trang chủ</a></body></html>';
    exit();
}
```

- [ ] **Step 2: Xây dựng `admin.css` Responsive Full-Device**
Thiết kế Dark Theme hiện đại (Slate/Emerald/Amber), Mobile Drawer Sidebar với hamburger menu, Bảng dữ liệu tự động co giãn `overflow-x: auto`, Card chỉ số thống kê, Nút bấm thân thiện cảm ứng Touch Target ≥ 44px.

- [ ] **Step 3: Xây dựng `sidebar.php`, `header.php`, `footer.php`**
Cấu trúc sidebar phân nhóm 8 phân hệ, đánh dấu tab đang chọn (`active`), nút chuyển đổi menu trên Mobile (Hamburger bar), nút "Live Reload Tất Cả" nhanh trên Header.

- [ ] **Step 4: Kiểm tra giao diện trên cả Desktop (1920px) và Mobile viewport (375px)**

---

### Task 3: Phân hệ Dashboard Tổng Quan & Quản Lý Live Sync Realtime

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\index.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\api\live_sync.php`

- [ ] **Step 1: Xây dựng Dashboard `index.php`**
Hiển thị các chỉ số tổng quan từ Database:
- Tổng số tài khoản (`account`).
- Tổng số nhân vật (`player`).
- Tổng doanh thu nạp thẻ (`napthe`) và chuyển khoản (`bank_transfers`).
- Số lượng giftcode đang kích hoạt.
- **Bảng điều khiển Live Sync trung tâm**: Nút bấm phát các lệnh Hot Reload ngay lập tức (Reload Giftcode, Reload Mob, Reload Shop, Broadcast Notice).
- Danh sách 10 lệnh Live Sync gần nhất trong `server_command_queue` kèm trạng thái (Pending, Completed).

- [ ] **Step 2: Xây dựng API `api/live_sync.php`**
Nhận request AJAX từ giao diện admin, chèn lệnh vào `server_command_queue`, kiểm tra trạng thái lệnh và trả về JSON cho client hiển thị Toast thành công/thất bại.

- [ ] **Step 3: Tích hợp Toast thông báo realtime trong `admin.js`**

---

### Task 4: Phân hệ 1 - Quản Lý Tài Khoản (Account Management)

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\accounts.php`

- [ ] **Step 1: Giao diện tra cứu danh sách tài khoản**
Bảng Responsive hỗ trợ phân trang, tìm kiếm theo `username`, `id`, lọc theo trạng thái (`ban`, `active`, `is_admin`).

- [ ] **Step 2: Modal hành động nhanh**
- Khóa / Mở khóa tài khoản (`UPDATE account SET ban = ? WHERE id = ?`).
- Kích hoạt thành viên (`UPDATE account SET active = ? WHERE id = ?`).
- Cộng / Trừ VND, Tổng nạp, Thỏi vàng, Vàng, Lượt quay.
- Đổi mật khẩu tài khoản người chơi.
- Cấp quyền / Thu hồi quyền Admin (`is_admin = 1/0`).

- [ ] **Step 3: Xử lý an toàn với CSRF & Prepared Statements**

---

### Task 5: Phân hệ 2 - Quản Lý Nhân Vật & Chỉ Số (Player Editor)

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\players.php`

- [ ] **Step 1: Giao diện tìm kiếm nhân vật**
Tìm kiếm theo Tên nhân vật (`player.name`) hoặc `account_id`. Hiển thị thông tin hành tinh (Trái Đất, Namếc, Xayda), bang hội (`clan_id`).

- [ ] **Step 2: Bộ giải mã & Cập nhật `data_point`**
Hiển thị form trực quan với các chỉ số:
- HP gốc (`data_point[5]`), KI gốc (`data_point[6]`), Sức đánh gốc (`data_point[7]`), Giáp gốc (`data_point[8]`), Chí mạng gốc (`data_point[9]`).
- Sức mạnh (`data_point[1]`), Tiềm năng (`data_point[2]`), Giới hạn sức mạnh (`data_point[0]`).
Khi Admin bấm lưu, tự động format lại mảng JSON chuẩn và ghi vào cột `data_point`.

- [ ] **Step 3: Bộ giải mã & Cập nhật `data_inventory` & Soi Hòm Đồ**
- Vàng (`data_inventory[0]`), Ngọc xanh (`data_inventory[1]`), Hồng ngọc (`data_inventory[2]`).
- Giải mã `items_body`, `items_bag`, `items_box` và render danh sách vật phẩm kèm **Icon game trực quan** lấy từ `get_item_icon_url($icon_id)`.

- [ ] **Step 4: Tích hợp nút Live Kick / Sync**
Cảnh báo nếu nhân vật đang online và hỗ trợ phát lệnh `KICK_PLAYER` vào hàng đợi để người chơi nhận chỉ số mới mà không bị game server ghi đè khi thoát.

---

### Task 6: Phân hệ 3 - Giftcode Studio & Bộ Chọn Vật Phẩm Có Icon

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\giftcodes.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\api\search_items.php`

- [ ] **Step 1: Giao diện danh sách Giftcode**
Bảng hiển thị mã code, số lượt còn lại (`count_left`), ngày tạo, ngày hết hạn. Có nút Xóa, Gia hạn, Nhân đôi số lượt.

- [ ] **Step 2: Modal tạo Giftcode thông minh với Icon Res**
- Bộ tìm kiếm vật phẩm từ `item_template`: Khi gõ tên đồ (ví dụ: "Thỏi vàng"), API trả về danh sách kèm **Icon hình ảnh vật phẩm thực tế**.
- Admin chọn vật phẩm -> Nhập số lượng -> Chọn Option (chỉ số) từ `item_option_template`.
- Nhập số lượt (nhập -1 nếu vô hạn) và ngày hết hạn.
- Hệ thống tự encode chuỗi JSON `[{"id":...,"quantity":...,"options":[...]}]` và lưu vào bảng `giftcode`.

- [ ] **Step 3: Kích hoạt Live Reload Giftcode tức thì**
Sau khi chèn vào bảng `giftcode`, hệ thống tự động bắn lệnh `RELOAD_GIFTCODE` vào `server_command_queue`. Người chơi trong game nhập được ngay lập tức.

---

### Task 7: Phân hệ 4 & 5 - Quản Lý Quái Vật, EXP Server, Shop NPC & Chợ Ký Gửi

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\mobs.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\shops.php`

- [ ] **Step 1: Quản lý Quái vật & EXP Server (`mobs.php`)**
- Xem và sửa bảng `mob_template`: Máu quái (`hp`), % sát thương (`percent_dame`), % tiềm năng rơi (`percent_tiem_nang`), tốc độ (`speed`).
- Công cụ điều chỉnh hàng loạt: Nút "Tăng/Giảm HP quái all server theo %" và "Nhân hệ số tiềm năng rơi".
- Đọc/sửa hệ số EXP server (`server.expserver` trong `Config.properties`).
- Nút "Live Reload Quái & Rate" bắn lệnh `RELOAD_MOB` và `SET_EXP_RATE` vào game server.

- [ ] **Step 2: Quản lý Shop NPC & Chợ Ký Gửi (`shops.php`)**
- Hiển thị danh sách các Shop NPC (`shop`, `tab_shop`, `item_shop`).
- Hiển thị hình ảnh Icon của vật phẩm bán trong shop (`get_item_icon_url`).
- Chỉnh sửa giá bán (Vàng, Ngọc, Hồng ngọc) và option của trang bị trong shop.
- Quản lý các vật phẩm đang ký gửi trong `shop_ky_gui`.
- Nút "Live Reload Shop" phát lệnh `RELOAD_SHOP` vào game.

---

### Task 8: Phân hệ 6, 7 & 8 - Gameplay, Doanh Thu, Thông Báo & Cấu Hình Web

**Files:**
- Create: `c:\xampp\htdocs\webgoc2026\admin\gameplay.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\transactions.php`
- Create: `c:\xampp\htdocs\webgoc2026\admin\website_settings.php`

- [ ] **Step 1: Quản lý Gameplay (`gameplay.php`)**
Tra cứu và quản lý nhiệm vụ (`task_*`), kỹ năng (`skill_template`), danh hiệu (`data_badges`), pet rồng (`dragon_pet_config`), thẻ sưu tập (`radar`).

- [ ] **Step 2: Quản lý Doanh thu & Thẻ cào (`transactions.php`)**
- Bảng lịch sử nạp thẻ cào (`napthe`) và chuyển khoản SePay/Momo (`bank_transfers`).
- Lọc theo ngày, trạng thái thẻ (Thành công, Thất bại, Chờ xử lý).
- Nút duyệt nạp thẻ thủ công nếu phát hiện thẻ đúng mà hệ thống bị nghẽn.

- [ ] **Step 3: Quản lý Thông báo In-Game & Cấu hình Web (`website_settings.php`)**
- Đổi thông báo máy chủ trong bảng `notify` (nút "Bắn thông báo tức thì" phát lệnh `SEND_NOTICE` chạy chữ in-game ngay lập tức).
- Cấu hình thông tin web trong bảng `settings` (Tên máy chủ, link tải Android/PC/iOS, Fanpage, Zalo).

---

### Task 9: Kiểm Thử Toàn Diện & Tối Ưu UI/UX Responsive

- [ ] **Step 1: Kiểm tra phân quyền & bảo mật**
- Đăng nhập tài khoản thường và truy cập `/webgoc2026/admin/` -> Xác nhận hiển thị 403 Forbidden.
- Đăng nhập tài khoản admin (`admin` / id `3728`) -> Xác nhận vào thẳng Control Panel.

- [ ] **Step 2: Kiểm tra Responsive UI/UX trên nhiều kích thước màn hình**
- Mobile (375px - 414px): Sidebar thu gọn thành Drawer, các bảng cuộn ngang mượt mà, modal full width.
- Tablet (768px - 1024px): Bố cục Grid 2 cột tự cân đối.
- Desktop (1200px+): Giao diện rộng rãi, sidebar cố định.

- [ ] **Step 3: Kiểm tra hiển thị Icon vật phẩm**
- Mở Giftcode Studio hoặc Danh sách Shop NPC -> Xác nhận các icon hình ảnh vật phẩm hiển thị sắc nét từ thư mục res.

- [ ] **Step 4: Kiểm tra chu trình Live Sync**
- Tạo 1 giftcode mới -> Xác nhận lệnh được ghi vào `server_command_queue` với status `pending`.
- Kiểm tra toast thông báo phản hồi trên Web CP.

---

### Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-10-06-admin-control-panel-and-live-sync.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration.
**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints.

**Which approach?**
