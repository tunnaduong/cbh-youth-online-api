# Hệ thống Cached Points - Tối ưu Performance

## Tổng quan

Hệ thống cached points được thiết kế để tối ưu hiệu suất của việc tính toán điểm người dùng. Thay vì tính toán lại mỗi lần gọi API, hệ thống sẽ cache điểm vào database và tự động cập nhật khi có thay đổi.

## Các thành phần chính

### 1. Database Schema

- **Cột mới**: `cached_points` trong bảng `cyo_auth_accounts`
- **Migration**: `2025_10_05_152402_add_cached_points_to_auth_accounts_table.php`

### 2. PointsService

- **File**: `app/Services/PointsService.php`
- **Chức năng**: Quản lý việc tính toán và cache điểm
- **Methods**:
  - `calculatePoints($userId)` - Tính điểm cho user
  - `updateUserPoints($userId)` - Cập nhật cached points cho user
  - `refreshAllPoints()` - Refresh tất cả points
  - `getTopUsers($limit)` - Lấy top users sử dụng cached data

### 3. Model Events

Tự động cập nhật points khi có thay đổi:

- **Topic**: Khi tạo/xóa bài viết
- **TopicVote**: Khi vote/unvote
- **TopicComment**: Khi tạo/xóa comment
- **UserPointDeduction**: Khi có trừ điểm

### 4. API Endpoints

#### Public Routes

```bash
GET /api/points/top-users          # Lấy top 8 users
GET /api/points/top-users/{limit}  # Lấy top N users
```

#### Admin Routes

```bash
POST /api/points/refresh-all       # Refresh tất cả points
POST /api/points/refresh-user/{id} # Refresh points cho user cụ thể
```

### 5. Artisan Commands

#### Populate cached points ban đầu

```bash
php artisan points:populate
php artisan points:populate --force  # Force update
```

#### Refresh tất cả points

```bash
php artisan points:refresh-all
```

## Cách sử dụng

### 1. Setup ban đầu

```bash
# Chạy migration
php artisan migrate

# Populate cached points cho tất cả users
php artisan points:populate
```

### 2. Setup cron job (tùy chọn)

```bash
# Chạy script setup cron
./scripts/setup-cron.sh

# Hoặc thêm manual vào crontab
*/30 * * * * cd /path/to/project && php artisan points:refresh-all
```

### 3. Sử dụng trong code

#### Lấy top users (nhanh)

```php
use App\Services\PointsService;

$topUsers = PointsService::getTopUsers(8);
```

#### Cập nhật points cho user cụ thể

```php
use App\Services\PointsService;

PointsService::updateUserPoints($userId);
```

#### Lấy cached points của user

```php
$user = AuthAccount::find($userId);
$points = $user->getCachedPoints();
```

## Performance Benefits

### Trước khi tối ưu

- Mỗi lần gọi `getTop8ActiveUsers()` phải:
  - Query tất cả users
  - Tính toán points cho từng user (3 queries/user)
  - Sort và limit
- **Tổng**: ~600+ queries cho 200 users

### Sau khi tối ưu

- Chỉ 1 query duy nhất:
  ```sql
  SELECT * FROM cyo_auth_accounts
  WHERE role != 'admin'
  ORDER BY cached_points DESC
  LIMIT 8
  ```
- **Tổng**: 1 query

## Tự động cập nhật

Hệ thống tự động cập nhật cached points khi:

- User tạo/xóa bài viết
- User nhận vote/unvote
- User tạo/xóa comment
- Admin trừ điểm user

## Monitoring

### Logs

- Tất cả lỗi được log vào `storage/logs/laravel.log`
- Có thể monitor qua Laravel Telescope (nếu có)

### Manual refresh

```bash
# Refresh tất cả
php artisan points:refresh-all

# Refresh user cụ thể
php artisan points:refresh-user {userId}
```

## Troubleshooting

### 1. Points không cập nhật

- Kiểm tra model events có được trigger không
- Chạy manual refresh: `php artisan points:refresh-all`

### 2. Performance vẫn chậm

- Kiểm tra index trên `cached_points` column
- Đảm bảo cron job chạy đúng

### 3. Data không đồng bộ

- Chạy `php artisan points:populate --force` để reset tất cả

## Migration từ hệ thống cũ

Nếu đang sử dụng method `points()` cũ:

1. Chạy migration
2. Populate cached points
3. Cập nhật code sử dụng `getCachedPoints()` thay vì `points()`
4. Test thoroughly

## Best Practices

1. **Luôn sử dụng cached points** cho display
2. **Chỉ tính toán real-time** khi cần thiết
3. **Setup cron job** để đảm bảo data đồng bộ
4. **Monitor logs** để phát hiện lỗi sớm
5. **Backup trước khi migrate** production
