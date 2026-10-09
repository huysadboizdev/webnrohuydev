# Kế hoạch chi tiết: Nâng cấp Toàn diện UI/UX & Cân chỉnh Chỉ số Boss Live

> **Dành cho Agentic Workers:** REQUIRED SUB-SKILL: Sử dụng `superpowers:subagent-driven-development` hoặc `superpowers:executing-plans` để thực thi kế hoạch theo từng nhiệm vụ. Các bước sử dụng cú pháp checkbox (`- [ ]`).

**Mục tiêu:** Tái thiết kế toàn diện UI/UX trang Quản lý & Cấu hình Boss (`admin/bosses.php`), bổ sung icon avatar res của Boss, hiển thị trực quan thông số mặc định từ SQL + Source code Java (HP, Sức đánh, Map, Giáp, Crit) ngay cạnh ô nhập liệu để người quản trị dễ dàng căn chỉnh, đồng thời đảm bảo quy trình lưu và kích hoạt Live Sync trực tiếp vào game không cần chạy lại server.

**Kiến trúc:** 
1. **Catalog & Resource Resolver:** Bổ sung ánh xạ Head Avatar ID và thông số gốc mặc định cho toàn bộ danh mục boss, tích hợp helper lấy ảnh icon boss từ `images/avatar/` và `data/icon/`.
2. **Giao diện Modern Admin (Glassmorphism Dark Theme):** Thay thế bảng dữ liệu đơn điệu và modal thô sơ bằng giao diện cao cấp: avatar boss sắc nét, badge phân loại rực rỡ, modal dialog responsive chia bố cục khoa học, có tab trực quan và nút hỗ trợ cân chỉnh nhanh (+20%, +50%, x2, dùng số gốc).
3. **So sánh chỉ số Gốc vs Mới:** Hiển thị song song chỉ số mặc định (nguồn SQL/SRC) với ô nhập chỉ số tùy chỉnh. Placeholder và helper text hiển thị chính xác con số gốc.
4. **Live Sync Bridge & Diagnostics:** Sửa triệt để các lỗi đường dẫn tương đối (404 API search), lỗi validate client rơi đồ, lỗi che khuất thông báo lỗi và tái sử dụng request ID; tự động xếp hàng lệnh `RELOAD_BOSS` và phản hồi trạng thái ACK thời gian thực qua Toast.

**Tech Stack:** PHP 8.2 (Vanilla OOP/Procedural), Vanilla JavaScript (ES6+), Vanilla CSS (Custom Design System, Glassmorphism, Responsive Grid/Flexbox), MySQL 8.0, WebSockets/Polling Live Sync Bridge.

---

### Danh sách tệp can thiệp:
- **Tạo mới:** `admin/includes/boss_avatar_map.json` (Ánh xạ Head ID & Avatar của 144 loại boss)
- **Tạo mới:** `admin/assets/css/boss-admin.css` (Hệ thống style cao cấp riêng cho trang Boss Admin)
- **Chỉnh sửa:** `admin/includes/icon_helper.php` (Bổ sung helper `get_boss_avatar_url($boss)`)
- **Chỉnh sửa:** `admin/bosses.php` (Cải tiến toàn diện markup HTML, bảng danh sách boss, thẻ thống kê và Modal Dialog)
- **Chỉnh sửa:** `admin/assets/js/boss-admin.js` (Nâng cấp logic hiển thị, so sánh chỉ số gốc, nút tăng giảm nhanh, validate drop, fix URL API)
- **Kiểm thử:** `tests/admin_boss_admin_test.php`, `tests/admin_boss_admin_ui_test.js`

---

### Task 1: Xây dựng Bộ Ánh xạ Avatar Boss & Helper Lấy Icon Resource

**Mục đích:** Cung cấp icon/avatar chuẩn xác cho từng boss từ dữ liệu game (`images/avatar/{head}.png` hoặc fallback theo kind) để hiển thị trên bảng và trong modal.

**Files:**
- Tạo mới: `c:\xampp\htdocs\webgoc2026\admin\includes\boss_avatar_map.json`
- Chỉnh sửa: `c:\xampp\htdocs\webgoc2026\admin\includes\icon_helper.php`
- Kiểm thử: `c:\xampp\htdocs\webgoc2026\tests\admin_icon_test.php`

- [x] **Bước 1: Trích xuất danh sách Head ID từ Source Code và tạo `admin/includes/boss_avatar_map.json`**
  Quét toàn bộ catalog boss và ánh xạ key của từng boss sang `head_id` tương ứng trong game (ví dụ: Android 13 = 252, Android 14 = 246, Dr.Kôrê = 255, Broly = 291...).

- [x] **Bước 2: Viết hàm `get_boss_avatar_url($bossKey, $headId)` trong `icon_helper.php`**
  Hàm kiểm tra sự tồn tại của file icon theo thứ tự ưu tiên:
  1. `webgoc2026/images/avatar/{head_id}.png`
  2. `Teamobi2026/SRC/data/icon/x1/{head_id}.png` hoặc `x2/{head_id}.png`
  3. Fallback theo nhóm boss (Nhiệm vụ, Broly, Super, Tiểu đội sát thủ...) hoặc icon mặc định `favicon-32x32.png`.

- [x] **Bước 3: Chạy script kiểm tra để xác nhận >95% boss có avatar hiển thị hợp lệ**
  Chạy script kiểm tra trong terminal, đảm bảo không có lỗi thiếu file hoặc đường dẫn sai.

---

### Task 2: Thiết kế Hệ thống UI/UX CSS Mới (`admin/assets/css/boss-admin.css`)

**Mục đích:** Xóa bỏ giao diện cũ đơn điệu; thay bằng giao diện quản trị viên chuẩn game hiện đại, sang trọng, màu sắc hài hòa, hỗ trợ mobile & desktop.

**Files:**
- Tạo mới: `c:\xampp\htdocs\webgoc2026\admin\assets\css\boss-admin.css`
- Chỉnh sửa: `c:\xampp\htdocs\webgoc2026\admin\bosses.php` (nhúng tệp css mới)

- [x] **Bước 1: Thiết kế giao diện Thẻ Thống kê (Stat Cards) & Bộ lọc thông minh**
  - Thẻ đếm số lượng với icon rực rỡ, gradient viền neon (Tổng loại, Nhiệm vụ, Giới hạn dame, Đã tùy chỉnh).
  - Thanh tìm kiếm dạng Search Box có icon kính lúp, dropdown chọn nhóm có badge số lượng.

- [x] **Bước 2: Thiết kế Bảng Boss (Data Table) Hiện đại**
  - Cột Boss hiển thị Avatar tròn có viền phát sáng + Tên boss in đậm + Key mã hóa monospace + Badge loại boss.
  - Cột Giai đoạn & Map hiển thị thẻ chip gọn gàng.
  - Cột Chỉ số hiển thị nổi bật số HP & Dame, badge phân biệt "Mặc định" (xanh dương dịu) vs "Tùy chỉnh" (xanh lá rực rỡ).
  - Cột Giáp & Chí mạng hiển thị rõ ràng nếu có chỉnh sửa.
  - Cột Đang sống với chấm Pulse Animation xanh lá khi có heartbeat.
  - Nút "Sửa" phong cách Gradient Button nổi bật.

- [x] **Bước 3: Thiết kế lại Modal Dialog (Card Floating Modal)**
  - Backdrop làm mờ có chiều sâu (`backdrop-filter: blur(8px)`).
  - Header modal nổi bật với Avatar boss lớn, tên boss, class name và nút Đóng dạng icon '✕'.
  - Tab navigation dạng Segmented Control bo tròn hiện đại: `📋 ① Thông tin gốc`, `⚡ ② Cân chỉnh chỉ số`, `🎁 ③ Rơi đồ (Drops)`.
  - Khung so sánh chỉ số: Hiển thị hộp số liệu gốc (Base Stats) màu cyan/blue nhạt làm đối trọng so sánh với hộp số liệu mới.
  - Các nút tác vụ nhanh (Quick Adjust Buttons): `Dùng số gốc`, `+20%`, `+50%`, `x2`, `Xóa về gốc`.
  - Footer modal với các nút hành động phân cấp màu sắc rõ rệt:
    - Nút chính: **⚡ Lưu & Nạp Live ngay** (Gradient Primary).
    - Nút phụ: **Đặt lại mặc định** (Outline Danger).
    - Nút khôi phục: **Khôi phục bản ghi trước** (Outline Secondary).

---

### Task 3: Hiển thị Đầy đủ Chỉ số Mặc định (SQL + Source Code) và Cân Chỉnh Nhanh trong Modal

**Mục đích:** Giải quyết triệt để yêu cầu cốt lõi của người dùng: *Khi mở modal chỉnh sửa, phải thấy ngay chỉ số mặc định từ SQL + Source code Java để tiện căn chỉnh, thay vì chỉ có ô trống khó đoán*.

**Files:**
- Chỉnh sửa: `c:\xampp\htdocs\webgoc2026\admin\bosses.php`
- Chỉnh sửa: `c:\xampp\htdocs\webgoc2026\admin\assets\js\boss-admin.js`

- [x] **Bước 1: Cấu trúc lại Tab ② Chỉ số trong Modal HTML (`admin/bosses.php`)**
  - Mỗi nhóm chỉ số (HP/Dame, Giáp/Crit, Trần Dame) chia thành 2 phần:
    1. **Thanh thông số gốc:** Hiển thị rõ số liệu mặc định (Ví dụ: `HP gốc: 3.000.000 (3 Triệu) | Sức đánh gốc: 12.055`).
    2. **Hàng nhập liệu & Công cụ căn chỉnh:**
       - Ô nhập liệu có placeholder hiển thị số gốc: `placeholder="Gốc: 3.000.000"`.
       - Thanh nút tiện ích ngay dưới ô nhập:
         - `[⟲ Gốc]`: Điền lại số gốc vào ô nhập.
         - `[+20%]`: Tăng 20% so với số gốc.
         - `[+50%]`: Tăng 50% so với số gốc.
         - `[x2]`: Nhân đôi số gốc.
         - `[x5]`: Nhân 5 lần số gốc.
         - `[Xóa rỗng]`: Để trống để lấy mặc định hệ thống.

- [x] **Bước 2: Cập nhật JavaScript `boss-admin.js` để render động chỉ số gốc theo từng giai đoạn (Phase)**
  - Khi người dùng chọn Giai đoạn (Phase 0, Phase 1...), JS đọc thông số gốc từ `catalog.phases[phase]` và cập nhật tức thì:
    - Giá trị gốc: `hp`, `damage`, `maps`.
    - Trạng thái công thức gốc / động: ghi chú rõ nếu là boss nhân bản hoặc boss tính dame theo % HP mục tiêu.
    - Cập nhật placeholder và sự kiện click cho các nút tăng giảm nhanh.

- [x] **Bước 3: Bổ sung chỉ số Giáp (`defense`) & Chí mạng (`critical`) vào logic hiển thị và mặc định của JS**
  - Thêm `defense: null`, `critical: null` vào `defaults`.
  - Cập nhật điều kiện nhận biết tùy chỉnh `isCustom` đầy đủ: HP, Dame, Giáp, Crit, Trần dame, hoặc Drop rules.

---

### Task 4: Sửa Lỗi Kỹ thuật, Chuẩn hóa Live Sync & Tối ưu Trải nghiệm Lưu Live

**Mục đích:** Khắc phục toàn bộ các lỗi tiềm ẩn (URL 404, validate drop rỗng, che lỗi backend, trùng request ID), đảm bảo lệnh Live Sync nạp thẳng vào game thành công 100% mà không cần khởi động lại máy chủ.

**Files:**
- Chỉnh sửa: `c:\xampp\htdocs\webgoc2026\admin\bosses.php`
- Chỉnh sửa: `c:\xampp\htdocs\webgoc2026\admin\assets\js\boss-admin.js`
- Chỉnh sửa: `c:\xampp\htdocs\webgoc2026\admin\includes\boss_admin_helper.php`

- [x] **Bước 1: Chuẩn hóa toàn bộ đường dẫn tuyệt đối**
  - Chuyển `src="assets/js/boss-admin.js"` thành `/webgoc2026/admin/assets/js/boss-admin.js?v=<?= time() ?>`.
  - Chuyển các lệnh `fetch('api/search_items.php...')` thành `/webgoc2026/admin/api/search_items.php...`.

- [x] **Bước 2: Hiển thị lỗi backend chân thực ngay trong Modal khi lưu thất bại**
  - Truyền `$message` (hoặc thông báo lỗi bắt được trong catch) vào `bossBoot.errorMessage`.
  - Nếu có lỗi, modal mở lại và hiển thị thanh cảnh báo đỏ nổi bật chứa đúng nguyên nhân (ví dụ: *"Vật phẩm #999 không tồn tại"* hoặc *"Cấu hình đã thay đổi bởi admin khác, vui lòng tải lại"*).

- [x] **Bước 3: Thêm Client-side Validation cho Tab Rơi đồ (Drops)**
  - Kiểm tra trước khi submit:
    - Nếu có quy tắc rơi đồ: `Item ID` phải có giá trị hợp lệ (>0 và <=32767).
    - `quantity_min` >= 1 và `quantity_max` >= `quantity_min`.
    - `weight` >= 1.
  - Nếu thiếu, hiển thị thông báo lỗi ngay tại Tab Rơi đồ và focus vào ô cần điền thay vì gửi dữ liệu rác lên server.

- [x] **Bước 4: Tự động làm mới `request_id` khi thao tác hoặc sửa lỗi**
  - Đảm bảo mỗi lần submit lưu mới hoặc thử lại đều có request_id duy nhất để không bị chặn bởi cơ chế chống trùng lặp.

- [x] **Bước 5: Trải nghiệm Live Sync không cần reload trang (AJAX Live Save)**
  - Hỗ trợ lưu cấu hình qua fetch API gửi lệnh `RELOAD_BOSS`.
  - Hiển thị Toast thông báo: *"Đang nạp cấu hình Live vào máy chủ game..."* -> Tự động chuyển sang *"Máy chủ đã nạp thành công #Lệnh! Boss đã sẵn sàng"* mà không cần tải lại toàn bộ trang web.

---

### Task 5: Kiểm thử Toàn diện & Xác thực Trực tiếp

**Mục đích:** Xác minh toàn bộ các tính năng hoạt động trơn tru từ giao diện đến database và cơ chế Live Reload trong game.

**Files:**
- Chạy: `tests/admin_boss_admin_test.php`
- Chạy: `tests/admin_boss_override_test.php`
- Chạy: `tests/admin_boss_admin_ui_test.js`
- Test thực tế: Chạy lệnh kiểm tra truy cập Web Panel qua PHP & Node.js

- [x] **Bước 1: Chạy kiểm thử tự động toàn bộ Unit Tests**
  Xác nhận tất cả tests PHP và JavaScript vượt qua 100%.

- [x] **Bước 2: Kiểm thử nạp Live Boss và kiểm tra hàng đợi lệnh**
  Thực hiện lưu mẫu một boss (ví dụ: Android 13 chỉnh HP và sức đánh), xác nhận dòng lệnh `RELOAD_BOSS` được tạo thành công trong `server_command_queue` với status `pending` và sau đó `completed`.

- [x] **Bước 3: Kiểm tra hiển thị hình ảnh giao diện thực tế**
  Đối chiếu giao diện mới với các ảnh chụp màn hình hiện tại của người dùng để đảm bảo độ đẹp, độ trực quan và tính tiện dụng vượt trội.
