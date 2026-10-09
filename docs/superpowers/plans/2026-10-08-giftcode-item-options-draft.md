# Giftcode Item Options — Kế hoạch triển khai, cập nhật 08/10/2026

**Đã triển khai:** xem bản kết quả đầy đủ `2026-10-08-giftcode-options-and-supplements.md` cùng thư mục. Phần dự thảo bên dưới lưu lại khảo sát ban đầu; phạm vi thực tế gồm cả sửa code và nhận phần quà bổ sung.

> **For agentic workers:** Khi thiết kế được chấp thuận, dùng skill `executing-plans` để triển khai từng task với checkpoint. Các bước dùng checkbox. Bản này phục vụ yêu cầu kiểm tra và lên kế hoạch; chưa phải thiết kế đã được người dùng duyệt.

**Goal:** Bổ sung cấu hình ID option và param riêng cho từng quà trong trang admin Giftcode, có nhập nhanh theo cú pháp buff đồ admin.

**Architecture:** Dùng API tìm vật phẩm/option hiện có, một bộ quản lý option phía trình duyệt và validator Giftcode PHP hiện có. Dữ liệu gửi vẫn là danh sách vật phẩm `{id, quantity, options:[{id,param}]}`; lưu giftcode và xếp hàng RELOAD_GIFTCODE trong giao dịch hiện tại.

**Tech Stack:** PHP, JavaScript thuần, MySQL, luồng Live Sync hiện có. Source Java chỉ dùng để đối chiếu hành vi trong lần khảo sát này.

---

## 1. Phạm vi và bằng chứng đã kiểm tra

- `admin/giftcodes.php:268`: mỗi vật phẩm bị gắn cứng `options: [{id:30,param:0}]`; không có trình sửa option. Danh sách đã chọn và bảng giftcode không hiển thị option.
- `admin/api/search_items.php:43`: có `action=options`, tìm theo tên hoặc ID trong `item_option_template`, hỗ trợ limit/offset. Dùng lại API, không hard-code số lượng hay danh sách ID option.
- `admin/includes/giftcode_helper.php`: đã kiểm tra kiểu số, miền số, tối đa 200 phần thưởng, tối đa 100 option/món, cấm trùng ID vật phẩm và trùng ID option. Khi lưu kiểm tra item/option tồn tại trong catalog SQL.
- `admin/player_detail.php:598`: form tặng đồ admin web đang có preset 30:0, 50:50, 77:50, 103:7; chưa có bộ chọn nhiều option tự do. Không sao chép nhãn preset làm nguồn sự thật; tên/đơn vị lấy từ catalog.
- Source đối chiếu `C:/Users/Admin/Downloads/nroteam/Teamobi2026/SRC/src/nro/models/server/Command.java:212`: lệnh `i <itemId> <quantity> [optionId:value...]` parse từng cặp và gọi validateOption/grantItems.
- Source đối chiếu `SRC/src/nro/models/services/GiftCodeService.java`: nhận options tùy chỉnh, cấm option trùng/param âm, thêm option 30 cho thỏi vàng 457 rồi gọi ItemDefaultOptions.complete.
- `SRC/src/nro/models/item/ItemDefaultOptions.java`: một số trang bị bị ép option sức mạnh yêu cầu/khóa giao dịch; một số món bổ sung option mặc định còn thiếu. Options rỗng trong Giftcode không đồng nghĩa chính xác với hành vi mặc định của lệnh buff đồ.
- Không có GitNexus tool/index tại workspace đang kiểm tra; dùng đọc source và rg để truy vết. Workspace web không có Git repository tại thời điểm khảo sát.
- Chưa xác nhận bytecode của JAR đang chạy đồng nhất với toàn bộ source Java trên. Cần xác nhận bằng nhận quà trên tài khoản thử trước kết luận hành vi chạy thật.

## 2. Các phương án

| Phương án | Lợi ích | Hạn chế |
|---|---|---|
| Bảng option + nhập nhanh ID:param (đề xuất) | Dễ dùng, hỗ trợ nhiều option, thuận tiện cho admin quen lệnh buff | Cần quản lý trạng thái draft và sửa món đã thêm |
| Chỉ preset thuộc tính | Ít thay đổi giao diện | Không đáp ứng cấu hình option tự do |
| Chỉ nhập chuỗi option | Làm nhanh, gọn | Khó tra ID, dễ nhập sai và không thấy tên thuộc tính |

## 3. Thiết kế đề xuất

### Chọn vật phẩm và option

- Giữ tìm vật phẩm theo tên/ID, icon và số lượng.
- Thêm bảng: ID option, tên thuộc tính, param, nút xóa. Mỗi món sở hữu một danh sách độc lập.
- Tra option theo tên hoặc ID bằng API hiện có; có thể gõ ID rồi xác nhận catalog. ID 0 là hợp lệ, không dùng kiểm tra truthy để loại nó.
- Thêm/sửa/xóa nhiều option; param là số nguyên từ 0 đến 2147483647. Số lượng là số nguyên từ 1 đến 2147483647.
- Giới hạn 100 option/món, 200 món/code; cấm trùng ID option trong món và cấm trùng ID vật phẩm trong code theo hợp đồng hiện tại.
- Nhập nhanh `50:20 77:20 103:20 30:0`. Chuỗi được parse toàn bộ và xác nhận catalog trước khi thay danh sách draft; nếu một token sai thì giữ nguyên draft cũ.
- Nhập nhanh thay toàn bộ option của món đang soạn; có nhãn rõ để tránh hiểu là cộng thêm.
- Giữ mặc định 30:0 để tương thích thao tác hiện tại, nhưng hiển thị công khai trong bảng và cho xóa. Giải thích rằng server vẫn có thể thêm/ép option theo quy tắc của vật phẩm.
- Tên thuộc tính dùng template catalog; nếu có dấu # thì thay bằng param để xem trước. Không tự suy diễn phần trăm hay đơn vị từ ID.

### Danh sách quà đã thêm

- Hiển thị ID, tên, số lượng và các option đã cấu hình dưới từng món.
- Có nút sửa: tải đúng món vào editor, lưu thay thế đúng index; hủy sửa giữ nguyên món cũ.
- Khi thêm món khác, clone danh sách option và reset draft, tránh option món trước lan sang món sau.
- Nếu nhập ID món đã có, thông báo dùng nút sửa; không tự gộp số lượng khi option khác nhau.
- Bảng giftcode đã lưu hiển thị option dưới quà. Đọc dữ liệu catalog theo lô thay vì truy vấn từng option trong vòng lặp.
- Phân biệt preview cấu hình đã lưu với option thực tế mà server có thể bổ sung. Không khẳng định preview là bản sao tuyệt đối vật phẩm nhận.

### Luồng lưu và lỗi

- Giữ JSON options dạng object `{id,param}`. Không đổi sang array `[id,param]` của đường GIFT_ITEM.
- Giữ CSRF, xác thực admin, transaction catalog, queue RELOAD_GIFTCODE và audit hiện có.
- Chặn dữ liệu sai trên trình duyệt để hỗ trợ người dùng; PHP vẫn quyết định dữ liệu hợp lệ.
- Nếu POST bị từ chối, phục hồi draft đã nhập sau khi validate và escape để admin sửa; không bắt nhập lại toàn bộ quà.
- Tên vật phẩm/option hiển thị bằng textContent hoặc htmlspecialchars, không nội suy dữ liệu catalog trực tiếp vào innerHTML.
- Khi tìm kiếm lỗi, hiện thông báo và cho thử lại. Bỏ kết quả phản hồi cũ nếu truy vấn đã thay đổi.
- Thành công lưu vẫn là đã lưu và xếp hàng lệnh; chỉ nói đồng bộ xong khi có trạng thái hoàn tất từ cơ chế queue hiện có.

### Giới hạn phạm vi

- Giai đoạn này nâng cấp trang Giftcode. Các lệnh buff chỉ số, boss, ban, kick và thao tác admin khác không đưa vào mã quà.
- Chưa thêm nhập nguyên lệnh `i ...`, preset theo loại trang bị hay sửa giftcode đang lưu. Có thể làm tiếp khi có nhu cầu.
- Không thay chính sách nhận code, giới hạn cộng dồn, sức chứa túi hoặc quy tắc vật phẩm bị hạn chế.
- Dự kiến không cần migration SQL hoặc thay JAR. Nếu kiểm thử xác định cần sửa server, lập phần triển khai bổ sung và tuân thủ backup JAR, dừng có lưu dữ liệu, SHA-256 và metadata trước thay thế.

## 4. File dự kiến

| File | Trách nhiệm |
|---|---|
| Sửa `admin/giftcodes.php` | UI option, nối editor với danh sách quà, phục hồi draft lỗi, hiển thị option của code đã lưu |
| Tạo `admin/assets/js/giftcode-options.js` | Parse cặp option, trạng thái editor, tìm catalog, render an toàn; chỉ tách logic phục vụ Giftcode |
| Sửa `admin/tests/giftcode_helper_test.php` | Bổ sung ca nhiều option, option 0, param 0, giới hạn option và lỗi số |
| Tạo `admin/tests/giftcode_options_test.js` | Test parser và độc lập trạng thái giữa các món, chạy bằng node:test nếu Node có sẵn |
| `admin/api/search_items.php` | Dùng lại; chỉ sửa nếu kiểm thử chứng minh lỗi tìm kiếm ảnh hưởng tính năng |
| `admin/includes/giftcode_helper.php` | Giữ hợp đồng; chỉ sửa nếu cần thông báo lỗi rõ vị trí món/option |

## 5. Trình tự triển khai sau khi chốt thiết kế

- [ ] Task 1 — Chốt default 30:0, cú pháp nhập nhanh và giới hạn; ghi thiết kế được duyệt. Kiểm tra các thay đổi sẵn có trước khi sửa.
- [ ] Task 2 — Viết test parser: `0:0 50:20` hợp lệ; `50:20 50:30`, `50:-1`, `50:1.5`, `50:2147483648`, `abc`, `50:` bị từ chối; lỗi parse không đổi danh sách đang có.
- [ ] Task 3 — Viết parser/manager option để test Task 2 qua; xác nhận clone option giữa hai món, sửa/hủy đúng index.
- [ ] Task 4 — Nối tìm catalog, bảng option, nhập nhanh vào modal; thêm/sửa/xóa từng món và hiển thị preview.
- [ ] Task 5 — Hiển thị options tại bảng giftcode, khôi phục draft khi POST lỗi, escape toàn bộ nhãn; giữ nguyên JSON và transaction Live Sync.
- [ ] Task 6 — Bổ sung test PHP: round-trip nhiều option giữ nguyên id/param; option 0/param 0; 101 option bị từ chối; item trùng/option trùng vẫn bị từ chối.
- [ ] Task 7 — Kiểm tra trình duyệt: tìm theo tên/ID, nhập nhanh, sửa/hủy/xóa món, món trùng, phản hồi tìm kiếm cũ, mất mạng, đóng/mở modal, bố cục màn hình nhỏ.
- [ ] Task 8 — Khi được giao triển khai và có tài khoản thử: tạo code thử có nhiều option, chờ queue hoàn tất, nhận quà trong game; kiểm tra option thực tế và quy tắc 457/trang bị bị khóa. Không dùng tài khoản người chơi thật cho phép thử.

## 6. Kiểm chứng và tiêu chí nghiệm thu

Đã chạy khảo sát ngày 08/10/2026:

```powershell
& C:\xampp\php\php.exe admin\tests\giftcode_helper_test.php
# giftcode_helper_test PASS
& C:\xampp\php\php.exe -l admin\giftcodes.php
# No syntax errors detected
& C:\xampp\php\php.exe -l admin\api\search_items.php
# No syntax errors detected
```

Sau triển khai chạy lại ba lệnh trên, lint tất cả PHP có chỉnh sửa và chạy `node --test admin/tests/giftcode_options_test.js` (kiểm tra Node trước khi chọn runner).

Nghiệm thu khi mỗi món có options độc lập, ID/param được lưu đúng, sửa món không tạo bản sao, lỗi input không mất draft, và queue + nhận quà thử giữ đúng các thuộc tính do admin cấu hình trừ các quy tắc server đã thông báo. Chưa có kiểm thử trình duyệt, DB integration hoặc phát quà thực tế trong lần lập kế hoạch này.

## 7. Mục cần người dùng chốt

Người dùng đã yêu cầu triển khai, thêm sửa Giftcode và nhận phần quà thiếu. Các mục dự thảo giới hạn sửa Giftcode/JAR ở trên được thay bằng phần mở rộng sau; form tặng đồ player_detail vẫn ngoài phạm vi.

## 8. Mở rộng đã được yêu cầu: sửa và bổ sung

Thiết kế: `docs/superpowers/specs/2026-10-08-giftcode-supplement-design.md`.

- [ ] Web: thêm `giftcode_update`, khóa catalog row, so fingerprint form với row hiện tại. Tên code giữ nguyên; tùy chỉnh số lượt/hạn/quà. Kiểm tra options các món đã phát qua snapshot claim, không đổi options những ID đó.
- [ ] UI: thêm nút Sửa code, load modal có dữ liệu cũ, submit update với ID/fingerprint; sửa từng item và hiển thị options.
- [ ] Migration `SRC/sql/migrations/20261008_giftcode_supplements.sql`: thêm delivered_detail vào giftcode_claim, backfill catalog hiện tại chỉ một lần; bảng giftcode_claim_request ghi receipt request độc lập. Chạy bảo trì có backup trước migration live.
- [ ] Java `GiftCodeRewardDelta`: parse current/delivered, kiểm tra ID/quantity/options và tính delta cùng watermark max; không phát lại khi giảm/xóa/thêm lại. Từ chối đổi option đã phát.
- [ ] DAO: đọc snapshot claim dưới khóa catalog; lần đầu giảm quota, supplement giữ quota và cho phép quota=0; ghi claim watermark + request receipt + player cùng transaction. Reconciliation đọc receipt đúng token độc lập các lần sau.
- [ ] Service: stage chỉ delta; không chặn dựa vào danh sách code đã nhận của Player nữa, giữ lịch sử string unique tương thích. Không bỏ cơ chế pending/logout/reconciliation.
- [ ] Test đỏ rồi xanh: delta 10→15, thêm món, thứ tự option, giảm rồi tăng, duplicate, no delta; DB baseline legacy, hết quota supplement, concurrent/retry, rollback, lost ack.
- [ ] Build/test candidate và deploy bảo trì có lưu, backup SQL/JAR timestamp+hash+metadata; kiểm tra startup/runtime.
