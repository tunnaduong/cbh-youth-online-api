# HƯỚNG DẪN PHÁT TRIỂN & ONBOARDING DÀNH CHO LẬP TRÌNH VIÊN MỚI

Chào mừng bạn đến với dự án **Chuyen Bien Hoa Youth Online (CYO)**! Đây là tài liệu hướng dẫn giúp bạn làm quen nhanh chóng với cấu trúc source code, cách thiết lập môi trường phát triển và nắm được các bước đi tiếp theo của dự án.

---

## 1. Tổng quan Dự án

**Chuyen Bien Hoa Youth Online (CYO)** là một nền tảng mạng xã hội và cổng thông tin học tập, hướng nghiệp dành cho học sinh trường THPT Chuyên Biên Hòa. Dự án kết hợp các tính năng diễn đàn truyền thống với các cơ chế mạng xã hội hiện đại như:
- **Diễn đàn & Bài viết (Topics & Comments):** Phân chia theo danh mục/diễn đàn con, có hệ thống bình chọn (upvote/downvote), tìm kiếm và lưu trữ bài viết.
- **Tương tác xã hội:** Trang cá nhân (User Profiles), theo dõi (followers/following), bảng tin hoạt động (Activity Feed) và Stories biến mất sau 24h.
- **Chat thời gian thực:** Trò chuyện riêng tư hoặc trò chuyện nhóm, hỗ trợ đính kèm tệp và trạng thái tin nhắn (read receipts).
- **Hệ thống Điểm thưởng (Points System):** Điểm tự động tích lũy qua hoạt động tương tác, có thể đổi thành điểm ví và quy đổi rút tiền thực tế hoặc mua tài liệu học tập.
- **Cửa hàng tài liệu (Study Materials):** Đăng tải, đánh giá, mua bán các tài liệu học tập bằng điểm thưởng.
- **Trang Quản trị (Admin Panel):** Dashboard quản trị toàn diện cho quản trị viên/giáo viên quản lý lớp học, thời khóa biểu, vi phạm của học sinh, duyệt báo cáo và giao dịch rút tiền.

---

## 2. Công nghệ sử dụng (Tech Stack)

Dự án được xây dựng trên mô hình nguyên khối hiện đại (Modern Monolith) kết hợp giữa PHP và JavaScript:

- **Backend:** 
  - **Laravel v10.x** & PHP 8.1+
  - **Eloquent ORM** (với các tối ưu hóa truy vấn nâng cao)
  - **Filament PHP v3** (Bộ thư viện xây dựng giao diện Admin Panel dựa trên Livewire)
- **Frontend:** 
  - **React v18**
  - **Inertia.js** (Giúp kết nối trực tiếp Laravel Backend và React Frontend mà không cần viết API Client riêng biệt)
  - **Vite** (Bộ công cụ build tài nguyên cực nhanh)
  - **Tailwind CSS** & **Ant Design (antd)** (Thư viện UI components chính)
- **Hệ thống thời gian thực (Real-time):** **Pusher** (hoặc Laravel Echo Server/Soketi cho local)
- **Tự động hóa thanh toán (Donation/Wallet):** Tích hợp cổng chuyển khoản tự động qua webhook **SEPay** (`sepayvn/laravel-sepay`)
- **Tài liệu API (API Docs):** **Dedoc Scramble** (Tự động tạo tài liệu API OpenAPI tại `/docs/api` mà không cần viết chú thích Swagger thủ công)
- **Push Notification:** **Expo Push Notifications** (cho Mobile App) và **Web Push** (cho trình duyệt)

---

## 3. Hướng dẫn Cài đặt Môi trường Phát triển (Local Setup)

Hãy làm theo các bước dưới đây để thiết lập dự án trên máy tính của bạn:

### Yêu cầu hệ thống
- **PHP** >= 8.1 (với đầy đủ các extension phổ biến như `pdo_mysql`, `mbstring`, `openssl`, `xml`, `gd`, `zip`)
- **Composer** (Quản lý thư viện PHP)
- **Node.js** >= 18 & **npm** (Quản lý thư viện frontend)
- **MySQL** hoặc **MariaDB** làm Database Server
- Một dịch vụ SMTP Mail ảo (như Mailtrap hoặc MailHog) để test gửi thư xác nhận email

### Các bước cài đặt chi tiết

1. **Clone mã nguồn từ kho lưu trữ:**
   ```bash
   git clone <repository-url>
   cd cbh-youth-online-api
   ```

2. **Cài đặt các gói phụ thuộc Backend (PHP):**
   ```bash
   composer install
   ```

3. **Cài đặt các gói phụ thuộc Frontend (JavaScript):**
   ```bash
   npm install
   ```

4. **Thiết lập file cấu hình môi trường:**
   Sao chép file `.env.example` thành `.env` và cập nhật thông tin cấu hình phù hợp với máy của bạn.
   ```bash
   cp .env.example .env
   ```
   **Các thông số quan trọng cần lưu ý trong `.env`:**
   - Cấu hình Database: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
   - Cấu hình Pusher (Real-time): `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER`.
   - Cấu hình Mail: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`.

5. **Tạo Application Key:**
   ```bash
   php artisan key:generate
   ```

6. **Liên kết thư mục Storage lưu trữ file:**
   ```bash
   php artisan storage:link
   ```

7. **Chạy Migration để tạo cấu trúc cơ sở dữ liệu:**
   ```bash
   php artisan migrate
   ```

8. **Nạp dữ liệu mẫu (Database Seeding):**
   Dự án sử dụng cơ chế nạp dữ liệu phân tầng 4 giai đoạn rất chi tiết. Hãy chạy câu lệnh sau để có dữ liệu thử nghiệm phong phú:
   ```bash
   php artisan db:seed
   ```
   *(Xem thêm tại [DatabaseSeeder.php](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/database/seeders/DatabaseSeeder.php) để hiểu trình tự nạp dữ liệu mẫu từ các bảng cơ sở đến các bảng phụ thuộc).*

9. **Chạy tài nguyên Frontend ở chế độ Dev:**
   Lệnh này sẽ khởi tạo Vite Dev Server để tự động reload UI khi bạn sửa code React/CSS.
   ```bash
   npm run dev
   ```

10. **Chạy ứng dụng Backend (Laravel Development Server):**
    ```bash
    php artisan serve
    ```
    Ứng dụng của bạn hiện đã sẵn sàng tại địa chỉ mặc định `http://127.0.0.1:8000`.

### Quyền truy cập Admin
Để truy cập giao diện quản trị tại `http://127.0.0.1:8000/admin`:
1. Mở database hoặc dùng công cụ quản lý DB, tìm bảng `cyo_auth_accounts`.
2. Chuyển giá trị trong cột `role` của tài khoản của bạn thành `'admin'`.
3. Hoặc bạn có thể sử dụng thông tin đăng nhập của tài khoản admin mẫu được tạo ra từ seeder [AuthAccountsSeeder.php](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/database/seeders/AuthAccountsSeeder.php).

---

## 4. Cấu trúc Thư mục Chính (Folder Structure)

Bạn cần lưu ý các thư mục quan trọng sau khi phát triển tính năng mới:

```
cbh-youth-online-api/
├── app/
│   ├── Console/Commands/       # Các câu lệnh Artisan tùy chỉnh (vd: populate điểm, backup...)
│   ├── Http/
│   │   ├── Controllers/        # Nơi xử lý logic nghiệp vụ chính (Phân chia theo Module)
│   │   └── Middleware/         # Các Middleware lọc Request (xác thực, đo người dùng online...)
│   ├── Models/                 # Các Eloquent Models định nghĩa cấu trúc bảng và liên kết (49+ models)
│   ├── Providers/              # Nơi đăng ký các service provider hệ thống
│   └── Services/               # Nơi chứa Business Logic tách biệt (Points, Notifications, Payments...)
├── config/                     # Các file cấu hình hệ thống (app, database, scramble, sepay...)
├── database/
│   ├── migrations/             # Lịch sử thay đổi cấu trúc database
│   └── seeders/                # Các seeder sinh dữ liệu mẫu chạy dev
├── docs/                       # Thư mục lưu trữ tài liệu hỗ trợ nhà phát triển (Thư mục hiện tại)
├── resources/
│   ├── js/                     # Nguồn mã React JS (Các Components, Pages, Layouts của Inertia)
│   └── views/                  # File giao diện Blade (app.blade.php là layout gốc cho Inertia)
├── routes/
│   ├── api.php                 # Các endpoint RESTful API (prefix: /api/v1.0/)
│   ├── web.php                 # Các route tải trang bằng Inertia & React
│   └── channels.php            # Định nghĩa các channel bảo mật của WebSockets (Pusher)
└── vite.config.js              # Cấu hình Vite bundler
```

---

## 5. Các Cơ chế Cốt lõi & Thiết kế Đặc thù (Core Mechanisms)

Dưới đây là một số cơ chế quan trọng của dự án mà lập trình viên mới bắt buộc phải nắm vững để không phá vỡ logic hệ thống:

### A. Hệ thống Tính điểm & Lưu trữ Điểm (Points System)
- **Thiết kế nguyên bản:** Trước đây hệ thống tính điểm theo cơ chế real-time nhưng hiệu năng rất chậm. Sau đó, dự án được tối ưu hóa sang cơ chế **Cached Points** bằng việc lưu điểm trực tiếp trong database (cột `points` ở bảng `cyo_auth_accounts`, được đổi tên từ cột `cached_points` cũ thông qua migration).
- **Cơ chế cập nhật tự động (Model Events):** Hệ thống sử dụng Model Events để tự động cộng/trừ điểm cho người dùng khi có hành vi tương ứng xảy ra:
  - Viết bài mới: `+10` điểm (xử lý qua event của `Topic`).
  - Xóa bài: `-10` điểm.
  - Bình luận trên bài viết: `+2` điểm (event của `TopicComment`).
  - Được người khác upvote bài viết: `+5` điểm (event của `TopicVote`).
  - Bị trừ điểm do vi phạm (Admin thực hiện): Trừ số điểm tương ứng (event của `UserPointDeduction`).
- **Sử dụng trong code:**
  - Lấy điểm hiện tại của user: `$user->getPoints()`.
  - Cộng/Trừ điểm thủ công: Sử dụng các static method trong class [PointsService](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/app/Services/PointsService.php):
    ```php
    PointsService::addPoints($userId, $amount, $type, $description, $relatedId);
    PointsService::deductPoints($userId, $amount, $type, $description, $relatedId);
    ```
- **Các câu lệnh Artisan hỗ trợ:**
  - `php artisan points:populate`: Nạp lại toàn bộ điểm cho người dùng dựa trên lịch sử hoạt động thực tế. Dùng tham số `--force` nếu muốn ghi đè lên dữ liệu điểm đang có.

---

### B. Chiến lược Tối ưu hóa Hiệu năng (Performance Optimization)
Dự án đã từng gặp phải hiện tượng nghẽn nghiêm trọng (truy vấn database mất hơn 9 giây). Do đó, hãy tuyệt đối tuân thủ các quy tắc tối ưu hóa hiệu năng sau khi phát triển:
1. **Khắc phục lỗi N+1 Query:** Khi lấy danh sách bài viết hoặc bình luận, luôn luôn load kèm thông tin quan hệ (Eager Loading) bằng method `with()`. Ví dụ: `AuthAccount::with(['profile'])->get()`. Tránh việc gọi quan hệ trong vòng lặp foreach.
2. **Cơ chế Cache Số liệu Thống kê:** Hạn chế đếm dữ liệu trực tiếp trong database ở mỗi request. Sử dụng class `StatsCacheService` để lấy số lượng user online, số lượng bài viết, danh sách top user. Các cache này có thời gian hết hạn (TTL) từ 1 đến 5 phút để tránh quá tải DB.
3. **Database Indexes:** Đảm bảo các trường lọc (`privacy`, `hidden`, `user_id`, `created_at`, `last_activity`, `session_id`) luôn được đánh chỉ mục (index) trong migration để hỗ trợ truy vấn siêu tốc.
4. **Tối ưu Middleware Theo dõi Trạng thái Online:** Middleware `UpdateOnlineUsers` đã được cấu hình cache cơ chế dọn dẹp phiên hoạt động (cleanup interval) 30 giây một lần để tránh việc thực thi query ghi/xóa liên tục mỗi khi user click chuyển trang.

---

### C. Tích hợp Cổng thanh toán (SEPay Integration)
Hệ thống tích hợp cổng thanh toán tự động qua **SEPay**:
- **Luồng hoạt động:** Khi người dùng chuyển khoản quyên góp/nạp tiền vào tài khoản ngân hàng của hệ thống với nội dung chuyển khoản đặc thù (chứa mã giao dịch hoặc cú pháp định danh), SEPay sẽ gửi một HTTP POST Request tới API Webhook của chúng ta tại endpoint tương ứng.
- **Xử lý phía backend:**
  - [SEPayWebhookController.php](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/app/Http/Controllers/SEPayWebhookController.php) tiếp nhận webhook request, xác thực chữ ký bảo mật và tính hợp lệ của giao dịch.
  - [SEPayWebhookService.php](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/app/Services/SEPayWebhookService.php) thực thi logic nghiệp vụ: Tạo bản ghi nạp tiền, tự động chuyển đổi số tiền VND sang điểm tương ứng (tỷ lệ 1.000 VND = 10 điểm) thông qua `PointsService::convertVNDToPoints()`, sau đó cộng điểm vào tài khoản người dùng và gửi thông báo xác nhận.

---

### D. Hệ thống Thông báo (Notification & Push)
- Khi có sự kiện xảy ra (thích bài viết, được nhắc tên, được phản hồi bình luận, tài liệu được mua/đánh giá), hệ thống sẽ gọi [NotificationService](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/app/Services/NotificationService.php) để tạo bản ghi thông báo.
- `NotificationService` đồng thời gọi sang [PushNotificationService](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/app/Services/PushNotificationService.php) để bắn thông báo đẩy (Push Notification) đến thiết bị di động của người dùng thông qua Expo Server và gửi email thông báo (nếu người dùng cài đặt cho phép).

---

## 6. Kế hoạch Phát triển Tiếp theo (Next Steps)

Khi tiếp nhận dự án này, bạn có thể tập trung triển khai và tối ưu hóa các phần công việc trọng tâm sau đây:

### 🚀 Nhiệm vụ 1: Viết Kiểm thử Tự động (Automated Tests)
Hiện tại dự án chưa có nhiều unit/integration tests cho các tính năng tùy chỉnh cốt lõi. Hãy bắt đầu bằng cách viết:
- Test cho [PointsService](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/app/Services/PointsService.php) để đảm bảo cộng/trừ điểm chính xác, đặc biệt là các logic giao dịch đồng thời (race conditions).
- Test cho [SEPayWebhookController](file:///c:/Users/Administrator/Documents/GitHub/cbh-youth-online-api/app/Http/Controllers/SEPayWebhookController.php) bằng cách giả lập request webhook gửi đến để kiểm tra cơ chế phân tích cú pháp nạp tiền.
- Chạy lệnh kiểm thử:
  ```bash
  php artisan test
  ```

### 🔒 Nhiệm vụ 2: Phân quyền Chi tiết (Authorization Policy)
- Rà soát các Controller và tạo các Laravel **Policies** tương ứng cho các model nhạy cảm như `Topic`, `TopicComment`, `StudyMaterial`, `WithdrawalRequest`.
- Đảm bảo người dùng chỉ được sửa/xóa bài viết hoặc tài liệu do chính mình tạo ra, ngoại trừ admin/moderator.

### ⚡ Nhiệm vụ 3: Nâng cấp Cơ chế Caching
- Mặc dù hệ thống đã có caching thông qua file/database, đối với môi trường production có lượng truy cập lớn của học sinh, hãy chuyển đổi cấu hình sang sử dụng **Redis** cho cả cache driver và session driver (`CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`).
- Thiết lập Redis connection và giám sát hiệu năng truy vấn trên server.

### 📱 Nhiệm vụ 4: Hoàn thiện Tích hợp Thông báo Đẩy (Push Notification SDK)
- Tiếp tục tích hợp sâu SDK Expo Push Token cho các thiết bị Android/iOS để học sinh có thể nhận thông tin tức thời khi có thông báo mới mà không cần mở trình duyệt.
- Tối ưu hóa hàng đợi gửi thông báo (Queueing) thông qua Laravel Queue (`QUEUE_CONNECTION=database` hoặc `redis`) để tránh làm chậm thời gian phản hồi của request chính từ phía client.

---

Chúc bạn có thời gian làm việc hiệu quả và phát triển thành công nhiều tính năng thú vị cho diễn đàn học sinh **Chuyen Bien Hoa Youth Online**! Nếu có khó khăn hay thắc mắc về luồng code, hãy tìm kiếm trong thư mục `tests/` hoặc xem các tài liệu hướng dẫn cụ thể trong dự án (như `POINTS_SYSTEM_README.md` hay `PERFORMANCE_OPTIMIZATION_GUIDE.md`).
