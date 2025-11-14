<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ConversationMessagesSeeder extends Seeder
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
        $messageTypes = ['text', 'image', 'file'];
        
        // Create 50-60 messages
        $count = rand(50, 60);
        
        for ($i = 0; $i < $count; $i++) {
            $messageType = $faker->randomElement($messageTypes);
            
            // Content is required, so provide text for all message types
            // For image/file messages, provide a caption/description
            $content = $messageType === 'text' 
                ? $faker->sentence(rand(5, 20))
                : $faker->optional(0.7)->sentence(rand(3, 10)) ?? '';
            
            DB::table('cyo_conversation_messages')->insert([
                'conversation_id' => $faker->randomElement($conversationIds),
                'user_id' => $faker->randomElement($authAccountIds),
                'content' => $content,
                'type' => $messageType,
                'file_url' => $messageType !== 'text' 
                    ? 'https://placehold.co/' . $faker->numberBetween(300, 800) . 'x' . $faker->numberBetween(200, 600)
                    : null,
                'is_edited' => $faker->boolean(10),
                'read_at' => $faker->optional(0.6)->dateTimeBetween('-1 month', 'now'),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} conversation messages.");
    }
}

