<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ForumSubforumsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get main category IDs
        $mainCategoryIds = DB::table('cyo_forum_main_categories')->pluck('id')->toArray();
        $roles = ['admin', 'moderator', 'teacher', 'user', ''];
        
        // Create 25-35 subforums
        $count = rand(25, 35);
        
        for ($i = 0; $i < $count; $i++) {
            $name = $faker->words(rand(2, 4), true);
            DB::table('cyo_forum_subforums')->insert([
                'main_category_id' => $faker->randomElement($mainCategoryIds),
                'name' => ucwords($name),
                'description' => $faker->optional(0.8)->sentence(rand(10, 20)),
                'slug' => \Str::slug($name),
                'role_restriction' => $faker->randomElement($roles),
                'background_image' => $faker->optional(0.3) 
                    ? 'https://placehold.co/800x400' 
                    : null,
                'active' => $faker->boolean(90),
                'pinned' => $faker->boolean(20),
                'arrange' => $i + 1,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} forum subforums.");
    }
}

