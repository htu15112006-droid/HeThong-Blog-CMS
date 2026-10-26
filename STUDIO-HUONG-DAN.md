# CMS Studio cho hệ thống Blog CMS

Giao diện WordPress thực sự dùng database và các dịch vụ Docker hiện có. Không phải trang HTML giả lập hoặc trang chỉ có nút mở công cụ khác.

## Chức năng

- Trang chủ blog đáp ứng màn hình điện thoại, danh sách bài và phân trang.
- Đọc bài, tìm kiếm, lọc danh mục, trang chủ đề và giới thiệu.
- Đăng nhập bằng tài khoản WordPress hiện có.
- Cùng giao diện cho tổng quan, tạo/sửa bài, lưu nháp/xuất bản, chuyển bài vào thùng rác.
- Thêm/sửa/xóa danh mục; giữ danh mục mặc định.
- Quản trị viên xem danh sách thành viên và tạo tài khoản tác giả, biên tập viên hoặc người theo dõi.
- Trang database hiển thị bảng và dữ liệu bài viết thật, không lộ hash mật khẩu.
- Metrics CPU, RAM, kết nối MySQL, trạng thái target và Nginx, biểu đồ HTTP từ Prometheus.
- Ba bộ lọc log Nginx từ Loki: truy cập, 404 và đếm yêu cầu 5 phút.

## Địa chỉ

| Chức năng | Đường dẫn |
|---|---|
| Blog | `/` |
| Đăng nhập | `/?cms=login` |
| Tổng quan | `/?cms=dashboard` |
| Bài viết | `/?cms=posts` |
| Viết bài | `/?cms=edit` |
| Danh mục | `/?cms=categories` |
| Người dùng | `/?cms=users` |
| Dữ liệu MySQL | `/?cms=database` |
| Giám sát | `/?cms=monitoring` |
| Nhật ký | `/?cms=logs` |

Các màn hình quản trị cùng một website và cùng phiên đăng nhập WordPress. Quản trị nâng cao WordPress vẫn có ở `/wp-admin/`. Grafana, Prometheus và phpMyAdmin gốc vẫn được giữ để demo các công cụ theo yêu cầu môn học. Trang database tích hợp là màn hình xem dữ liệu, không phải trình chạy SQL tùy ý hay toàn bộ phpMyAdmin. Trang giám sát tích hợp không thay thế trình sửa dashboard Grafana. Trình soạn bài dùng văn bản/HTML cơ bản; khi sửa bài có Gutenberg markup cần giữ markup hoặc dùng trình soạn WordPress gốc để bảo toàn các khối phức tạp.

## Cài vào máy ảo đang chạy

Trong Ubuntu, từ thư mục đã giải nén bản nâng cấp:

```bash
bash install-studio.sh ~/qtri_csdl_lab
```

Script kiểm tra WordPress, ghi lại giao diện đang dùng trong `.studio-backups`, sao chép theme vào volume WordPress và kích hoạt theme. Không đổi mật khẩu, xóa dữ liệu hoặc khởi tạo lại database. Có thể chạy khi các dịch vụ khác đang hoạt động. Theme lấy bài viết thật hiện có; cài theme không tự tạo bài mẫu.

Khôi phục giao diện trước lần cài gần nhất:

```bash
bash rollback-studio.sh ~/qtri_csdl_lab
```

Không chạy lại cài đặt nhiều lần chỉ để kiểm tra: bản sao mới nhất sẽ là theme đang dùng ở lần chạy đó. Các bản trạng thái trước vẫn được giữ để lựa chọn thủ công khi cần.

## Kết nối mạng và quyền

WordPress cần cùng mạng default với Prometheus và Loki (đã có trong cấu hình hiện tại). Kết nối upstream cố định `http://prometheus:9090` và `http://loki:3100`. Không phải mở thêm cổng trên Windows, không cần iframe, không tắt security headers của Nginx.

REST routes `/cms-studio/v1/metrics` và `/cms-studio/v1/logs` chỉ cho `manage_options`, dùng cookie WordPress và REST nonce. Mọi thao tác ghi kiểm tra nonce và capability; tác giả chỉ sửa/xóa bài được WordPress cho phép. Không đưa mật khẩu DB hoặc token vào JavaScript. Các truy vấn upstream là allowlist, không có URL proxy hoặc SQL tự do do người dùng nhập.

Bộ lọc Loki giả định log Nginx giống log hiện tại, bắt đầu bằng địa chỉ IP, dấu gạch ngang và chứa HTTP/. Nếu đổi log_format cần cập nhật bộ lọc server. Không tự gán số 0 khi thiếu dữ liệu. Các lỗi upstream được hiển thị và không dùng số giả. HTTP request counter Nginx có thể gồm cả yêu cầu scrape stub_status.

## Windows

Bật máy ảo, chạy `docker compose up -d` trong `~/qtri_csdl_lab`, rồi mở PowerShell:

```powershell
ssh -p 2849 -o PreferredAuthentications=password -o PubkeyAuthentication=no -o ExitOnForwardFailure=yes -L 127.0.0.1:80:127.0.0.1:80 ubuntu@192.168.181.128
```

Giữ phiên SSH mở và vào `http://localhost/`. Blog, quản trị, database, metrics và log đều qua cổng này; không cần chuyển tiếp thêm 3000, 9090 hoặc 3100 để dùng màn hình tích hợp. Địa chỉ máy ảo phải còn đúng.

## Trước khi nộp

Kiểm tra bằng tài khoản quản trị và tác giả, thử một bản nháp rồi chuyển vào thùng rác. Kiểm tra thiếu quyền với tác giả trên database/metrics/logs. Cập nhật hình minh chứng và sơ đồ kiến trúc trong báo cáo theo bản nâng cấp. Giữ các dashboard và công cụ gốc để trình bày theo rubric.
