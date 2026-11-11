<?php

namespace Database\Seeders;

use App\Models\Topic;
use App\Models\AuthAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningTipsTopicSeeder extends Seeder
{
  private $schoolAccountId;
  private $subforumId = 6; // "Chia sẻ kinh nghiệm" subforum, adjust as needed

  public function run(): void
  {
    // Get or create school account
    $schoolAccount = AuthAccount::where('username', 'DoanTruongCBH')->first();
    if (!$schoolAccount) {
      $this->command->warn('School account (DoanTruongCBH) not found. Creating...');
      $schoolAccount = AuthAccount::create([
        'username' => 'DoanTruongCBH',
        'email' => 'doanpublish@example.com',
        'password' => bcrypt('secure-password'),
        'email_verified_at' => now(),
        'role' => 'admin',
      ]);
    }
    $this->schoolAccountId = $schoolAccount->id;

    $topics = [
      [
        'title' => '🎯 Nền Tảng Học Tập Hiệu Quả: Điều Kiện Tiên Quyết',
        'description' => 'Ngủ đủ, ăn sáng, môi trường yên tĩnh và cân bằng khó-dễ là những điều kiện tiên quyết để học tập hiệu quả. Khám phá cách tối ưu hóa những yếu tố này.',
        'content_md' => '# Nền Tảng Học Tập Hiệu Quả

## Điều Kiện Tiên Quyết

### 1. Yếu Tố Sinh Lý
- **Ngủ đủ giấc**: 8-9 tiếng mỗi đêm giúp não xử lý kiến thức
- **Ăn sáng hàng ngày**: Cung cấp năng lượng cho buổi học
- **Ăn nhiều rau quả**: Các chất dinh dưỡng hỗ trợ sự tập trung
- **Vận động thể chất**: Giảm căng thẳng và tăng năng lượng

### 2. Môi Trường Học
- Nơi yên tĩnh, vắng những yếu tố gây xao nhãng
- Bàn ghế thoải mái, đủ ánh sáng
- Có thể tạo một không gian cố định dành riêng cho học tập

### 3. Nguyên Tắc Cân Bằng Khó-Dễ
- **Tỷ lệ lý tưởng**: 70% kiến thức đã biết + 30% kiến thức mới
- **Tránh quá khó**: Dễ gây rối trí và nản lòng
- **Tránh quá dễ**: Dễ gây chán nản

Hãy đánh giá tình trạng hiện tại của bạn và cải thiện từng yếu tố một! 💪',
      ],
      [
        'title' => '🧠 Phù Hợp Với Kiểu Tư Duy: Bán Cầu Trái vs Phải',
        'description' => 'Bộ não của bạn có thiên về logic (bán cầu trái) hay sáng tạo (bán cầu phải)? Khám phá phương pháp học phù hợp với kiểu tư duy của mình.',
        'content_md' => '# Phù Hợp Với Kiểu Tư Duy

Mỗi người có kiểu tư duy khác nhau. Hãy tìm phương pháp học phù hợp!

## Nếu bạn thiên về bán cầu phải (sáng tạo, toàn cảnh)

### Phương Pháp Phù Hợp:
- 🎵 Hát hoặc ngân nga nội dung theo giai điệu quen thuộc
- 🔗 Liên hệ thông tin với trải nghiệm cá nhân
- 🎨 Dùng màu sắc và hình dáng khi ghi chú
- 🖼️ Xem video hoặc ảnh minh họa
- 💭 Tự do liên tưởng và sáng tạo ý tưởng

## Nếu bạn thiên về bán cầu trái (logic, chi tiết)

### Phương Pháp Phù Hợp:
- 📋 Chuyển thông tin thành danh sách có nhóm
- 🏷️ Dùng ký hiệu, số hiệu rõ ràng
- 📊 Dùng sơ đồ, biểu đồ và hình minh họa logic
- ✍️ Tự tái hiện bằng lời nói và vẽ
- 🔍 Phân tích chi tiết, tìm quy luật

## Mẹo Kết Hợp
Sử dụng cả hai bán cầu sẽ giúp bạn học hiệu quả hơn. Cân bằng giữa logic và sáng tạo là chìa khóa! 🔑',
      ],
      [
        'title' => '💻 14 Phương Pháp Học Trực Tuyến Hiệu Quả (P1)',
        'description' => 'Khám phá 7 phương pháp đầu tiên giúp bạn học online thành công: từ xác định mong muốn đến chuẩn bị bài trước.',
        'content_md' => '# 14 Phương Pháp Học Trực Tuyến Hiệu Quả (Phần 1)

## 1. Xác Định Mong Muốn Cá Nhân
- Học vì bản thân bạn muốn chứ không phải vì áp lực
- Động lực nội tại giúp bạn vượt qua những lúc khó khăn

## 2. Chọn Nền Tảng Học Phù Hợp
- Tìm website uy tín với đánh giá tốt
- Xem bài giảng mẫu trước khi đăng ký
- Đảm bảo nền tảng hỗ trợ tiếng Việt (nếu cần)

## 3. Lập Kế Hoạch Rõ Ràng
- Đánh giá năng lực hiện tại
- Đặt mục tiêu cụ thể và đo lường được
- Xây dựng lịch học linh hoạt theo ưu tiên

## 4. Duy Trì Động Lực Mỗi Ngày
- Xác định mục tiêu cụ thể cho từng ngày
- Tìm cách giải quyết khó khăn ngay khi gặp
- Chia sẻ tiến độ với người học khác

## 5. Đề Cao Tính Tự Giác
- Tập trung như khi ngồi lớp truyền thống
- Tránh xa các yếu tố gây xao nhãng
- Tự giác hơn là chỉ chờ có người thúc ép

## 6. Tham Gia Thảo Luận Trực Tuyến
- Học nhóm giúp điểm lại kiến thức
- Trao đổi ý tưởng rèn kỹ năng giao tiếp
- Nộp bài tập và nhận phản hồi

## 7. Chọn Không Gian Học Phù Hợp
- Ưu tiên nơi yên tĩnh (thư viện, phòng kín)
- Đủ ánh sáng tự nhiên hoặc đèn LED
- Bàn ghế thoải mái, dụng cụ đầy đủ

**Tiếp theo: 7 phương pháp còn lại ở bài viết sau!** 👉',
      ],
      [
        'title' => '💻 14 Phương Pháp Học Trực Tuyến Hiệu Quả (P2)',
        'description' => '7 phương pháp tiếp theo: từ chuẩn bị bài trước đến luyện kỹ năng đọc nhanh.',
        'content_md' => '# 14 Phương Pháp Học Trực Tuyến Hiệu Quả (Phần 2)

## 8. Chuẩn Bị Bài Trước
- Xem trước nội dung chính trước buổi học
- Ghi chú những phần chưa hiểu
- Chuẩn bị câu hỏi để hỏi giáo viên

## 9. Kết Hợp Nhiều Phương Pháp Học
- 👂 **Nghe**: Bài giảng, podcast, video
- 👀 **Nhìn**: Sách, ảnh, sơ đồ
- 💬 **Nói**: Thảo luận, giải thích cho người khác
- ✍️ **Viết**: Ghi chú, tóm tắt, làm bài tập

## 10. Nghỉ Ngơi Đúng Cách
- Nghỉ giải lao 5-10 phút sau mỗi 30-60 phút học
- Vận động nhẹ hoặc nghe nhạc
- Giữ năng lượng bằng cách uống nước, ăn nhẹ

## 11. Kết Hợp Với Thực Tế
- Áp dụng kiến thức vào tình huống thực
- Tìm ví dụ từ cuộc sống hàng ngày
- Thực hành để hiểu sâu hơn

## 12. Ghi Chép Thường Xuyên
- Lưu ý những ý quan trọng
- Dễ tra cứu mà không phải xem lại toàn bộ
- Tạo flashcard cho những phần khó

## 13. Dùng Liên Tưởng Để Ghi Nhớ
- Tạo liên kết logic giữa các thông tin
- Dùng hình ảnh sáng tạo để nhớ lâu hơn
- Liên hệ với kinh nghiệm cá nhân

## 14. Luyện Kỹ Năng Đọc Nhanh
- Đọc hiểu nhanh tiếp nhận nhiều thông tin
- Quét nhanh để lấy ý chính
- Đọc sâu những phần quan trọng

**Áp dụng từng phương pháp một để tìm ra cách học hiệu quất nhất cho bạn!** 🌟',
      ],
      [
        'title' => '🎯 Chiến Lược Ghi Nhớ: Nhịp Ôn Tập Tối Ưu',
        'description' => 'Không phải học càng nhiều càng tốt. Khám phá nhịp ôn tập khoa học giúp ghi nhớ lâu hơn và quên ít hơn.',
        'content_md' => '# Chiến Lược Ghi Nhớ Hiệu Quả

## 1. Tỷ Lệ Ghi Nhớ Theo Phương Pháp

| Phương Pháp | Ghi Nhớ |
|---|---|
| Đọc | 20% |
| Nghe | 30% |
| Nhìn | 40% |
| Nói | 50% |
| Làm | 60% |
| Kết hợp (nghe+nhìn+nói+làm) | 90% |

**Kết luận**: Kết hợp nhiều giác quan = ghi nhớ lâu hơn 💡

## 2. Nhịp Ôn Tập Tối Ưu (Spaced Repetition)

Đây là phương pháp dựa trên khoa học não bộ:

- **Ôn tập 1**: Sau **10 phút** học (ôn ngay)
- **Ôn tập 2**: Sau **48 giờ** (ôn lại 2 ngày sau)
- **Ôn tập 3**: Sau **7 ngày** (ôn lại 1 tuần sau)
- **Ôn tập 4**: Sau **30 ngày** (ôn lại 1 tháng sau)

### Ví Dụ Thực Tế:
- Thứ 2: Học từ vựng mới → Ôn ngay sau 10 phút
- Thứ 4: Ôn lại những từ học thứ 2
- Thứ 2 tuần sau: Ôn lại những từ học 1 tuần trước
- Thứ 2 tháng sau: Ôn lại những từ đã ôn 1 tháng trước

## 3. Kỹ Thuật Ghi Nhớ
- ✍️ Vẽ sơ đồ tư duy
- 🏷️ Dùng ký hiệu cho ý chính
- 📝 Tóm tắt bằng lời của mình
- ❓ Tự kiểm tra sau khi học
- 🔗 Liên hệ với trải nghiệm cá nhân

## 4. Công Cụ Hỗ Trợ
- 📱 Anki: Flashcard thông minh với spaced repetition
- 📚 Quizlet: Tạo flashcard và quiz
- 📓 Notion: Tổ chức ghi chú
- 🎯 Obsidian: Kết nối ý tưởng

**Nhớ rằng**: Không phải học nhiều mà phải học đúng cách! 🎓',
      ],
      [
        'title' => '⏰ Quản Lý Thời Gian: Phương Pháp Pomodoro',
        'description' => 'Tập trung 25 phút, nghỉ 5 phút. Phương pháp đơn giản nhưng hiệu quả để tăng năng suất học.',
        'content_md' => '# Phương Pháp Pomodoro: Quản Lý Thời Gian Hiệu Quả

## Cấu Trúc Cơ Bản

### 1 Pomodoro = 4 Giai Đoạn

| Giai Đoạn | Thời Gian | Hoạt Động |
|---|---|---|
| **Làm việc** | 25 phút | Tập trung hoàn toàn |
| **Nghỉ ngắn** | 5 phút | Uống nước, giãn cơ |
| **Làm việc** | 25 phút | Tiếp tục tập trung |
| **Nghỉ dài** | 15-30 phút | Sau 4 Pomodoro |

## Ví Dụ Một Ngày Học

```
09:00 - 09:25: Pomodoro 1 (Toán)
09:25 - 09:30: Nghỉ
09:30 - 09:55: Pomodoro 2 (Toán)
09:55 - 10:00: Nghỉ
10:00 - 10:25: Pomodoro 3 (Anh)
10:25 - 10:30: Nghỉ
10:30 - 10:55: Pomodoro 4 (Anh)
10:55 - 11:15: Nghỉ dài ⭐
11:15 - 11:40: Pomodoro 5 (Văn)
...
```

## Lợi Ích Của Pomodoro

✅ **Tập trung cao**: Biết mục tiêu rõ ràng cho 25 phút
✅ **Tránh mệt**: Nghỉ đều đặn giúp não phục hồi
✅ **Dễ bắt đầu**: Không quá khó khăn để bắt tay vào
✅ **Cải thiện năng suất**: Nhiều người báo cáo năng suất tăng 25%

## Linh Hoạt Điều Chỉnh

Không cần cứng nhắc với 25 phút:
- 📚 **Học lý thuyết**: 25-30 phút
- 💻 **Làm bài tập**: 50 phút (2 Pomodoro liên tiếp)
- 🎮 **Học công nghệ**: 20 phút (dễ bị xao nhãng)
- 📖 **Đọc sách**: 30-45 phút

## Ứng Dụng Hỗ Trợ
- **Forest**: Hình ảnh cây phát triển
- **Focus To-Do**: Kết hợp Pomodoro + TODO list
- **Be Focused**: Cho máy tính
- **Timer đơn giản**: Trên điện thoại

**Bí quyết**: Hãy tắt điện thoại hoặc để ở chế độ im lặng! 📵',
      ],
      [
        'title' => '🧘 Tập Trung Khi Học: Tạo Môi Trường Lý Tưởng',
        'description' => 'Loại bỏ yếu tố xao nhãng, thiết kế không gian học, chuẩn bị kỹ lưỡng - những bước để đạt được sự tập trung cao độ.',
        'content_md' => '# Tập Trung Khi Học: Môi Trường & Chuẩn Bị

## 🚫 Loại Bỏ Yếu Tố Xao Nhãng

### Những Yếu Tố Chính Cần Tránh
- ❌ **Điện thoại**: Để ở chế độ im lặng hoặc để ra chỗ khác
- ❌ **TV, radio**: Tiếng ồn làm mất tập trung
- ❌ **Mạng xã hội**: Facebook, TikTok, Instagram
- ❌ **Website gây xao nhãng**: YouTube, game online

### Công Cụ Chặn Xao Nhãng
- 🔒 **Freedom**: Chặn website gây xao nhãng toàn bộ
- 🚫 **LeechBlock NG**: Extension cho trình duyệt
- 📵 **App Blockers**: Chặn ứng dụng trên điện thoại
- 🏫 **Focus Mode**: Chế độ tập trung sẵn có

## 🏠 Thiết Kế Không Gian Học

### Yếu Tố Vật Lý
- ✅ Bàn ghế thoải mái (không quá xuống cấp)
- ✅ **Ánh sáng**: Tối thiểu 500 lux (tương đương ánh sáng tự nhiên)
- ✅ **Nhiệt độ**: 18-22°C (mát mẻ nhưng không quá lạnh)
- ✅ **Âm thanh**: Yên tĩnh hoặc nhạc không lời nhẹ nhàng
- ✅ **Không gian**: Dành riêng cho học tập

### Mẹo Nâng Cao
- 🎵 Nghe nhạc không lời nếu giúp bạn tập trung hơn
- 🌿 Cây xanh hoặc không khí trong lành
- 🎨 Trang trí đơn giản, không quá rực rỡ
- 📍 Tạo một chỗ cố định dành cho học tập

## 📋 Chuẩn Bị Kỹ Lưỡng

### Trước Khi Bắt Đầu
- ✅ Chuẩn bị **tất cả dụng cụ** cần thiết
- ✅ Uống nước, ăn nhẹ nếu cần
- ✅ Đi vệ sinh để tránh phải dừng giữa chừng
- ✅ Mở tất cả tài liệu cần học
- ✅ Đặt điện thoại ra xa

### Checklist Chuẩn Bị
- [ ] Nước uống
- [ ] Dụng cụ học tập (bút, giấy, máy tính)
- [ ] Tài liệu học
- [ ] Đồng hồ (hoặc ứng dụng Pomodoro)
- [ ] Điện thoại: im lặng hoặc để ra chỗ khác
- [ ] Cửa: đóng lại để tránh tiếng ồn

## 💡 Bí Quyết Bổ Sung

1. **Khởi động**: Đọc lại ghi chú 5 phút trước khi học
2. **Momentum**: Cách tốt nhất để bắt đầu là... bắt đầu!
3. **Kéo dài**: Nếu tập trung tốt, có thể học thêm 5 phút
4. **Thay đổi vị trí**: Đôi khi thay đổi chỗ học cũng tốt

**Nhớ**: Tập trung là kỹ năng, có thể huấn luyện! 💪',
      ],
      [
        'title' => '📊 Tỷ Lệ Ghi Nhớ: Từ 20% Lên Tới 90%',
        'description' => 'Bạn sẽ nhớ bao nhiêu tùy vào cách bạn học. Hãy kết hợp nhiều phương pháp để tăng tỷ lệ ghi nhớ lên tới 90%.',
        'content_md' => '# Tỷ Lệ Ghi Nhớ Theo Phương Pháp Học

## 📈 Biểu Đồ Ghi Nhớ

Một nghiên cứu về cách học cho thấy:

| Phương Pháp | Tỷ Lệ Ghi Nhớ | Ví Dụ |
|---|---|---|
| 📖 **Đọc** | **20%** | Đọc sách, bài giảng viết |
| 👂 **Nghe** | **30%** | Nghe bài giảng, podcast |
| 👀 **Nhìn** | **40%** | Xem video, sơ đồ, ảnh |
| 💬 **Nói** | **50%** | Thảo luận, giải thích cho người khác |
| ✋ **Làm** | **60%** | Làm bài tập, dự án thực tế |
| **Kết hợp tất cả** | **90%** | Nghe + Nhìn + Nói + Làm |

## 🎯 Ý Nghĩa Thực Tế

### Nếu Bạn Chỉ Đọc (20%)
- ❌ Sau 1 tuần: Nhớ 20% → Quên 80%
- ❌ Phương pháp kém hiệu quả nhất

### Nếu Bạn Chỉ Nghe (30%)
- ⚠️ Nghe bài giảng nhưng không ghi chú
- ⚠️ Sau 1 tuần: Nhớ 30% → Quên 70%

### Nếu Bạn Kết Hợp Tất Cả (90%)
- ✅ Nghe bài giảng (30%)
- ✅ Nhìn sơ đồ, hình ảnh (40%)
- ✅ Nói lại bằng lời mình (50%)
- ✅ Làm bài tập, thực hành (60%)
- ✅ **Tổng = 90% ghi nhớ!**

## 💡 Cách Áp Dụng

### Ví Dụ: Học Môn Toán

**Cách kém hiệu quả (chỉ 20-30%)**:
```
Đọc sách toán → Xong
```

**Cách hiệu quả (90%)**:
```
1. 👂 Nghe bài giảng trực tuyến (30%)
2. 👀 Xem giáo viên giải ví dụ (40%)
3. ✍️ Ghi chú và vẽ sơ đồ (thêm)
4. 💬 Giải thích bài cho bạn bè (50%)
5. ✋ Làm bài tập + đề trắc nghiệm (60%)
```

**Kết quả**: Nhớ ~90% bài học! 🎉

## 🔧 Công Thức Kết Hợp Tối Ưu

1. **Nghe + Nhìn**: 30% + 40% = 70% ghi nhớ
2. **Nghe + Nhìn + Nói**: 30% + 40% + 50% = 90%+
3. **Nghe + Làm**: 30% + 60% = 80%
4. **Tất cả**: 30% + 40% + 50% + 60% = **90%+**

## 📚 Mẹo Áp Dụng Cho Mỗi Môn

**Ngôn ngữ**: Nghe (podcast) + Nói (luyện phát âm) + Làm (bài tập)
**Toán**: Nghe (bài giảng) + Nhìn (ví dụ) + Làm (bài tập nhiều)
**Lịch sử**: Nghe (bài giảng) + Nhìn (ảnh/video) + Nói (thảo luận)
**Khoa học**: Nghe + Nhìn (thí nghiệm) + Làm (thực hành lab)

**Kết luận**: Bạn có thể tăng ghi nhớ gấp 4-5 lần bằng cách kết hợp các phương pháp! 🚀',
      ],
      [
        'title' => '⏰ 5 Quy Tắc Quản Lý Thời Gian Để Tăng Năng Suất',
        'description' => 'Không phải mọi người đều có 24 giờ nhiều như nhau. Hãy áp dụng 5 quy tắc này để tối đa hóa thời gian học của bạn.',
        'content_md' => '# 5 Quy Tắc Quản Lý Thời Gian Để Tăng Năng Suất

## 📌 Quy Tắc 1-3-5: Ưu Tiên Rõ Ràng

### Cấu Trúc Mỗi Ngày
- **1 việc quan trọng nhất**: Ưu tiên hoàn thành
- **3 việc trung bình**: Mục tiêu thứ cấp
- **5 việc nhỏ**: Những công việc nhanh

### Ví Dụ Một Ngày
```
1 việc quan trọng: Hoàn thành đề cương toán
3 việc trung bình: Ôn anh văn, làm bài tập lý, ghi chú lịch
5 việc nhỏ: Email, dọn bàn, trả lời tin nhắn, ăn trưa, giãn cơ
```

### Lợi Ích
✅ Không bị quá tải
✅ Có cảm giác thành tựu
✅ Tập trung vào những thứ quan trọng nhất

## 🎯 Quy Tắc 80/20 (Pareto)

### Nguyên Tắc
- **20% nỗ lực** → **80% kết quả**
- 80% nỗ lực còn lại → chỉ 20% kết quả

### Áp Dụng Học Tập
```
❌ Không hiệu quả:
- Học 100% nội dung bài học
- Mất 10 giờ để lấy 8 giờ hữu ích

✅ Hiệu quả:
- Tập trung vào 20% nội dung quan trọng nhất
- Mất 2 giờ để lấy được 8 giờ kết quả
```

### Mẹo Nhận Diện 20% Quan Trọng
- Đọc câu hỏi thi từ năm trước
- Hỏi giáo viên phần nào quan trọng nhất
- Đọc mục lục và phần tóm tắt

## ⏱️ Quy Tắc Pomodoro + Buffer

### Công Thức
```
(25 phút học) + (5 phút nghỉ) = 1 Pomodoro

4 Pomodoro → 1 cuối tuần dài 15-30 phút
```

### Lịch Học Thực Tế
```
09:00 - 09:25: Pomodoro 1
09:25 - 09:30: Nghỉ
09:30 - 09:55: Pomodoro 2
09:55 - 10:00: Nghỉ
10:00 - 10:25: Pomodoro 3
10:25 - 10:30: Nghỉ
10:30 - 10:55: Pomodoro 4
10:55 - 11:15: NGHỈ DÀI ⭐
```

## 🚫 Quy Tắc Không Đa Nhiệm

### Vấn Đề Của Đa Nhiệm
```
Liên tục đổi việc:
- Làm toán 5 phút
- Chuyển sang anh văn
- Xem điện thoại
- Quay lại toán
→ KHÔNG hoàn thành cái nào cả! ❌
```

### Sự Thật Khoa Học
- Não cần **5-15 phút** để focus lại
- Mỗi lần chuyển = **mất 5-15 phút** năng suất
- Tập trung một việc → hoàn thành **nhanh hơn 40%**

### Giải Pháp
✅ Tập trung một môn cho 25 phút (Pomodoro)
✅ Chỉ sau Pomodoro mới chuyển việc

## 📅 Lập Kế Hoạch Tuần

### Mô Hình Hiệu Quả
```
Thứ 2-4: Tập trung vào những phần khó
Thứ 5-6: Ôn luyện và làm bài tập
Thứ 7-CN: Ôn cuối tuần, chuẩn bị tuần tới
```

### Lợi Ích
✅ Phần khó học khi não tỉnh táo nhất
✅ Phần dễ ôn luyện khi có nền tảng rồi
✅ Cuối tuần để tổng hợp và chuẩn bị

## 💡 Bí Quyết Thêm

1. **Biết từ chối**: Học từ chối những việc làm mất thời gian
2. **Buffer time**: Thêm 10% thời gian cho những bất ngờ
3. **Theo dõi**: Ghi lại thời gian thực tế để điều chỉnh
4. **Linh hoạt**: Điều chỉnh lịch khi cần thiết

**Nhớ**: Thời gian là tài nguyên quý giá nhất của bạn! ⏳',
      ],
    ];

    foreach ($topics as $topicData) {
      Topic::create([
        'user_id' => $this->schoolAccountId,
        'subforum_id' => $this->subforumId,
        'title' => $topicData['title'],
        'description' => $topicData['description'],
        'content_html' => $this->markdownToHtml($topicData['content_md']),
        'status' => 'published',
      ]);
    }

    $this->command->info('✅ Successfully created ' . count($topics) . ' learning tips topics!');
  }

  private function markdownToHtml(string $markdown): string
  {
    // Simple markdown to HTML conversion
    // In production, use parsedown or similar library
    $html = $markdown;

    // Convert headers
    $html = preg_replace('/^# (.+?)$/m', '<h1>$1</h1>', $html);
    $html = preg_replace('/^## (.+?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^### (.+?)$/m', '<h3>$1</h3>', $html);

    // Convert bold
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

    // Convert italic
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

    // Convert line breaks
    $html = nl2br($html);

    return $html;
  }
}
