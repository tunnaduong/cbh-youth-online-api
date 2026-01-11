<?php

namespace Database\Seeders;

use App\Models\AuthAccount;
use App\Models\StudyMaterial;
use App\Models\StudyMaterialCategory;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class StudyMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $users = AuthAccount::all();
        $categories = StudyMaterialCategory::all();

        if ($users->isEmpty() || $categories->isEmpty()) {
            $this->command->warn("Please seed users and study material categories first.");
            return;
        }

        $materials = [
            [
                'title' => 'Tổng ôn công thức Toán học lớp 12',
                'description' => 'Tài liệu bao gồm đầy đủ các công thức đạo hàm, tích phân, hình học không gian phục vụ ôn thi THPT Quốc gia.',
            ],
            [
                'title' => 'Bộ đề thi thử Vật lý 2025 - Chuyên Biên Hòa',
                'description' => 'Tổng hợp 10 đề thi thử bám sát cấu trúc đề minh họa của Bộ Giáo dục điện tử.',
            ],
            [
                'title' => 'Sơ đồ tư duy Hóa học hữu cơ',
                'description' => 'Giúp học sinh dễ dàng ghi nhớ các phản ứng đặc trưng của Ankan, Anken, Ankin và Aren.',
            ],
            [
                'title' => '1000 từ vựng Tiếng Anh trọng tâm ôn thi đại học',
                'description' => 'Danh sách từ vựng hay xuất hiện trong các bài đọc hiểu và hoàn thành câu.',
            ],
            [
                'title' => 'Phân tích các tác phẩm văn học lớp 12',
                'description' => 'Bài viết phân tích chi tiết các tác phẩm Vợ chồng A Phủ, Chiếc thuyền ngoài xa, Tây Tiến...',
            ],
            [
                'title' => 'Cẩm nang giải nhanh trắc nghiệm Sinh học',
                'description' => 'Các mẹo bấm máy tính và phương pháp loại trừ đáp án nhanh cho phần Di truyền học.',
            ],
            [
                'title' => 'Tóm tắt lịch sử Việt Nam giai đoạn 1945 - 1975',
                'description' => 'Hệ thống hóa các sự kiện quan trọng theo dòng thời gian giúp dễ ôn tập.',
            ],
            [
                'title' => 'Kỹ năng đọc bản đồ và Atlat Địa lý Việt Nam',
                'description' => 'Hướng dẫn chi tiết cách khai thác số liệu từ Atlat để đạt điểm tối đa.',
            ],
        ];

        foreach ($materials as $item) {
            $isFree = $faker->boolean(60); // 60% chance to be free
            
            StudyMaterial::create([
                'user_id' => $users->random()->id,
                'category_id' => $categories->random()->id,
                'title' => $item['title'],
                'description' => $item['description'],
                'price' => $isFree ? 0 : $faker->numberBetween(10, 100),
                'is_free' => $isFree,
                'status' => 'published',
                'download_count' => $faker->numberBetween(0, 500),
                'view_count' => $faker->numberBetween(100, 2000),
                'preview_content' => $faker->paragraph(3),
            ]);
        }

        // Add some more random materials to make it diverse
        for ($i = 0; $i < 20; $i++) {
            $isFree = $faker->boolean(70);
            StudyMaterial::create([
                'user_id' => $users->random()->id,
                'category_id' => $categories->random()->id,
                'title' => "Tài liệu " . $faker->words(3, true),
                'description' => $faker->sentence(15),
                'price' => $isFree ? 0 : $faker->numberBetween(5, 50),
                'is_free' => $isFree,
                'status' => 'published',
                'download_count' => $faker->numberBetween(0, 100),
                'view_count' => $faker->numberBetween(10, 500),
            ]);
        }

        $this->command->info("Created " . (count($materials) + 20) . " study materials.");
    }
}
