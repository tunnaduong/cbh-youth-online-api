<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class CdnUserContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        $fileTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'audio/mpeg', 'application/pdf'];
        $extensions = ['jpg', 'png', 'gif', 'mp4', 'mp3', 'pdf'];
        
        // Create 30-40 content items
        $count = rand(30, 40);
        $selectedIds = $faker->randomElements($authAccountIds, min($count, count($authAccountIds)));
        
        foreach ($selectedIds as $userId) {
            $fileType = $faker->randomElement($fileTypes);
            $extension = $faker->randomElement($extensions);
            $fileName = $faker->uuid() . '.' . $extension;
            
            DB::table('cyo_cdn_user_content')->insert([
                'user_id' => $userId,
                'file_name' => $fileName,
                'file_path' => '/storage/uploads/' . date('Y/m') . '/' . $fileName,
                'file_type' => $fileType,
                'file_size' => $faker->numberBetween(1024, 10485760), // 1KB to 10MB
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} user content items.");
    }
}

