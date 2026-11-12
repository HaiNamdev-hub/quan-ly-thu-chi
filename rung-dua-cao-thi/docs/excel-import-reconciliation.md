# Đối soát import dữ liệu Excel thực

Cập nhật: 12/11/2026

## Kết quả

Workbook `reference/CAO THỊ_QUẢN LÝ THU CHI_FINAL (2).xlsm` đã được Admin xác nhận trong batch `#1`. SHA-256 của workbook gốc trùng với file upload đang lưu trong Laravel. File gốc không bị sửa.

Workbook có 12 dòng nghiệp vụ ở sheet `NHAT KY DOAN`: 9 dòng đủ điều kiện đã được import thành 9 đoàn, 3 dòng được giữ `needs_review`, chưa liên kết với đoàn và không được tính trên Dashboard. Không có mã đoàn bị trùng hoặc bị ghi đè trong lượt import. Có 9 HDV liên kết với các đoàn đã nhập.

Đối soát các dòng được duyệt:

| Chỉ tiêu | Excel (9 dòng đủ điều kiện) | MySQL sau import | Chênh lệch |
| --- | ---: | ---: | ---: |
| Doanh thu vé | 7.780.000đ | 7.780.000đ | 0đ |
| Doanh thu ảnh | 2.610.000đ | 2.610.000đ | 0đ |
| Doanh thu áo dài | 0đ | 0đ | 0đ |
| Tổng doanh thu | 10.390.000đ | 10.390.000đ | 0đ |
| Chi vé | 3.900.000đ | 3.900.000đ | 0đ |
| Công chèo | 2.680.000đ | 2.680.000đ | 0đ |
| Hoa hồng cửa | 750.000đ | 750.000đ | 0đ |
| Hoa hồng dịch vụ chè dừa | 110.000đ | 110.000đ | 0đ |
| Hoa hồng ảnh | 380.000đ | 380.000đ | 0đ |
| Chi phí khác | 0đ | 0đ | 0đ |
| Chi phí hoạt động | 7.820.000đ | 7.820.000đ | 0đ |
| Giá vốn ảnh | 760.000đ | 760.000đ | 0đ |
| Lợi nhuận sau giá vốn ảnh | 1.810.000đ | 1.810.000đ | 0đ |

Dashboard lũy kế đến 12/11/2026 đang trả về 9 đoàn, 134 khách, doanh thu 10.390.000đ, chi phí hoạt động 7.820.000đ, giá vốn ảnh 760.000đ, lợi nhuận sau giá vốn ảnh 1.810.000đ. Tiền vé được phân loại: tiền mặt 6.800.000đ, chuyển khoản công ty 980.000đ, chuyển khoản cá nhân 0đ, chưa phân loại 0đ. Sai lệch đối soát thu là 0đ.

## Dòng còn chờ xác minh

- Dòng Excel 10 và 11: ô hoa hồng cửa (cột AI) không có giá trị. Không tự suy diễn thành 0đ.
- Dòng Excel 19 (`CT-01-03/11`): snapshot doanh thu ảnh là 4.260.000đ, trong khi số lượng và giá theo dòng cộng thành 4.750.000đ; chênh 490.000đ. Đây là giá trị snapshot tĩnh trong file, không được tự tính lại.

Tổng của cả 12 dòng trong Excel là 19.030.000đ doanh thu, 11.470.000đ chi phí hoạt động, 2.845.000đ giá vốn ảnh và 4.715.000đ lợi nhuận sau giá vốn ảnh. Các tổng này bao gồm 3 dòng chờ xác minh nên không phải tổng kinh doanh hiện được Dashboard chấp nhận. Chênh lệch doanh thu 8.640.000đ của các dòng chưa duyệt đang được giữ ngoài Dashboard.

## Phân loại các sheet và khoản lưu trữ

- `NHAT KY DOAN` là nguồn giao dịch đoàn chính. `TONG QUAN`, `NHAP THEO NGAY` và các sheet phân tích là tổng hợp hoặc đầu vào thay thế, không import thêm để tránh nhân đôi.
- `DANH SACH HDV` là danh sách phát sinh từ nhật ký đoàn; không nhập lặp HDV độc lập từ sheet này.
- `CHI PHI KHAC` không có số lượng phân bổ nhập thực tế trong các dòng được rà soát; không sinh expense giả.
- Bốn dòng `LUU CHI PHI CU` được giữ trong bảng archive với trạng thái `archived_not_dashboard`; không cộng vào chi phí hiện hành. Bảng `expenses` hiện không có bản ghi.
- Ảnh lịch sử được lưu theo số lượng, doanh thu, giá vốn và hoa hồng snapshot. Không tạo bút toán xuất kho lịch sử khi chưa có tồn đầu kỳ; hiện `inventory_transactions` không có bản ghi.
- Không phát hiện bản ghi DEMO trong database tại thời điểm kiểm tra. API tour, HDV, chi phí và Dashboard đã được cập nhật để lọc DEMO.

## Trạng thái database và thao tác

Kết nối Laravel dùng driver `mysql`, database `cao_thi_management`. Máy chủ thực tế trả phiên bản `10.11.16-MariaDB`, không phải Oracle MySQL 8. Migration bổ sung bảng Excel import đã có trong bảng `migrations` (batch 2). Không chạy migration, `migrate:fresh`, `db:wipe` hoặc `migrate:refresh` trong lần đối soát này.

Audit log ghi nhận Admin (user id 1) đã xác nhận batch `#1`, nhập 9 dòng. Sau khi kiểm tra hash, trạng thái xác nhận, liên kết 9 dòng, 3 dòng chờ và so khớp snapshot, chỉ cập nhật trạng thái của 9 đoàn đã xác nhận sang `APPROVED`/`REAL_IMPORTED`, `is_reconciled=true`, `counts_in_dashboard=true`. Ghi nhận audit `excel_import.dashboard_enabled`. Không thay đổi bất kỳ trường tài chính, dịch vụ, ảnh, tồn kho, file nguồn hoặc dòng `needs_review` nào. Không tạo thêm đoàn, HDV, expense hay giao dịch tồn kho.

## Kiểm thử

- Laravel PHPUnit: 28 tests, 193 assertions — đạt.
- Frontend `vue-tsc --noEmit && vite build` — đạt. Vite có cảnh báo chunk Dashboard lớn hơn 500 kB, không làm build thất bại.
- Đọc `DashboardService` trên database thực trong transaction chỉ đọc: 9 đoàn và các tổng tiền ở trên khớp; `rows_needing_review=3`, `tours_pending_reconciliation=0`.
- API thống kê số ảnh bán đã lọc các bản ghi DEMO; kiểm thử Demo xác nhận ảnh demo không xuất hiện trong số lượng bán nghiệp vụ.

Không kết luận toàn bộ workbook đã được duyệt: 3 dòng còn chờ Admin xác minh và xác nhận riêng trước khi nhập.
