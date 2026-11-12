# Phân tích workbook Excel Cao Thị

## Trạng thái phân tích

Đã đọc chỉ-đọc file `reference/CAO THỊ_QUẢN LÝ THU CHI_FINAL (2).xlsm` bằng cấu trúc OOXML và PhpSpreadsheet. File có 12 sheet và chứa `xl/vbaProject.bin`; macro không được chạy, workbook gốc không bị sửa.

Sheet nhật ký có 12 đoàn ở các dòng 5–19, ngày 24/10/2026–10/11/2026, trên 7 ngày. Không có trùng ngày/mã trong 12 dòng. Phân loại thanh toán hiện có 8 đoàn tiền mặt, 4 đoàn CK công ty; không thấy CK cá nhân hoặc chưa phân loại trong các dòng lịch sử này.

Tổng theo các snapshot lưu sẵn ở `NHAT KY DOAN`: vé 11.180.000đ; ảnh 7.850.000đ; áo dài 0đ; tổng thu 19.030.000đ; tổng chi 11.470.000đ; lãi gộp 7.560.000đ; giá vốn ảnh 2.845.000đ; lãi sau giá vốn ảnh 4.715.000đ. Đây là tổng từ workbook, chưa được ghi vào database.

## Phạm vi và mapping đã xác minh

- Nguồn đoàn duy nhất là `NHAT KY DOAN`; bỏ qua `NHAP THEO NGAY` để tránh bản xem/nhập trùng và bỏ qua bảng dashboard/báo cáo tổng hợp.
- Ngày, mã đoàn, HDV, số điện thoại, khách, thanh toán: A–I. Điện thoại giữ dạng chuỗi.
- Số lượng dịch vụ: L–AB; chè dừa S, ảnh W–AA, áo dài AB. Dịch vụ nước/dừa/cà phê/bia không phát sinh doanh thu website; snapshot lượng vẫn được giữ.
- Tài chính: vé AC, công chèo AD, ảnh AE, áo dài AF, tổng thu AG, chi vé AH, hoa hồng cửa AI, chè dừa AJ, ảnh AK, áo dài AL, chi nước AM, tổng chi AN, lãi AO; giá vốn ảnh CA và lãi sau giá vốn CB.
- Hình thức thu vé nằm ở I; giá trị phân loại vé/tổng tiền mặt là CC–CI. Đoàn không có giá trị hợp lệ ở I được giữ chưa phân loại, không mặc định tiền mặt.
- `BANG GIA` xác nhận ảnh khung lớn/nhỏ cho khách Việt 180.000đ/120.000đ, khách quốc tế 200.000đ/150.000đ; plastic lớn/nhỏ Việt 100.000đ/70.000đ, quốc tế 120.000đ/100.000đ; vốn ảnh 75.000đ/40.000đ/10.000đ/5.000đ; hoa hồng ảnh plastic 10.000đ từ 09/11/2026. Giá vốn ảnh được tính trong lãi sau vốn, không cộng hai lần vào chi.
- Hoa hồng cửa trong bảng giá: 50.000đ cho đoàn tối đa 10 khách, 100.000đ trên 10. Hoa hồng chè dừa 10.000đ/phần và áo dài 20.000đ/đơn vị. Công chèo 40.000đ/thúng; chi vé 30.000đ/vé.
- `LUU CHI PHI CU` tự ghi rõ không cộng vào báo cáo; có 4 dòng chi thật (điện/nước, vận hành, in ấn, vệ sinh) và được lưu dạng archive, không vào bảng `expenses`.
- `DANH SACH HDV`, tra cứu, dashboard, phân tích và `CHI TIET DOAN HDV` là danh sách/tổng hợp; không dùng làm nguồn đoàn thứ hai.

## Mapping dự kiến, cần đối chiếu

| Dữ liệu workbook | Đích dự kiến | Ghi chú |
|---|---|---|
| HDV và điện thoại từ các dòng đoàn | `guides` | Ghép chính xác số điện thoại; xung đột tên hoặc HDV đã lưu trữ cần đối soát. |
| Đoàn, mã, khách, số tiền snapshot | `tour_groups` | Lưu ngày đủ năm, nguồn `excel`, chưa đối soát, không tính Dashboard ban đầu. |
| Chè dừa, áo dài, lượng dịch vụ khác | `tour_services` | Lưu số lượng và snapshot hoa hồng; doanh thu chè dừa bằng 0. |
| Lượng và snapshot ảnh theo loại | `tour_photos` | Giá/giá vốn/hoa hồng từng dòng phải khớp snapshot AE/CA/AK. Không sinh bút toán kho. |
| Lô, dòng nguồn, checksum, dữ liệu gốc, lỗi | `excel_import_batches`, `excel_import_rows` | Hash workbook chống chạy lặp; ngày+mã và ngày+sequence chống ghi trùng. |
| Chi phí cũ | `excel_import_archives` | Chỉ lưu trữ, không vào Dashboard. |

Quy trình preview/confirm được xây dựng; chỉ confirm thủ công mới chép các dòng `ready`. Dòng chưa xác minh được giữ nguyên payload gốc và không được import.

## Dòng cần đối soát trong workbook hiện tại

- Dòng 10 và 11 (`CT-01-10/11`, `CT-02-10/11`): ô hoa hồng cửa AI để trống, không có giá trị lưu; không tự thay bằng 0.
- Dòng 19 (`CT-01-03/11`): 48 khách quốc tế; 23 ảnh khung lớn và 1 khung nhỏ. Snapshot doanh thu ảnh AE là 4.260.000đ, trong khi lượng và đơn giá BANG GIA cho ra 4.750.000đ. Chênh 490.000đ; AE là giá trị nhập cứng, không phải công thức. Hệ thống đưa dòng vào `needs_review`, không dùng lại tổng công thức để ghi đè snapshot.
- Kết quả preview theo quy tắc trên: 9 dòng sẵn sàng, 3 dòng cần đối soát; 4 khoản chi thực từ sheet cũ được lưu archive. Snapshot của 9 dòng sẵn sàng: tổng thu 10.390.000đ, tổng chi 7.820.000đ, lãi gộp 2.570.000đ, vốn ảnh 760.000đ, lãi sau vốn ảnh 1.810.000đ.
- Workbook có 56.900 ô công thức, trong đó 55.345 ô không có cache; phần lớn là công thức mẫu ở vùng tra cứu/template. Các ô tài chính cần thiết của 12 dòng đoàn có cache hoặc giá trị tĩnh; ô hoa hồng cửa AI để trống ở hai dòng được giữ là chưa xác minh. Parser chỉ đọc cache, không tự tính Excel.

## Bảo toàn dữ liệu lịch sử

- Không cập nhật, xóa hoặc ghi đè workbook gốc.
- Lưu snapshot số tiền đã ghi nhận của đoàn cũ; không tính lại theo giá vé, giá ảnh hay hoa hồng mới.
- Dùng mã nguồn và/hoặc dấu vân tay dữ liệu để nhận diện import lặp; nếu khóa trùng có khác biệt dữ liệu thì đưa vào danh sách đối chiếu, không ghi đè.
- Chỉ import sau khi có workbook, mapping được xác nhận, preview tổng tiền và người có thẩm quyền duyệt.

## Việc còn lại

1. Người quản lý đối soát ba dòng nêu trên trong workbook gốc, sau đó tải bản đã xử lý/duyệt để phân tích lại.
2. Mở màn Admin `/admin/import-excel`, xem preview và tự xác nhận import khi dữ liệu sẵn sàng.
3. Sau khi đối soát dữ liệu lịch sử với báo cáo gốc, dùng bước bật Dashboard riêng.
4. Không chạy import vào MySQL cấu hình sẵn trước khi duyệt preview và lô cần nhập.
