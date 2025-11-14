<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TopicsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs, subforum IDs, and content IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $subforumIds = DB::table('cyo_forum_subforums')->pluck('id')->toArray();
        $contentIds = DB::table('cyo_cdn_user_content')->pluck('id')->toArray();
        
        // Create 30-40 topics
        $count = rand(30, 40);
        
        for ($i = 0; $i < $count; $i++) {
            DB::table('cyo_topics')->insert([
                'subforum_id' => !empty($subforumIds) ? $faker->optional(0.9)->randomElement($subforumIds) : null,
                'user_id' => $faker->randomElement($authAccountIds),
                'title' => $faker->sentence(rand(5, 12)),
                'description' => $faker->paragraph(rand(3, 8)),
                'pinned' => $faker->boolean(10),
                'hidden' => $faker->numberBetween(0, 1),
                'cdn_image_id' => !empty($contentIds) ? $faker->optional(0.4)->randomElement($contentIds) : null,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} topics.");
    }
}

