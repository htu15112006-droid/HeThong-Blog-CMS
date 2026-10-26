# Đề 7 - Hệ thống Blog / CMS

## Giao diện website tích hợp

Theme `theme/cms-studio` cung cấp trang blog, đăng nhập, quản lý bài viết,
danh mục, người dùng, xem dữ liệu và giám sát/log trong cùng website.
Theme dùng tài khoản và dữ liệu WordPress hiện có.

Hướng dẫn cài đặt và khôi phục: [STUDIO-HUONG-DAN.md](STUDIO-HUONG-DAN.md).
Trên Ubuntu, sau khi tải repository, chạy:

```bash
bash install-studio.sh ~/qtri_csdl_lab
```

Chạy lệnh từ thư mục repository đã tải về, khi container WordPress đang hoạt động.
Nếu dùng Windows, giữ `Cai-website.cmd` và `cms-studio-upgrade.tar.gz` cùng thư mục.
Truy cập bằng địa chỉ `home`/`siteurl` đã cấu hình trong WordPress; với máy ảo
hiện tại có thể dùng `http://192.168.181.128/` sau khi đặt hai tùy chọn đó tương ứng.
Địa chỉ IP này thuộc mạng máy ảo, chưa phải website công khai trên Internet.

Triển khai WordPress và MySQL bằng Docker Compose, sử dụng Nginx
reverse proxy, Prometheus + Grafana để giám sát, Loki + Promtail
để thu thập và truy vấn log.

## Chức năng

- WordPress quản lý bài viết, danh mục và người dùng.
- MySQL lưu trữ dữ liệu; phpMyAdmin quản trị database.
- Nginx tiếp nhận yêu cầu HTTP và chuyển tới WordPress.
- Prometheus thu thập metrics từ node-exporter, cAdvisor và
  mysql-exporter.
- Grafana hiển thị dashboard và truy vấn log từ Loki.
- Promtail chuyển log container tới Loki.

Compose còn chứa PostgreSQL và pgAdmin phục vụ thực hành bổ sung;
database của WordPress trong đề 7 là MySQL.

## Cấu trúc

- docker-compose.yml: các dịch vụ, volume và mạng Docker.
- nginx/default.conf: reverse proxy và security headers.
- monitoring/prometheus.yml: cấu hình thu thập metrics.
- monitoring/mysql-exporter.cnf.example: cấu hình exporter mẫu.
- logging/loki.yml: cấu hình lưu log.
- logging/promtail.yml: cấu hình thu thập log.
- .env.example: danh sách biến môi trường cần thiết.

## Yêu cầu môi trường

Ubuntu có Docker Engine và Docker Compose plugin.
Các lệnh dưới đây chạy trong thư mục dự án trên Ubuntu.

## Chuẩn bị khi cài mới

1. Sao chép .env.example thành .env.
2. Thay toàn bộ giá trị REPLACE_WITH bằng mật khẩu riêng.
3. MYSQL_PASSWORD và WORDPRESS_DB_PASSWORD phải giống nhau.
4. Sao chép monitoring/mysql-exporter.cnf.example thành
   monitoring/mysql-exporter.cnf và đặt mật khẩu exporter.
5. Tạo tài khoản MySQL exporter cùng mật khẩu và cấp quyền
   giám sát phù hợp trước khi kiểm tra mysql_up.

File mẫu exporter không tự tạo tài khoản trong database.
Dữ liệu WordPress, tài khoản database và dashboard hiện có nằm
trong volume, không được sao chép chỉ bằng cách clone repository.

Không ghi đè .env hoặc cấu hình exporter của hệ thống đang chạy.
Đổi biến môi trường không tự đổi mật khẩu trong database đã có dữ liệu.

## Khởi động

    docker compose config --quiet
    docker compose up -d
    docker compose ps

Lần đầu cài mới cần hoàn tất trình cài đặt WordPress trên website.

## Truy cập từ trình duyệt trong Ubuntu

- Website: http://localhost
- Quản trị WordPress: http://localhost/wp-admin/
- phpMyAdmin: http://localhost:8082
- Prometheus: http://localhost:9090
- Grafana: http://localhost:3000
- cAdvisor: http://localhost:8081

localhost trong trình duyệt Windows là máy Windows, không phải Ubuntu.
phpMyAdmin chỉ lắng nghe trên loopback của máy Ubuntu.

Trong Grafana, địa chỉ nguồn dữ liệu bên trong Docker:
- Prometheus: http://prometheus:9090
- Loki: http://loki:3100

Các nguồn dữ liệu và dashboard cần được cấu hình nếu dùng volume mới.

## Kiểm tra

    curl -I http://localhost
    docker compose exec nginx nginx -t

Website cần trả về HTTP 200 và có các header:
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- Referrer-Policy: strict-origin-when-cross-origin

Truy vấn PromQL:
- up
- mysql_up

mysql_up cần bằng 1.

## Log tập trung

Trong Grafana Explore, chọn nguồn Loki.
Chọn stream log của container Nginx theo labels thực tế rồi:
- Xem log truy cập.
- Lọc yêu cầu trả về HTTP 404.
- Dùng count_over_time để đếm truy cập trong 5 phút.

Nếu lọc theo đường dẫn chứa container ID, cần chọn lại đường dẫn
sau khi container được tạo lại.

## Các biện pháp bảo mật đã cấu hình

- MySQL không publish cổng 3306 ra host.
- MySQL chỉ thuộc db_net có internal: true.
- WordPress và mysql-exporter kết nối cả default và db_net.
- Nginx chỉ thuộc mạng default.
- phpMyAdmin và Loki chỉ publish trên 127.0.0.1.
- Nginx bổ sung ba security headers.
- Các file cấu hình được mount chỉ đọc khi phù hợp.
- Mật khẩu thật nằm trong file cục bộ, không đưa lên Git.

Hệ thống lab vẫn có dịch vụ publish cổng trên mọi interface,
cAdvisor chạy privileged và Promtail chạy root.
Cấu hình này chưa phải cấu hình triển khai production hoàn chỉnh.

## Dừng và bảo toàn dữ liệu

    docker compose stop

Named volumes giữ dữ liệu khi container được tạo lại.
Không chạy docker compose down -v nếu cần giữ dữ liệu.
Không commit .env, cấu hình exporter thật hoặc bản sao lưu chứa bí mật.
