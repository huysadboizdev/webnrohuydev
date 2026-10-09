# Tích Hợp Live Sync Engine Vào Game Server Teamobi2026 & Web Control Panel

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây dựng luồng đồng bộ trực tiếp (Live Sync Thread) giữa Web Control Panel `webgoc2026` và Game Server Java `Teamobi2026` qua bảng MySQL `server_command_queue`. Khi Admin trên web thao tác tặng đồ, sửa chỉ số, warp map hay kick, người chơi đang online trong game sẽ nhận được ngay lập tức vào túi đồ/chỉ số mà không cần thoát game hoặc khởi động lại server.

**Architecture:**
- Game Server Java khởi chạy `LiveSyncService` (ScheduledExecutorService) quét bảng `server_command_queue` mỗi 1.5 giây.
- Xử lý các command: `GIFT_ITEM`, `UPDATE_STATS`, `KICK_PLAYER`, `WARP_HOME`, `RELOAD_CONFIG`.
- Đối với nhân vật Online: Áp dụng trực tiếp vào RAM (`Player`, `InventoryService`, `Service.gI().point`), gửi packet cập nhật ngay trên màn hình game và phát thông báo popup. Sau đó gọi `PlayerDAO.updatePlayer` để đồng bộ CSDL.
- Đối với nhân vật Offline: Dữ liệu đã được PHP lưu vào MySQL; Server chỉ cập nhật trạng thái lệnh thành `success`.
- Tuân thủ nghiêm ngặt quy tắc cập nhật JAR trong `Teamobi2026/AGENTS.md`: Backup `20.jar` cũ có timestamp vào `SRC/jar-backup`, đối chiếu SHA-256, compile class mới, tạo JAR và xác minh runtime.

**Tech Stack:** Java 17/21 (`SRC/src/nro/`), MySQL 8.x (`team2026`), PHP 8.x (`webgoc2026/admin/api/`).

---

### Task 1: Thiết kế & Tạo LiveSyncService trong Java Game Server

**Files:**
- Create: `c:\Users\Admin\Downloads\nroteam\Teamobi2026\SRC\src\nro\models\services\LiveSyncService.java`
- Modify: `c:\Users\Admin\Downloads\nroteam\Teamobi2026\SRC\src\nro\models\server\ServerManager.java`

- [ ] **Step 1: Viết class `LiveSyncService.java`**
Xử lý kết nối JDBC `LocalManager.gI().getConnection()`, đọc các hàng `status = 'pending'` từ `server_command_queue`, thực thi các hành động với người chơi online (tìm qua `Client.gI().getPlayer(playerId)`).

- [ ] **Step 2: Đăng ký `LiveSyncService.gI().start()` trong `ServerManager.java`**
Khởi động scheduler trong phương thức `run()` của `ServerManager`.

- [ ] **Step 3: Biên dịch thử nghiệm class `LiveSyncService` với `javac`**
Kiểm tra cú pháp và sự phụ thuộc class:
```powershell
& 'C:\Program Files\Java\jdk-21.0.12\bin\javac.exe' --release 17 -encoding UTF-8 -cp 'SRC/20.jar;SRC/lib/*' -d 'SRC/build/classes' SRC/src/nro/models/services/LiveSyncService.java
```

---

### Task 2: Cập Nhật API Web `player_actions.php` Dispatch Đầy Đủ Command

**Files:**
- Modify: `c:\xampp\htdocs\webgoc2026\admin\api\player_actions.php`
- Modify: `c:\xampp\htdocs\webgoc2026\admin\includes\live_sync_helper.php`

- [ ] **Step 1: Mở rộng `player_actions.php` để dispatch lệnh `GIFT_ITEM` và `UPDATE_STATS`**
Khi Admin bấm tặng đồ hoặc sửa chỉ số:
1. Ghi vào MySQL (để nếu nhân vật offline thì lưu sẵn).
2. Đồng thời dispatch `GIFT_ITEM` hoặc `UPDATE_STATS` vào `server_command_queue` (để nếu nhân vật online thì Java server nhận và nạp đồ ngay lập tức).

- [ ] **Step 2: Kiểm thử dispatch lệnh từ PHP CLI vào MySQL**
Chạy test thử phát lệnh và đối soát hàng `server_command_queue`.

---

### Task 3: Quy Trình Đóng Gói JAR Chuẩn AGENTS.md & Triển Khai

**Files:**
- Backup: `c:\Users\Admin\Downloads\nroteam\Teamobi2026\SRC\jar-backup\20-before-livesync-<timestamp>.jar`
- Deploy: `c:\Users\Admin\Downloads\nroteam\Teamobi2026\SRC\20.jar`

- [ ] **Step 1: Dừng Game Server đang chạy (PID 8484)**
Thực hiện tắt server an toàn để lưu dữ liệu.

- [ ] **Step 2: Backup `20.jar` cũ sang `SRC/jar-backup/` có timestamp và ghi nhận SHA-256**
```powershell
$hash = (Get-FileHash 'SRC/20.jar' -Algorithm SHA256).Hash
Copy-Item 'SRC/20.jar' "SRC/jar-backup/20-before-livesync-$(Get-Date -Format 'yyyyMMdd-HHmmss').jar"
```

- [ ] **Step 3: Compile toàn bộ code mới và cập nhật `20.jar`**
Thêm class đã compile vào `20.jar` bằng `jar uf 20.jar -C SRC/build/classes .`.

- [ ] **Step 4: Khởi động lại Server và Kiểm tra Logs**
Chạy `java -server -Dfile.encoding=UTF-8 -jar 20.jar` và kiểm tra luồng LiveSync hoạt động.

---

### Task 4: Kiểm Thử Nghiệm Thu Trực Tiếp (End-to-End Verification)

- [ ] **Step 1: Kiểm thử Live Kick**
Admin bấm "Live Kick" trên web -> Xác nhận `server_command_queue` chuyển từ `pending` sang `success`.

- [ ] **Step 2: Kiểm thử Live Gift Item**
Tặng 1 Bông tai Porata (#454) x1 từ web -> Xác nhận `server_command_queue` xử lý thành công, vật phẩm xuất hiện trong game.
