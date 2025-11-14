<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class NotificationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $notifyTypes = ['all', 'direct_mentions', 'none'];
        
        // Create settings for 25-35 users
        $count = rand(25, 35);
        $selectedIds = $faker->randomElements($authAccountIds, min($count, count($authAccountIds)));
        
        foreach ($selectedIds as $userId) {
            DB::table('cyo_notification_settings')->insert([
                'user_id' => $userId,
                'notify_type' => $faker->randomElement($notifyTypes),
                'notify_email_contact' => $faker->boolean(80),
                'notify_email_marketing' => $faker->boolean(30),
                'notify_email_social' => $faker->boolean(70),
                'notify_email_security' => $faker->boolean(90),
                'updated_at' => $faker->dateTimeBetween('-1 year', 'now'),
            ]);
        }

        $this->command->info("Created {$count} notification settings.");
    }
}

