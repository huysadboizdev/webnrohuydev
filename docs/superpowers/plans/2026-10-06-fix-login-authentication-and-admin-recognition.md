# Fix Login Authentication and Admin Recognition Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khắc phục triệt để lỗi không đăng nhập được khi nhập tài khoản `admin`, sửa lỗi chuyển hướng URL sau đăng nhập (`redirect`), và xử lý nhận diện đầy đủ quyền hạn Admin trên toàn bộ giao diện web (Avatar, danh hiệu, menu).

**Architecture:**
1. Cải tiến logic truy vấn trong `app/auth_process.php`: cho phép đăng nhập linh hoạt bằng cả **Tên tài khoản (`account.username`)** hoặc **Tên nhân vật (`player.name`)** kèm mật khẩu.
2. Chuẩn hóa đường dẫn phản hồi redirect trong `app/auth_process.php` để chuyển hướng chính xác đến `WEB_ROOT . '/forum'` thay vì root `/forum` gây lỗi 404.
3. Đồng bộ đầy đủ các session đăng nhập (`user_id`, `id`, `username`, `account`, `is_admin`).
4. Bổ sung truy vấn cột `is_admin` và `admin` trong `head.php`, `forum_data.php`, và `set.php` để kích hoạt giao diện Admin (danh hiệu Admin màu đỏ, avatar Admin) khi đăng nhập thành công.
5. Giữ nguyên phạm vi làm việc 100% bên trong thư mục `c:/xampp/htdocs/webgoc2026`.

**Tech Stack:** PHP 8, MySQLi / PDO, Session Auth.

---

### Task 1: Cập nhật logic xác thực và URL chuyển hướng trong app/auth_process.php

**Files:**
- Modify: `c:/xampp/htdocs/webgoc2026/app/auth_process.php:40-75`

- [x] **Step 1: Cải tiến truy vấn đăng nhập hỗ trợ cả username và player.name**
Thay thế câu query cứng trong `app/auth_process.php`:
```php
$stmt = $pdo->prepare("
    SELECT 
        a.id, 
        a.username, 
        a.password, 
        a.is_admin, 
        a.admin 
    FROM account a
    LEFT JOIN player p ON a.id = p.account_id
    WHERE (a.username = :login_input OR p.name = :login_input)
      AND a.password = :password
    LIMIT 1
");
$stmt->execute([
    ':login_input' => $username,
    ':password' => $password
]);
$user = $stmt->fetch();
```

- [x] **Step 2: Cập nhật đầy đủ Session và URL chuyển hướng**
Thiết lập session đồng bộ và redirect tương thích `WEB_ROOT`:
```php
$_SESSION['user_id'] = $user['id'];
$_SESSION['id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['account'] = $user['username'];
$_SESSION['is_admin'] = (($user['is_admin'] ?? 0) == 1 || ($user['admin'] ?? 0) == 1) ? 1 : 0;

$redirect_url = (defined('WEB_ROOT') ? WEB_ROOT : '') . '/forum';
echo json_encode([
    'status' => 'success',
    'message' => 'Đăng nhập thành công! Chúc bạn chơi game vui vẻ.',
    'redirect' => $redirect_url
]);
```

- [x] **Step 3: Chạy test kiểm tra logic đăng nhập với tài khoản 'admin' và mật khẩu '1'**
Chạy script kiểm tra request đăng nhập giả lập.
Kỳ vọng: Trả về JSON `{"status": "success", "redirect": "/webgoc2026/forum"}`.

---

### Task 2: Cập nhật nhận diện quyền Admin trong head.php và forum_data.php

**Files:**
- Modify: `c:/xampp/htdocs/webgoc2026/head.php:28-65`
- Modify: `c:/xampp/htdocs/webgoc2026/forum_data.php:70-105`

- [x] **Step 1: Bổ sung a.is_admin, a.admin vào câu SELECT của head.php**
Truy vấn thêm cột admin:
```php
$sql_user_data = "
    SELECT
        p.name AS player_name,
        p.gender,
        p.head,
        a.tichdiem,
        a.coin,
        a.is_admin,
        a.admin
    FROM
        account a
    LEFT JOIN
        player p ON a.id = p.account_id
    WHERE
        a.username = ?
    LIMIT 1
";
```
Gán quyền admin:
```php
$_admin_logged_in = (($user_row['is_admin'] ?? 0) == 1 || ($user_row['admin'] ?? 0) == 1) ? 1 : 0;
```

- [x] **Step 2: Bổ sung a.is_admin, a.admin vào câu SELECT của forum_data.php**
Trong `forum_data.php`:
```php
$stmt_user_data = $conn->prepare("
    SELECT
        a.id, a.vnd, a.is_admin, a.admin,
        p.name AS player_name, p.gender, p.head
    FROM
        account a
    LEFT JOIN
        player p ON a.id = p.account_id
    WHERE
        a.username = ?
    LIMIT 1
");
```
Gán quyền admin:
```php
$is_admin_for_avatar = (($user_row['is_admin'] ?? 0) == 1 || ($user_row['admin'] ?? 0) == 1) ? 1 : 0;
```

---

### Task 3: Cập nhật nhận diện quyền Admin trong set.php

**Files:**
- Modify: `c:/xampp/htdocs/webgoc2026/set.php:36-55`

- [x] **Step 1: Bổ sung is_admin, admin vào câu SELECT của set.php**
```php
$stmt = $conn->prepare("SELECT id, username, password, gioithieu, vnd, tongnap, active, is_admin, admin FROM account WHERE id = ? AND username = ? LIMIT 1");
```
Gán giá trị biến `$_admin`:
```php
$_admin = (($user_arr['is_admin'] ?? 0) == 1 || ($user_arr['admin'] ?? 0) == 1) ? 1 : 0;
```

---

### Task 4: Xác thực toàn diện (Verification Before Completion)

- [x] **Step 1: Kiểm tra API đăng nhập trực tiếp**
Gửi POST request tới `http://localhost/webgoc2026/app/auth_process.php`:
- Input: `user=admin&pass=1&action=login`
Kỳ vọng: HTTP 200 OK, JSON status `success`, `redirect: /webgoc2026/forum`.

- [x] **Step 2: Kiểm tra đăng nhập với user gốc**
Gửi POST request tới `http://localhost/webgoc2026/app/auth_process.php`:
- Input: `user=1&pass=1&action=login`
Kỳ vọng: HTTP 200 OK, JSON status `success`.

- [x] **Step 3: Kiểm tra hiển thị sau đăng nhập**
Xác nhận avatar admin và tên hiển thị `(Admin)` màu đỏ xuất hiện đầy đủ trên header và diễn đàn.
