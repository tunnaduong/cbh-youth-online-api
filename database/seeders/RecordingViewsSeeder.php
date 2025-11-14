<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class RecordingViewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get recording IDs and auth account IDs
        $recordingIds = DB::table('cyo_recordings')->pluck('id')->toArray();
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        // Create 30-40 recording views
        $count = rand(30, 40);
        
        for ($i = 0; $i < $count; $i++) {
            if (empty($recordingIds)) {
                break;
            }
            
            DB::table('cyo_recording_views')->insert([
                'record_id' => $faker->randomElement($recordingIds),
                'user_id' => $faker->optional(0.7)->randomElement($authAccountIds),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} recording views.");
    }
}

