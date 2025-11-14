<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ConversationParticipantsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get conversation IDs and auth account IDs
        $conversationIds = DB::table('cyo_conversations')->pluck('id')->toArray();
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        // Create 40-50 participants
        $count = rand(40, 50);
        
        // Track unique pairs to avoid duplicates
        $participantPairs = [];
        
        for ($i = 0; $i < $count; $i++) {
            $conversationId = $faker->randomElement($conversationIds);
            $userId = $faker->randomElement($authAccountIds);
            $pairKey = $conversationId . '_' . $userId;
            
            // Skip if this pair already exists
            if (in_array($pairKey, $participantPairs)) {
                continue;
            }
            
            $participantPairs[] = $pairKey;
            
            DB::table('cyo_conversation_participants')->insert([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'last_read_at' => $faker->optional(0.6)->dateTimeBetween('-1 month', 'now'),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created " . count($participantPairs) . " conversation participants.");
    }
}

