<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class UserSavedTopicsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs and topic IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $topicIds = DB::table('cyo_topics')->pluck('id')->toArray();
        
        // Create 30-40 saved topics
        $count = rand(30, 40);
        
        // Track unique pairs to avoid duplicates
        $savedPairs = [];
        
        for ($i = 0; $i < $count; $i++) {
            $userId = $faker->randomElement($authAccountIds);
            $topicId = $faker->randomElement($topicIds);
            $pairKey = $userId . '_' . $topicId;
            
            // Skip if this pair already exists
            if (in_array($pairKey, $savedPairs)) {
                continue;
            }
            
            $savedPairs[] = $pairKey;
            
            DB::table('cyo_user_saved_topics')->insert([
                'user_id' => $userId,
                'topic_id' => $topicId,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created " . count($savedPairs) . " saved topics.");
    }
}

