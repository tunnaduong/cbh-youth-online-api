<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class StoryViewersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get story IDs and auth account IDs
        $storyIds = DB::table('cyo_stories')->pluck('id')->toArray();
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        // Create 40-50 story views
        $count = rand(40, 50);
        
        // Track unique pairs to avoid duplicates
        $viewPairs = [];
        
        for ($i = 0; $i < $count; $i++) {
            if (empty($storyIds)) {
                break;
            }
            
            $storyId = $faker->randomElement($storyIds);
            $userId = $faker->randomElement($authAccountIds);
            $pairKey = $storyId . '_' . $userId;
            
            // Skip if this pair already exists
            if (in_array($pairKey, $viewPairs)) {
                continue;
            }
            
            $viewPairs[] = $pairKey;
            
            DB::table('cyo_story_viewers')->insert([
                'story_id' => $storyId,
                'user_id' => $userId,
                'viewed_at' => $faker->dateTimeBetween('-2 days', 'now'),
                'created_at' => $faker->dateTimeBetween('-2 days', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created " . count($viewPairs) . " story views.");
    }
}

