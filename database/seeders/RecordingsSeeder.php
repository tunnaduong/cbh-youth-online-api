<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class RecordingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs and content IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $contentIds = DB::table('cyo_cdn_user_content')->pluck('id')->toArray();
        
        // Create 25-35 recordings
        $count = rand(25, 35);
        
        for ($i = 0; $i < $count; $i++) {
            $audioLength = $faker->numberBetween(60, 3600); // 1 minute to 1 hour
            $minutes = floor($audioLength / 60);
            $seconds = $audioLength % 60;
            $lengthString = sprintf('%02d:%02d', $minutes, $seconds);
            
            DB::table('cyo_recordings')->insert([
                'user_id' => $faker->randomElement($authAccountIds),
                'title' => $faker->sentence(rand(3, 8)),
                'description' => $faker->paragraph(rand(2, 5)),
                'cdn_audio_id' => $faker->randomElement($contentIds),
                'cdn_preview_id' => !empty($contentIds) ? $faker->optional(0.5)->randomElement($contentIds) : null,
                'audio_length' => $lengthString,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} recordings.");
    }
}

