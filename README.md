# webnrohuydev

Source website PHP của webgoc2026.

## Cấu hình

1. Copy `connect.example.php` thành `connect.php`, rồi điền cấu hình database và cổng thanh toán của môi trường chạy.
2. Nếu sử dụng webhook SePay, copy `api/sepay-secret.example.php` thành `api/sepay-secret.local.php`, rồi điền khóa riêng.
3. MoMo/Web2M: copy `momo-token.example.php` to `momo-token.local.php` and configure your private token.
4. Các migration nằm trong `sql/migrations`; đọc nội dung và sao lưu database trước khi áp dụng.

Thư mục `down` chứa các bản tải game cục bộ và không được đưa vào repo. Bổ sung bản tải hoặc cấu hình đường dẫn tải theo môi trường triển khai.

Không commit cấu hình local, khóa thanh toán, log, dữ liệu sao lưu hoặc database dump.
