# Fix Asset Paths and Routing for Subfolder and Root Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khắc phục triệt để lỗi vỡ giao diện (404 CSS, JS, Images) và lỗi định tuyến đường dẫn khi chạy trên môi trường XAMPP (`localhost/webgoc2026`) đồng thời vẫn tương thích hoàn hảo khi triển khai trên tên miền gốc (Root Domain).

**Architecture:** 
1. Tự động phát hiện biến `WEB_ROOT` / `$base_url` trong `connect.php` và `cauhinh.php` dựa trên vị trí thư mục so với `DOCUMENT_ROOT`.
2. Sửa lại `.htaccess` để tự nhận diện thư mục hiện tại khi rewrite URL (loại bỏ cứng `RewriteBase /`).
3. Chuẩn hóa đường dẫn tài nguyên tĩnh (`/view/static/`, `/images/`, `/favicon...`) và link nội bộ (`/trang-chu`, `/forum`, `/gioi-thieu`,...) trong các template giao diện chính bằng `$base_url`.
4. Kiểm chứng bằng `curl` và kiểm tra phản hồi HTTP 200 cho toàn bộ tài nguyên.

**Tech Stack:** PHP 8, Apache mod_rewrite, Vanilla CSS, JS.

---

### Task 1: Cấu hình động Base URL trong connect.php và cauhinh.php

**Files:**
- Modify: `c:/xampp/htdocs/webgoc2026/connect.php`
- Modify: `c:/xampp/htdocs/webgoc2026/cauhinh.php`

- [x] **Step 1: Viết logic tính toán WEB_ROOT và $base_url trong connect.php**
Thêm logic tự động nhận biết thư mục chạy:
```php
if (!defined('WEB_ROOT')) {
    $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $dir_root = str_replace('\\', '/', realpath(__DIR__));
    if ($doc_root && strpos($dir_root, $doc_root) === 0) {
        $sub = trim(substr($dir_root, strlen($doc_root)), '/');
        define('WEB_ROOT', $sub !== '' ? '/' . $sub : '');
    } else {
        define('WEB_ROOT', '/webgoc2026');
    }
}
$base_url = WEB_ROOT;
```

- [x] **Step 2: Cập nhật $_domain trong cauhinh.php sử dụng WEB_ROOT**
Đảm bảo `$_domain` phản ánh đúng đường dẫn đầy đủ bao gồm cả subfolder khi cần.

- [x] **Step 3: Chạy test kiểm tra giá trị WEB_ROOT**
Chạy: `C:\xampp\php\php.exe -r "require 'c:/xampp/htdocs/webgoc2026/connect.php'; echo WEB_ROOT;"`
Kỳ vọng: In ra `/webgoc2026` khi chạy trong thư mục này.

---

### Task 2: Điều chỉnh .htaccess để hỗ trợ URL Rewrite trong thư mục con

**Files:**
- Modify: `c:/xampp/htdocs/webgoc2026/.htaccess`

- [x] **Step 1: Cập nhật .htaccess**
Bỏ `RewriteBase /` hoặc sử dụng RewriteCond linh hoạt để các đường dẫn như `/trang-chu`, `/forum` được rewrite chuẩn xác về file `.php` trong thư mục hiện tại.

- [x] **Step 2: Kiểm tra định tuyến bằng curl**
Chạy: `curl.exe -I http://localhost/webgoc2026/trang-chu`
Kỳ vọng: Trả về HTTP 200 OK thay vì 404 Not Found.

---

### Task 3: Cập nhật đường dẫn tài nguyên trong index.php và trang-chu.php

**Files:**
- Modify: `c:/xampp/htdocs/webgoc2026/index.php`
- Modify: `c:/xampp/htdocs/webgoc2026/trang-chu.php`

- [x] **Step 1: Thay thế các đường dẫn cứng bắt đầu bằng `/view/`, `/images/`, `/favicon` bằng `<?= $base_url ?>/...`**
Áp dụng cho:
- CSS: `<?= $base_url ?>/view/static/css/...`
- JS: `<?= $base_url ?>/view/static/js/...`
- Images: `<?= $base_url ?>/images/...`
- Links: `<?= $base_url ?>/trang-chu`, `<?= $base_url ?>/gioi-thieu`, `<?= $base_url ?>/forum`

- [x] **Step 2: Kiểm tra phản hồi HTTP của trang chủ**
Chạy: `curl.exe -s http://localhost/webgoc2026/ | Select-String "webgoc2026/view/static/css"`
Kỳ vọng: HTML chứa đường dẫn hợp lệ kèm tiền tố `/webgoc2026`.

---

### Task 4: Cập nhật head.php và các file giao diện dùng chung

**Files:**
- Modify: `c:/xampp/htdocs/webgoc2026/head.php`
- Modify: `c:/xampp/htdocs/webgoc2026/forum.php`
- Modify: `c:/xampp/htdocs/webgoc2026/gioi-thieu.php`
- Modify: `c:/xampp/htdocs/webgoc2026/bang-xep-hang.php`
- Modify: `c:/xampp/htdocs/webgoc2026/top-nap.php`
- Modify: `c:/xampp/htdocs/webgoc2026/top-nhiem-vu.php`

- [x] **Step 1: Cập nhật head.php để hỗ trợ $base_url cho avatar và assets**
- [x] **Step 2: Cập nhật bang-xep-hang.php, top-nap.php, top-nhiem-vu.php**
Sửa `<base href="/">` thành `<base href="<?= $base_url ?>/">` hoặc dùng tiền tố `$base_url`.

---

### Task 5: Xác thực toàn diện (Verification Before Completion)

- [x] **Step 1: Kiểm tra tải CSS/JS của trang chủ**
Chạy curl test mã HTTP 200 cho:
`http://localhost/webgoc2026/view/static/css/template.css`
`http://localhost/webgoc2026/view/static/css/eff.css`
`http://localhost/webgoc2026/images/logo_sk_he.png`
`http://localhost/webgoc2026/images/banner_Summer.png`

- [x] **Step 2: Kiểm tra trang chủ và forum hiển thị đầy đủ**
Kỳ vọng: Không còn lỗi 404 trên các tài nguyên tĩnh.
