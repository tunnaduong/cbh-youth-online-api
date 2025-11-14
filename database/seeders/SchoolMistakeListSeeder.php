<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SchoolMistakeListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        $mistakes = [
            ['type' => 'Đi muộn', 'penalty' => 5, 'description' => 'Đến lớp muộn không có lý do'],
            ['type' => 'Vắng mặt không phép', 'penalty' => 10, 'description' => 'Nghỉ học không có giấy phép'],
            ['type' => 'Không làm bài tập', 'penalty' => 3, 'description' => 'Không hoàn thành bài tập về nhà'],
            ['type' => 'Nói chuyện trong giờ', 'penalty' => 2, 'description' => 'Làm mất trật tự trong lớp'],
            ['type' => 'Không mặc đồng phục', 'penalty' => 5, 'description' => 'Vi phạm quy định đồng phục'],
            ['type' => 'Sử dụng điện thoại', 'penalty' => 10, 'description' => 'Sử dụng điện thoại trong giờ học'],
            ['type' => 'Gây gổ', 'penalty' => 20, 'description' => 'Gây gổ với bạn bè'],
            ['type' => 'Vứt rác bừa bãi', 'penalty' => 3, 'description' => 'Không giữ gìn vệ sinh'],
            ['type' => 'Quay cóp', 'penalty' => 30, 'description' => 'Vi phạm quy chế thi cử'],
            ['type' => 'Thiếu lễ phép', 'penalty' => 5, 'description' => 'Thiếu tôn trọng thầy cô'],
        ];

        foreach ($mistakes as $mistake) {
            DB::table('cyo_school_mistake_list')->insert([
                'description' => $mistake['description'],
                'mistake_type' => $mistake['type'],
                'point_penalty' => $mistake['penalty'],
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        // Create additional random mistakes (20-30 total)
        $additionalCount = rand(10, 20);
        for ($i = 0; $i < $additionalCount; $i++) {
            DB::table('cyo_school_mistake_list')->insert([
                'description' => $faker->sentence(),
                'mistake_type' => $faker->words(rand(2, 4), true),
                'point_penalty' => $faker->numberBetween(1, 50),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created " . (count($mistakes) + $additionalCount) . " mistake list items.");
    }
}

