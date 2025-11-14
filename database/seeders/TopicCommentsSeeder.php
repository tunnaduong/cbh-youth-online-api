<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TopicCommentsSeeder extends Seeder
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
        
        // Create 40-50 comments
        $count = rand(40, 50);
        
        for ($i = 0; $i < $count; $i++) {
            // Some comments are replies to other comments
            $replyingTo = null;
            if ($faker->boolean(30)) {
                $existingCommentIds = DB::table('cyo_topic_comments')->pluck('id')->toArray();
                if (!empty($existingCommentIds)) {
                    $replyingTo = $faker->randomElement($existingCommentIds);
                }
            }
            
            DB::table('cyo_topic_comments')->insert([
                'replying_to' => $replyingTo,
                'topic_id' => $faker->randomElement($topicIds),
                'user_id' => $faker->randomElement($authAccountIds),
                'comment' => $faker->paragraph(rand(1, 5)),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} topic comments.");
    }
}

