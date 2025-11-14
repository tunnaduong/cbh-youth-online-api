<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class OnlineUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        // Create 30-40 online user records
        $count = rand(30, 40);
        
        for ($i = 0; $i < $count; $i++) {
            DB::table('cyo_online_users')->insert([
                'session_id' => $faker->uuid(),
                'user_id' => !empty($authAccountIds) ? $faker->optional(0.8)->randomElement($authAccountIds) : null,
                'is_hidden' => $faker->boolean(20),
                'last_activity' => $faker->dateTimeBetween('-1 hour', 'now'),
                'ip_address' => $faker->ipv4(),
            ]);
        }

        $this->command->info("Created {$count} online user records.");
    }
}

