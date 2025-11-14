<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TopicCommentVotesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get comment IDs and auth account IDs
        $commentIds = DB::table('cyo_topic_comments')->pluck('id')->toArray();
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $voteValues = [-1, 1];
        
        // Create 30-40 comment votes
        $count = rand(30, 40);
        
        // Track unique pairs to avoid duplicates
        $votePairs = [];
        
        for ($i = 0; $i < $count; $i++) {
            if (empty($commentIds)) {
                break;
            }
            
            $commentId = $faker->randomElement($commentIds);
            $userId = $faker->randomElement($authAccountIds);
            $pairKey = $commentId . '_' . $userId;
            
            // Skip if this pair already exists
            if (in_array($pairKey, $votePairs)) {
                continue;
            }
            
            $votePairs[] = $pairKey;
            
            DB::table('cyo_topic_comment_votes')->insert([
                'comment_id' => $commentId,
                'user_id' => $userId,
                'vote_value' => $faker->randomElement($voteValues),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created " . count($votePairs) . " comment votes.");
    }
}

