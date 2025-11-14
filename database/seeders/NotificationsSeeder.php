<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class NotificationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs, topic IDs, and comment IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $topicIds = DB::table('cyo_topics')->pluck('id')->toArray();
        $commentIds = DB::table('cyo_topic_comments')->pluck('id')->toArray();
        
        $notificationTypes = [
            'topic_liked', 'comment_liked', 'comment_replied', 
            'topic_created', 'user_followed', 'mention'
        ];
        $notifiableTypes = ['Topic', 'TopicComment', null];
        
        // Create 40-50 notifications
        $count = rand(40, 50);
        
        for ($i = 0; $i < $count; $i++) {
            $notifiableType = $faker->randomElement($notifiableTypes);
            $notifiableId = null;
            
            if ($notifiableType === 'Topic' && !empty($topicIds)) {
                $notifiableId = $faker->randomElement($topicIds);
            } elseif ($notifiableType === 'TopicComment' && !empty($commentIds)) {
                $notifiableId = $faker->randomElement($commentIds);
            }
            
            DB::table('cyo_notifications')->insert([
                'user_id' => $faker->randomElement($authAccountIds),
                'actor_id' => $faker->optional(0.8)->randomElement($authAccountIds),
                'type' => $faker->randomElement($notificationTypes),
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'data' => json_encode([
                    'title' => $faker->sentence(),
                    'excerpt' => $faker->optional(0.7)->sentence(),
                    'url' => $faker->url(),
                ]),
                'read_at' => $faker->optional(0.4)->dateTimeBetween('-1 month', 'now'),
                'created_at' => $faker->dateTimeBetween('-1 month', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} notifications.");
    }
}

