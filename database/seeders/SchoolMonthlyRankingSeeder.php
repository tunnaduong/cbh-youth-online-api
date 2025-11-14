<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SchoolMonthlyRankingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get class IDs
        $classIds = DB::table('cyo_school_classes')->pluck('id')->toArray();
        $classNames = DB::table('cyo_school_classes')->pluck('name')->toArray();
        
        // Create 20-30 monthly rankings
        $count = rand(20, 30);
        
        for ($i = 0; $i < $count; $i++) {
            $classIndex = array_rand($classIds);
            $classId = $classIds[$classIndex];
            $className = $classNames[$classIndex];
            
            DB::table('cyo_school_monthly_ranking')->insert([
                'class_id' => $classId,
                'class_name' => $className,
                'month' => $faker->numberBetween(1, 12),
                'year' => $faker->numberBetween(2023, 2025),
                'total_points' => $faker->numberBetween(100, 5000),
                'rank' => $faker->numberBetween(1, 20),
            ]);
        }

        $this->command->info("Created {$count} monthly rankings.");
    }
}

