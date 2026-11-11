# 📚 Learning Tips Topics - Setup Guide

## Giới Thiệu

Hệ thống này cho phép **admin đăng ngẫu nhiên các bài viết về học tập** để tăng content forum một cách tự động.

## Cài Đặt

### Bước 1: Chạy Migration (nếu cần)

```bash
php artisan migrate
```

### Bước 2: Seed Dữ Liệu Ban Đầu

```bash
php artisan db:seed --class=LearningTipsTopicSeeder
```

**Kết quả**: Tạo **8 bài viết** về học tập vào database (trạng thái: draft/pending)

Topics được tạo:
1. 🎯 Nền Tảng Học Tập Hiệu Quả
2. 🧠 Phù Hợp Với Kiểu Tư Duy
3. 💻 14 Phương Pháp Học Trực Tuyến (P1)
4. 💻 14 Phương Pháp Học Trực Tuyến (P2)
5. 🎯 Chiến Lược Ghi Nhớ
6. ⏰ Pomodoro: Quản Lý Thời Gian
7. 🧘 Tập Trung Khi Học
8. 📊 Tỷ Lệ Ghi Nhớ
9. ⏰ 5 Quy Tắc Quản Lý Thời Gian

## Sử Dụng

### Random Publish Topics

#### Một Lần

```bash
# Publish 3 topic ngẫu nhiên (mặc định)
php artisan learning-tips:random-publish

# Publish 5 topic
php artisan learning-tips:random-publish --limit=5

# Publish 1 topic
php artisan learning-tips:random-publish --limit=1
```

#### Tự Động Hàng Ngày

Thêm vào `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Publish 1-2 learning tips topic mỗi ngày lúc 08:00
    $schedule->command('learning-tips:random-publish --limit=1')
        ->dailyAt('08:00');
}
```

Hoặc **2-3 times per week**:

```php
$schedule->command('learning-tips:random-publish --limit=2')
    ->mondays()
    ->wednesdays()
    ->fridays()
    ->at('08:00');
```

### Chọn Subforum

Mặc định topics được đăng vào subforum ID `6` ("Chia sẻ kinh nghiệm").

Để thay đổi, sửa file `database/seeders/LearningTipsTopicSeeder.php`:

```php
private $subforumId = 6;  // ← Thay đổi số này
```

Rồi chạy lại seeder hoặc update thủ công database.

## API Endpoints

### Danh Sách Learning Tips Topics

```bash
GET /v1.0/topics?search=🎯
```

### Đánh Giá Bài Viết

```bash
POST /v1.0/topics/{id}/ratings
{
  "rating": 5,
  "comment": "Bài viết rất hữu ích!"
}
```

## Tính Năng Bổ Sung

### 1. Tránh Publish Lặp Lại

Command sẽ **skip topics** đã được publish trong **7 ngày gần đây**.

```php
if ($topic->status === 'published' && $topic->updated_at->diffInDays() < 7) {
    // Skip nếu publish trong 7 ngày
}
```

### 2. Linh Hoạt Điều Chỉnh

Nếu muốn **publish ngay cả topics đã publish gần đây**:

```bash
# Edit command hoặc thêm --force flag
php artisan learning-tips:random-publish --limit=3 --force
```

### 3. Xem Thống Kê

```bash
php artisan tinker

> Topic::where('title', 'like', '%🎯%')->count()  // Total learning tips
> Topic::where('title', 'like', '%🎯%')->where('status', 'published')->count()  // Published
```

## Quản Lý Topics

### Thêm Topics Mới

Chỉnh sửa `database/seeders/LearningTipsTopicSeeder.php` - thêm mục trong mảng `$topics`:

```php
[
  'title' => '🎯 Tiêu đề Mới',
  'description' => 'Mô tả ngắn...',
  'content_md' => '# Tiêu đề\n\n Nội dung markdown...',
]
```

Rồi chạy:

```bash
php artisan db:seed --class=LearningTipsTopicSeeder
```

### Sửa Topics Hiện Tại

**Thông qua Admin API**:

```bash
PATCH /v1.0/admin/topics/{id}
{
  "title": "Tiêu đề mới",
  "content_html": "Nội dung HTML mới"
}
```

**Trực tiếp database**:

```sql
UPDATE cyo_topics 
SET title = 'Tiêu đề mới'
WHERE id = 123;
```

## Lịch Biểu Đề Xuất

### Hàng Ngày (1 topic)
```
Thứ 2-CN: 08:00 sáng → 1 bài mỗi ngày
```

### Hàng Tuần (3 topics)
```
Thứ 2, 3, 5: 08:00 sáng → 3 bài mỗi tuần
```

### Hai Tuần Một Lần (2 topics)
```
Thứ 2, Thứ 5: 08:00 sáng → 2 bài mỗi lần
```

## Troubleshooting

### ❌ "No learning tips topics found"

**Nguyên nhân**: Chưa chạy seeder

**Giải pháp**:
```bash
php artisan db:seed --class=LearningTipsTopicSeeder
```

### ❌ "All topics were skipped"

**Nguyên nhân**: Tất cả topics đều được publish trong 7 ngày gần đây

**Giải pháp**: 
- Chạy lại sau 7 ngày, hoặc
- Thêm `--force` flag (nếu implement)

### ❌ School account not found

**Nguyên nhân**: Tài khoản `DoanTruongCBH` không tồn tại

**Giải pháp**: Seeder sẽ tự tạo nếu không tìm thấy

## Thống Kê & Monitoring

### Topics Published Per Month

```sql
SELECT DATE_FORMAT(updated_at, '%Y-%m') as month, COUNT(*) as count
FROM cyo_topics
WHERE title LIKE '%🎯%' OR title LIKE '%🧠%' OR title LIKE '%💻%'
GROUP BY month
ORDER BY month DESC;
```

### Most Liked Topics

```sql
SELECT title, COUNT(*) as rating_count, AVG(rating) as avg_rating
FROM cyo_topics t
LEFT JOIN cyo_topic_ratings tr ON t.id = tr.topic_id
WHERE t.title LIKE '%🎯%'
GROUP BY t.id
ORDER BY avg_rating DESC;
```

## Ghi Chú

- 📝 Tất cả topics được tạo bằng **user account** `DoanTruongCBH`
- 🎯 Topics được đánh dấu với emoji để dễ nhận diện
- ✅ Mỗi topic có mô tả ngắn + nội dung markdown đầy đủ
- 🔄 Command có thể chạy multiple times mà không tạo duplicate
- 📊 Dễ theo dõi và thống kê engagement

## Support

Nếu có vấn đề, kiểm tra:
1. Logs: `storage/logs/laravel.log`
2. Database: Xem dữ liệu trong `cyo_topics`
3. Scheduler: Kiểm tra `ps aux | grep schedule:work`
