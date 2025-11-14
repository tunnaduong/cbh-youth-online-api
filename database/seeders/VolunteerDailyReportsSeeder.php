<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class VolunteerDailyReportsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get class IDs, volunteer IDs, and mistake IDs
        $classIds = DB::table('cyo_school_classes')->pluck('id')->toArray();
        $volunteerIds = DB::table('cyo_volunteers')->pluck('id')->toArray();
        $mistakeIds = DB::table('cyo_school_mistake_list')->pluck('id')->toArray();
        
        // Create 30-40 daily reports
        $count = rand(30, 40);
        
        for ($i = 0; $i < $count; $i++) {
            DB::table('cyo_volunteer_daily_reports')->insert([
                'class_id' => $faker->randomElement($classIds),
                'volunteer_id' => $faker->randomElement($volunteerIds),
                'absent' => $faker->numberBetween(0, 10),
                'cleanliness' => $faker->boolean(80),
                'uniform' => $faker->boolean(85),
                'mistake_id' => !empty($mistakeIds) ? $faker->optional(0.5)->randomElement($mistakeIds) : null,
                'note' => $faker->optional(0.6)->paragraph(),
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} volunteer daily reports.");
    }
}

