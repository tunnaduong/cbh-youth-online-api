<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class OnlineRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');

        // Create 20-30 online records
        $count = rand(20, 30);
        
        for ($i = 0; $i < $count; $i++) {
            DB::table('cyo_online_record')->insert([
                'id' => $i + 1,
                'max_online' => $faker->numberBetween(50, 500),
                'recorded_at' => $faker->dateTimeBetween('-1 year', 'now'),
            ]);
        }

        $this->command->info("Created {$count} online records.");
    }
}

