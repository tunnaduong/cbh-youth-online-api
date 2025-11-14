<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class NotificationSubscriptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        // Create 20-30 push notification subscriptions
        $count = rand(20, 30);
        $selectedIds = $faker->randomElements($authAccountIds, min($count, count($authAccountIds)));
        
        foreach ($selectedIds as $userId) {
            DB::table('cyo_notification_subscriptions')->insert([
                'user_id' => $userId,
                'endpoint' => $faker->unique()->url() . '/push/' . $faker->uuid(),
                'p256dh' => $faker->sha256(),
                'auth' => $faker->sha256(),
                'expires_at' => $faker->optional(0.5)->dateTimeBetween('now', '+1 year'),
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} notification subscriptions.");
    }
}

