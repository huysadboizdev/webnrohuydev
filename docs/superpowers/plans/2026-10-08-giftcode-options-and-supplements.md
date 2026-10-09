# Giftcode options, sửa và nhận phần bổ sung — kế hoạch đã triển khai

> **For agentic workers:** Công việc đã thực hiện theo TDD và subagent-driven-development; không chạy lại migration/backfill hoặc reset lịch sử để sửa quà. Thay đổi tiếp theo dùng executing-plans và các checkpoint kiểm thử.

**Goal:** Admin cấu hình nhiều option, sửa Giftcode và đồng bộ; người đã nhập code chỉ nhận phần quà mới hoặc số lượng còn thiếu.

**Architecture:** PHP lưu catalog và queue RELOAD_GIFTCODE cùng transaction, chống form lỗi thời bằng fingerprint. Java tính delta theo watermark account/code/item, ghi snapshot người chơi, watermark, quota và UUID receipt cùng một transaction.

**Tech Stack:** PHP, JavaScript thuần/node:test, MySQL/MariaDB InnoDB, Java17/JDBC/Gson, Live Sync hiện có.

---

## Hành vi đã chốt

1. Code trước có 10 A, sau sửa thành 15 A + 2 B: người đã nhận chỉ được 5 A + 2 B; người mới nhận 15 A + 2 B.
2. Nhập lại khi không còn phần bổ sung bị từ chối, không phát trùng. Dùng/bán đồ đã nhận không làm được nhận lại.
3. Chỉ lần nhận đầu trừ lượt. Người đã nhận vẫn nhận bổ sung khi lượt còn lại bằng 0, với điều kiện code còn hạn.
4. Giảm/xóa không thu hồi quà hoặc hạ watermark; thêm lại số lượng cũ không được nhận lại.
5. Giữ tên code khi sửa. Option của ID món đã có người nhận được giữ cố định, kể cả xóa rồi thêm lại. Trước khi có người nhận có thể sửa option.
6. Lịch sử cũ được chuyển sang mốc quà hiện tại một lần. Claim cũ không còn catalog có baseline NULL, từ chối bổ sung/sửa an toàn thay vì giả định chưa nhận.
7. Server tiếp tục áp dụng option bắt buộc; preview là cấu hình lưu, không cam kết bỏ được quy tắc trang bị.

## Các bước và kết quả

- [x] Khảo sát trang Giftcode, API options, validator và lệnh buff; mở rộng thiết kế theo yêu cầu sửa/nhận phần thiếu.
- [x] Web: modal tạo/sửa, sửa từng món, nhiều option và nhập nhanh `50:20 77:20 103:20 30:0`, ID/param 0 hợp lệ, kiểm tra trùng/giới hạn, draft độc lập và phục hồi khi POST lỗi.
- [x] PHP: cập nhật theo ID/fingerprint, giữ canonical code, lock catalog và đọc claim bằng FOR UPDATE, bảo vệ option đã phát cả khi xóa/tạo lại code. Lưu + queue + CSRF + idempotency giữ nguyên.
- [x] Migration: delivered_detail, giftcode_claim_request, marker backfill một lần và index canonical_code/account_id; kiểm tra rerun và rollback baseline/receipt/marker.
- [x] Java: tính delta/watermark, cho bổ sung khi quota=0, receipt riêng cho từng request, giữ pending/logout/commit reconciliation và lịch sử string unique.
- [x] Kiểm thử đỏ→xanh: parser, clone/sửa/hủy draft, option locking, form cũ/quota thay đổi, race snapshot khi recreate, delta nhiều lần, túi đầy/SQL rollback, hai request đồng thời, lost commit và receipt cũ.
- [x] Build 655 source Java; 8 nhóm regression trên classes và 8 nhóm trên candidate JAR. Hai suite SQL Java dùng database riêng, đã xóa schema test. Test service thật xác nhận code đã dùng vẫn nhận delta và không ghi receipt khi túi đầy.
- [x] Web: PHP lint/helper/update tests, 4 bài JS, SQL integration và render trang PHP thật trên schema riêng kiểm tra escape tên/option và bootstrap JSON. HTTP editor asset 200.
- [x] Review spec + code; sửa lỗi recreation dùng snapshot cũ và async nhập nhanh ghi đè editor khác; review cuối không còn blocker.
- [x] Triển khai: bảo trì có lưu, backup SQL/JAR timestamp+SHA-256, migration rồi cài JAR và khởi động lại. PID 15244, cổng14445; log đã nạp Giftcode và server initialized. Chỉ SRC/20.jar còn ở thư mục chạy.

## File chính

Web: `admin/giftcodes.php`, `admin/includes/giftcode_helper.php`, `admin/assets/js/giftcode-options.js` và các test `admin/tests/giftcode_*`.

Server: `SRC/src/nro/models/services/GiftCodeRewardDelta.java`, `GiftCodeService.java`, `SRC/src/nro/models/database/GiftCodeClaimDAO.java`, `SRC/sql/migrations/20261008_giftcode_supplements.sql`, các test delta/supplement/claim. Bốn dump/seed đã bổ sung migration để khởi tạo sau này.

## Giới hạn kiểm chứng

Chưa thao tác bằng browser tự động hoặc client game thật. Logic nhận quà được kiểm tra qua Java service thật với fixture và database riêng; không phát quà trên tài khoản người chơi thật trong lần triển khai này.

Metadata triển khai và cách khôi phục: `C:/Users/Admin/Downloads/nroteam/Teamobi2026/.analysis/giftcode-supplements-deployment-latest.json`. Backup file web: `.analysis/giftcode-supplements-backup`.
