<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TopicViewsSeeder extends Seeder
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
        
        // Create 50-60 views
        $count = rand(50, 60);
        
        for ($i = 0; $i < $count; $i++) {
            DB::table('cyo_topic_views')->insert([
                'topic_id' => $faker->randomElement($topicIds),
                'user_id' => $faker->optional(0.7)->randomElement($authAccountIds),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} topic views.");
    }
}

