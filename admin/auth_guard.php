<?php
require_once __DIR__ . '/includes/url_helper.php';
// admin/auth_guard.php
// Middleware bảo vệ nghiêm ngặt: Chỉ cho phép tài khoản Role Admin truy cập

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/connect.php';

$is_admin_verified = false;
$logged_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$logged_username = $_SESSION['username'] ?? $_SESSION['account'] ?? 'Khách';

if ($logged_user_id && isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT id, username, is_admin, admin, ban FROM account WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $logged_user_id);
        $stmt->execute();
        $user_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user_row && (($user_row['is_admin'] ?? 0) == 1 || ($user_row['admin'] ?? 0) == 1) && ($user_row['ban'] ?? 0) == 0) {
            $is_admin_verified = true;
            $_SESSION['is_admin'] = 1;
            $_SESSION['admin'] = 1;
            $logged_username = $user_row['username'];
        }
    }
}

// Chặn truy cập nếu không phải Admin
if (!$is_admin_verified) {
    http_response_code(403);
    if (defined('ADMIN_JSON_API') && ADMIN_JSON_API) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'error','message'=>'Admin access required']);
        exit;
    }
    echo '<!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 Forbidden - Truy Cập Bị Từ Chối</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
                color: #f8fafc;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .card {
                background: rgba(30, 41, 59, 0.7);
                backdrop-filter: blur(12px);
                border: 1px solid rgba(239, 68, 68, 0.3);
                border-radius: 16px;
                padding: 40px 30px;
                max-width: 480px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            }
            .icon-box {
                width: 80px;
                height: 80px;
                background: rgba(239, 68, 68, 0.15);
                border: 2px solid #ef4444;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px;
                font-size: 36px;
            }
            h1 { font-size: 26px; color: #ef4444; margin-bottom: 12px; }
            p { font-size: 15px; color: #94a3b8; line-height: 1.6; margin-bottom: 28px; }
            .btn {
                display: inline-block;
                background: #3b82f6;
                color: #fff;
                font-weight: 600;
                text-decoration: none;
                padding: 12px 28px;
                border-radius: 10px;
                transition: all 0.2s;
            }
            .btn:hover { background: #2563eb; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-box">🛡️</div>
            <h1>403 - KHU VỰC QUẢN TRỊ</h1>
            <p>Trang web này yêu cầu quyền <strong>Admin</strong> để truy cập. Tài khoản hiện tại của bạn không đủ đặc quyền quản trị viên.</p>
            <a href="' . rtrim(admin_url(''), '/') . '/" class="btn">Về Trang Chủ</a>
        </div>
    </body>
    </html>';
    exit();
}

// CSRF Helpers
function get_csrf_token() {
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function verify_csrf_token($token) {
    return is_string($token) && isset($_SESSION['admin_csrf_token']) && hash_equals($_SESSION['admin_csrf_token'], $token);
}
