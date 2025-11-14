<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class UserReportsSeeder extends Seeder
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
        $statuses = ['pending', 'reviewed', 'resolved', 'dismissed'];
        
        // Get admin IDs for reviewed_by
        $adminIds = DB::table('cyo_auth_accounts')
            ->where('role', 'admin')
            ->pluck('id')
            ->toArray();
        
        // Create 20-30 reports
        $count = rand(20, 30);
        
        for ($i = 0; $i < $count; $i++) {
            $status = $faker->randomElement($statuses);
            $reviewedBy = null;
            $reviewedAt = null;
            
            if ($status !== 'pending' && !empty($adminIds)) {
                $reviewedBy = $faker->randomElement($adminIds);
                $reviewedAt = $faker->dateTimeBetween('-1 month', 'now');
            }
            
            DB::table('cyo_user_reports')->insert([
                'user_id' => $faker->randomElement($authAccountIds),
                'reported_user_id' => $faker->randomElement($authAccountIds),
                'topic_id' => $faker->optional(0.6)->randomElement($topicIds),
                'reason' => $faker->sentence(rand(5, 15)),
                'status' => $status,
                'admin_notes' => $status !== 'pending' ? $faker->optional(0.7)->paragraph() : null,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => $reviewedAt,
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} user reports.");
    }
}

