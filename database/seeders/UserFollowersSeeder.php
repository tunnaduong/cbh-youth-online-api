<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class UserFollowersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        // Create 40-50 follower relationships
        $count = rand(40, 50);
        
        // Track unique pairs to avoid duplicates
        $followerPairs = [];
        
        for ($i = 0; $i < $count; $i++) {
            $followerId = $faker->randomElement($authAccountIds);
            $followedId = $faker->randomElement($authAccountIds);
            
            // Don't allow users to follow themselves
            if ($followerId === $followedId) {
                continue;
            }
            
            $pairKey = $followerId . '_' . $followedId;
            
            // Skip if this pair already exists
            if (in_array($pairKey, $followerPairs)) {
                continue;
            }
            
            $followerPairs[] = $pairKey;
            
            DB::table('cyo_user_followers')->insert([
                'follower_id' => $followerId,
                'followed_id' => $followedId,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
            ]);
        }

        $this->command->info("Created " . count($followerPairs) . " follower relationships.");
    }
}

