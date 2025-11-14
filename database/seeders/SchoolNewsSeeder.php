<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SchoolNewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs (for authors)
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $statuses = ['draft', 'published', 'archived'];
        
        // Create 25-35 news articles
        $count = rand(25, 35);
        
        for ($i = 0; $i < $count; $i++) {
            $status = $faker->randomElement($statuses);
            $publishedAt = null;
            
            if ($status === 'published') {
                $publishedAt = $faker->dateTimeBetween('-1 year', 'now');
            }
            
            DB::table('cyo_school_news')->insert([
                'title' => $faker->sentence(rand(5, 10)),
                'content' => $faker->paragraph(rand(5, 15)),
                'author_id' => $faker->randomElement($authAccountIds),
                'published_at' => $publishedAt,
                'status' => $status,
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} news articles.");
    }
}

