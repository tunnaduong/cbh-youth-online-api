<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TopicVotesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get topic IDs and auth account IDs
        $topicIds = DB::table('cyo_topics')->pluck('id')->toArray();
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $voteValues = [-1, 1]; // downvote or upvote
        
        // Create 40-50 votes
        $count = rand(40, 50);
        
        // Track unique pairs to avoid duplicates
        $votePairs = [];
        
        for ($i = 0; $i < $count; $i++) {
            $topicId = $faker->randomElement($topicIds);
            $userId = $faker->randomElement($authAccountIds);
            $pairKey = $topicId . '_' . $userId;
            
            // Skip if this pair already exists
            if (in_array($pairKey, $votePairs)) {
                continue;
            }
            
            $votePairs[] = $pairKey;
            
            DB::table('cyo_topic_votes')->insert([
                'topic_id' => $topicId,
                'user_id' => $userId,
                'vote_value' => $faker->randomElement($voteValues),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created " . count($votePairs) . " topic votes.");
    }
}

