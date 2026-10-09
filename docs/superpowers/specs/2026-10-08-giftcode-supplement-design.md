# Giftcode option, sửa và nhận quà bổ sung

Người dùng đã yêu cầu triển khai kế hoạch option và bổ sung sửa Giftcode để người đã nhận được nhập lại lấy phần thiếu (08/10/2026).

## Hợp đồng

- Mỗi reward có ID vật phẩm duy nhất; nhiều option `{id,param}` được chọn từ catalog. Giữ nhập nhanh `id:param`.
- Sửa code theo ID catalog, không đổi tên code. Dùng dấu vân tay nội dung/số lượt/hạn cũ để chặn ghi đè từ form lỗi thời.
- Lưu watermark số lượng đã phát theo account + canonical code + item ID. Delta = max(0, số lượng hiện tại - watermark). Không xét vật phẩm hiện đang còn trong túi; bán/dùng quà không làm được nhận lại.
- Thêm món mới phát toàn bộ món mới, tăng món cũ chỉ phát phần tăng. Giảm/xóa không thu hồi quà và không hạ watermark; thêm lại cùng số lượng không phát lại.
- Option của ID từng phát được giữ cố định, kể cả xóa rồi thêm lại. Có thể sửa option trước khi có người nhận. Server tiếp tục áp dụng chính sách option bắt buộc.
- Người nhận lần đầu cần code còn lượt, chưa hết hạn; trừ đúng một lượt. Người bổ sung không trừ lượt, được nhận khi count_left=0 nếu chưa hết hạn. Không có delta thì từ chối không ghi/quy đổi lượt.
- Lịch sử nhận theo tài khoản giữ nguyên khi xóa/tạo lại code hoặc nhân vật. Receipt từng request UUID lưu riêng để phục hồi commit không rõ kết quả mà không phát lại.
- Snapshot bag/inventory/history, watermark, receipt và quota commit cùng transaction. Túi đầy/SQL lỗi phải rollback toàn bộ.
- Migration sao chép detail hiện tại vào các claim cũ như mốc đã phát, giữ request receipt cũ. Claim code không còn catalog được đánh dấu không đủ lịch sử và từ chối bổ sung khi code tái tạo; không giả định người đó chưa nhận.

## UI

Bảng Giftcode có Sửa, Xóa; modal dùng cho tạo/sửa, có sửa từng món, bảng option và nhập nhanh. Hiển thị ID/tên/param trong draft và code đã lưu. Có hướng dẫn phần bổ sung, giới hạn, quy tắc option và thông báo queue. CSRF/audit/idempotency giữ nguyên.

## Kiểm chứng và phát hành

Test JS parser/trạng thái, PHP validation/update conflict/option lock, Java delta và DB transaction trên schema riêng. Kiểm tra migration rerun, legacy backfill, bổ sung qua nhiều lần sửa, quota hết, hai request đồng thời, túi đầy, lost commit/reconciliation. Build toàn source, test candidate JAR. Trước triển khai dùng bảo trì có lưu, backup SQL/JAR với timestamp và SHA-256, migration rồi cài JAR/startup kiểm chứng. Không thử phát quà trên tài khoản người chơi thật.
