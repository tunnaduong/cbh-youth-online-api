<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class StoryReactionsSeeder extends Seeder
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
        $reactionTypes = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
        
        // Create 30-40 story reactions
        $count = rand(30, 40);
        
        // Track unique pairs to avoid duplicates
        $reactionPairs = [];
        
        for ($i = 0; $i < $count; $i++) {
            if (empty($storyIds)) {
                break;
            }
            
            $storyId = $faker->randomElement($storyIds);
            $userId = $faker->randomElement($authAccountIds);
            $pairKey = $storyId . '_' . $userId;
            
            // Skip if this pair already exists
            if (in_array($pairKey, $reactionPairs)) {
                continue;
            }
            
            $reactionPairs[] = $pairKey;
            
            DB::table('cyo_story_reactions')->insert([
                'story_id' => $storyId,
                'user_id' => $userId,
                'reaction_type' => $faker->randomElement($reactionTypes),
                'created_at' => $faker->dateTimeBetween('-2 days', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created " . count($reactionPairs) . " story reactions.");
    }
}

