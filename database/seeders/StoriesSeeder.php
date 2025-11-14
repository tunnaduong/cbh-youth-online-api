<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class StoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $mediaTypes = ['image', 'video', 'audio', null];
        $privacies = ['public', 'followers'];
        $fontStyles = ['normal', 'bold', 'italic', 'bold-italic'];
        $colors = ['#FF5733', '#33FF57', '#3357FF', '#FF33F5', '#F5FF33'];
        
        // Create 30-40 stories
        $count = rand(30, 40);
        
        for ($i = 0; $i < $count; $i++) {
            $mediaType = $faker->randomElement($mediaTypes);
            $expiresAt = $faker->dateTimeBetween('now', '+24 hours');
            
            DB::table('cyo_stories')->insert([
                'user_id' => $faker->randomElement($authAccountIds),
                'content' => $faker->optional(0.7)->sentence(rand(5, 20)),
                'media_type' => $mediaType,
                'media_url' => $mediaType ? 'https://placehold.co/1080x1920' : null,
                'background_color' => !$mediaType ? $faker->randomElement($colors) : null,
                'font_style' => !$mediaType ? $faker->optional(0.6)->randomElement($fontStyles) : null,
                'text_position' => !$mediaType ? json_encode(['x' => $faker->randomFloat(2, 0, 1), 'y' => $faker->randomFloat(2, 0, 1)]) : null,
                'privacy' => $faker->randomElement($privacies),
                'expires_at' => $expiresAt,
                'duration' => $mediaType === 'video' || $mediaType === 'audio' ? $faker->numberBetween(5, 60) : null,
                'created_at' => $faker->dateTimeBetween('-2 days', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} stories.");
    }
}

