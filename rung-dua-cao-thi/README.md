# CAO THI MANAGEMENT

Nền tảng quản trị vận hành Rừng Dừa Cao Thị, gồm API Laravel và ứng dụng Vue 3/TypeScript tách biệt.

## Yêu cầu

- PHP 8.3 trở lên và Composer.
- PHP extensions `zip` và `gd` bật để PhpSpreadsheet đọc XLSM.
- Node.js 20.19+ hoặc 22.12+.
- MySQL 8.0+ (InnoDB, `utf8mb4`).

## Chạy backend

```powershell
cd backend
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
composer install
php artisan key:generate
```

`php artisan db:seed` nạp bảng giá, quy tắc nghiệp vụ và danh mục tồn kho ảnh. Các commission tham chiếu Excel được đánh dấu `needs_reconciliation=true` cho đến khi đối chiếu workbook gốc. Lịch sử ảnh chỉ bắt đầu từ 09/11/2026; không seed giá ảnh cho ngày trước đó.

Trong HeidiSQL, tạo database `cao_thi_management` với charset `utf8mb4` và collation `utf8mb4_unicode_ci`. Điền thông tin máy chủ MySQL và tài khoản được cấp vào `backend/.env` (`DB_CONNECTION=mysql`, cổng mặc định `3306`), sau đó chạy migrations theo cách an toàn:

```powershell
php artisan migrate --force
php artisan db:seed
```

Trước tiên xác nhận cấu hình và trạng thái hiện tại bằng `php artisan config:clear`, `php artisan db:show` và `php artisan migrate:status`. `migrate` chỉ áp dụng các migrations chưa chạy và không xóa bảng/dữ liệu hiện có. Chỉ chạy khi host, tài khoản và database đã được xác nhận; sao lưu database trước khi áp dụng thay đổi schema. Không dùng `migrate:fresh`, `migrate:refresh` hoặc `db:wipe` trên database có dữ liệu. Import Excel lịch sử cần bổ sung các dòng qua quy trình preview/commit; không đưa dữ liệu lịch sử vào seeder.

Tạo ba tài khoản khởi tạo bằng ba mật khẩu riêng cấu hình tạm qua biến môi trường. `UserSeeder` không đặt lại mật khẩu tài khoản đã có; tài khoản bootstrap được gán về role tương ứng:

```powershell
$env:SEED_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new("", (Read-Host "Mật khẩu Admin" -AsSecureString)).Password
$env:SEED_MANAGER_PASSWORD = [System.Net.NetworkCredential]::new("", (Read-Host "Mật khẩu Manager" -AsSecureString)).Password
$env:SEED_STAFF_PASSWORD = [System.Net.NetworkCredential]::new("", (Read-Host "Mật khẩu Staff" -AsSecureString)).Password
php artisan db:seed --class=UserSeeder
Remove-Item Env:SEED_ADMIN_PASSWORD, Env:SEED_MANAGER_PASSWORD, Env:SEED_STAFF_PASSWORD
```

Seeder từ chối mật khẩu thiếu, ngắn hoặc trùng nhau. Role được lưu bằng enum hiện có trong `users.role` (`ADMIN`, `MANAGER`, `STAFF`); dự án không có bảng roles/permissions hoặc Spatie Permission. Chạy API:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

## Chạy frontend

```powershell
cd frontend
npm install
npm run dev
```

Mặc định frontend dùng API tại `http://localhost:8000`. Có thể đặt `VITE_API_BASE_URL` trong `frontend/.env.local`. `FRONTEND_URLS` và `SANCTUM_STATEFUL_DOMAINS` backend cần khớp origin frontend khi đổi host/port. HTTPS production phải bật `SESSION_SECURE_COOKIE=true`, đặt `SESSION_DOMAIN` phù hợp và chỉ cho phép origin production tin cậy.

Đăng nhập dùng Sanctum SPA session cookie HttpOnly và CSRF cookie. Frontend không lưu token hoặc dữ liệu tài chính vào localStorage.

## Phạm vi đã triển khai

- Đăng nhập/đăng xuất session, giới hạn thử đăng nhập, đổi mật khẩu, kiểm tra tài khoản đang hoạt động.
- Ba role Admin/Manager/Staff, API quản lý user, audit log cơ bản và route guard phía frontend.
- Quản lý đoàn và HDV; phép tính vé, công chèo, hoa hồng, ảnh/áo dài ở backend; duyệt, chốt, mở khóa có lý do.
- Dashboard theo ngày/tháng/lũy kế, phân quyền số liệu và đối chiếu hình thức thu vé.
- Sổ khoản chi, tồn kho, biến động kho và cập nhật tồn ảnh trong transaction cùng thao tác đoàn.
- Bảng giá ảnh theo ngày hiệu lực, xem lịch sử dành cho Admin và giữ nguyên snapshot giá của đoàn đã lưu.

## Import Excel lịch sử

Admin dùng `/admin/import-excel` để tải workbook XLSM, xem preview, các dòng cần đối soát và lịch sử từng lô. API chỉ đọc sheet `NHAT KY DOAN`, không chạy VBA, nhận diện workbook bằng SHA-256 và không ghi đè ngày/mã đoàn đã có. Dữ liệu đoàn được giữ snapshot, chờ đối soát và chưa tính vào Dashboard; Admin cần thực hiện bước bật Dashboard riêng sau khi đối chiếu.

Sheet `NHAP THEO NGAY` và các sheet tổng hợp không được nhập. Dòng trong `LUU CHI PHI CU` được lưu riêng dưới dạng dữ liệu lưu trữ, không cộng vào báo cáo. Ảnh lịch sử không tạo bút toán xuất kho vì chưa có xác nhận tồn đầu kỳ. Chỉ các dòng không có lỗi mới đủ điều kiện import; các dòng còn vướng được giữ trong preview cùng dữ liệu nguồn và không bị sửa.

Workbook hiện có tại `reference/CAO THỊ_QUẢN LÝ THU CHI_FINAL (2).xlsm`. Phân tích schema, cách mapping và sai lệch đã phát hiện nằm trong [`docs/excel-analysis.md`](docs/excel-analysis.md). Backup/restore MySQL, xuất CSV/XLSX, báo cáo chuyên sâu và giao diện quản trị lịch sử hoa hồng chưa triển khai.

## Dữ liệu demo

Seeder demo chỉ được phép chạy trong môi trường `local` hoặc `testing`, có nhãn `DEMO-SEED` trên HDV, đoàn, khoản chi và bút toán kho. Seeder dừng nếu phát hiện đoàn/chi phí thực trong khoảng demo hoặc tồn kho ảnh không còn cô lập. Hãy seed ba tài khoản trước (đặt đủ biến `SEED_*_PASSWORD` nếu còn thiếu tài khoản), rồi chạy riêng bằng lệnh Laravel chính thức:

```powershell
php artisan db:seed --class=DemoSeeder
```

Lệnh này tạo 15 HDV, 30 đoàn từ 24/10/2026 đến 11/11/2026, bút toán nhập/xuất ảnh và 8 khoản chi. `DatabaseSeeder` mặc định không gọi dữ liệu demo.

## Kiểm tra

Backend feature tests dùng SQLite in-memory để không ghi vào database MySQL cấu hình trong `.env`. Chạy các lệnh này không tạo hoặc xóa dữ liệu MySQL.

```powershell
cd backend
php artisan test
vendor/bin/pint --format agent

cd ../frontend
npm run build
```

Các route xác thực hiện có: `POST /api/login`, `POST /api/logout`, `GET /api/me`, `PUT /api/profile`, `PUT /api/change-password`. Quản trị user nằm dưới `/api/admin/users` và giới hạn Admin ở middleware backend.

## Excel lịch sử

Chưa có đoàn lịch sử nào được ghi vào database kinh doanh. Admin cần xem preview và xác nhận import theo từng lô; các dòng cần đối soát phải được xử lý trước khi đưa số liệu vào Dashboard.
