<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ForumMainCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $roles = ['admin', 'moderator', 'user', ''];
        
        $categories = [
            ['name' => 'Thảo luận chung', 'description' => 'Nơi thảo luận các chủ đề chung'],
            ['name' => 'Học tập', 'description' => 'Chia sẻ kiến thức và học hỏi'],
            ['name' => 'Hoạt động', 'description' => 'Các hoạt động của trường'],
            ['name' => 'Giải trí', 'description' => 'Nơi giải trí và thư giãn'],
            ['name' => 'Tin tức', 'description' => 'Tin tức mới nhất'],
        ];

        $arrange = 1;
        foreach ($categories as $category) {
            DB::table('cyo_forum_main_categories')->insert([
                'arrange' => $arrange++,
                'name' => $category['name'],
                'description' => $category['description'],
                'slug' => \Str::slug($category['name']),
                'role_restriction' => $faker->randomElement($roles),
                'background_image' => $faker->optional(0.3) 
                    ? 'https://placehold.co/800x400' 
                    : null,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        // Create additional random categories (20-30 total)
        $additionalCount = rand(15, 25);
        for ($i = 0; $i < $additionalCount; $i++) {
            $name = $faker->words(rand(2, 4), true);
            DB::table('cyo_forum_main_categories')->insert([
                'arrange' => $arrange++,
                'name' => ucwords($name),
                'description' => $faker->optional(0.8)->sentence(),
                'slug' => \Str::slug($name),
                'role_restriction' => $faker->randomElement($roles),
                'background_image' => $faker->optional(0.3) 
                    ? 'https://placehold.co/800x400' 
                    : null,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created " . (count($categories) + $additionalCount) . " forum main categories.");
    }
}

