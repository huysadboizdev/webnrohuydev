# Sửa đường dẫn admin trên VPS

Đã đăng nhập kiểm tra ngocrongbear.online: CSS và JavaScript admin trả HTTP 404 vì URL cố định `/webgoc2026/` trong khi website chạy tại `/`.

Bản sửa dùng WEB_ROOT có sẵn trong connect.php cho CSS, JS, menu, API, form và ảnh boss. Hỗ trợ root và thư mục con; không thay cấu hình database hoặc dữ liệu game.

## Cài bản sửa

1. Sao lưu thư mục `admin` đang chạy trên VPS.
2. Giải nén gói `admin-vps-path-fix.zip` vào thư mục gốc website, nơi có `connect.php`. Cho phép ghi đè các file trong `admin`; không xóa cả thư mục.
3. Nếu website nằm trực tiếp trong `C:\xampp\htdocs`, đích giải nén là `C:\xampp\htdocs`. Nếu Apache VirtualHost dùng DocumentRoot khác, chọn đúng thư mục đó.
4. Tải lại `/admin/` bằng Ctrl + F5. CSS và JS phải trả HTTP 200 ở `/admin/assets/...`. Đường dẫn sẽ tự giữ tiền tố khi chạy website trong thư mục con.

Gói không chứa connect.php, mật khẩu, SQL hoặc cấu hình VPS. Không cần khởi động lại game server.

## Kiểm tra đã chạy

- PHP syntax toàn bộ admin, icon và 152 ảnh boss.
- Bộ kiểm tra URL PHP/JS ở root và thư mục con; kiểm tra cú pháp 9 script inline.
- Kiểm tra JS hàng đợi lệnh, giao diện boss và sự kiện.
- Chromium đăng nhập local: dashboard, bosses, events, players ở hai cách triển khai (8 lượt trang). Không có CSS/JS/admin HTTP lỗi hoặc JavaScript runtime error; giao diện admin dùng flex đúng CSS.

Bản sửa chưa được cài trên VPS: quyền đăng nhập admin website không cung cấp quyền ghi file hệ thống VPS.
