<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SchoolClassesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get teacher IDs
        $teacherIds = DB::table('cyo_school_teachers')->pluck('id')->toArray();
        $schoolYears = ['2023-2024', '2024-2025', '2025-2026'];
        $grades = [10, 11, 12];
        
        // Create 20-30 classes
        $count = rand(20, 30);
        
        for ($i = 0; $i < $count; $i++) {
            $grade = $faker->randomElement($grades);
            $classNumber = $faker->numberBetween(1, 15);
            $className = $grade . 'A' . $classNumber;
            
            DB::table('cyo_school_classes')->insert([
                'name' => $className,
                'grade_level' => $grade,
                'main_teacher_id' => !empty($teacherIds) ? $faker->optional(0.8)->randomElement($teacherIds) : null,
                'student_count' => $faker->numberBetween(30, 45),
                'school_year' => $faker->randomElement($schoolYears),
                'room_number' => $faker->numerify('###'),
                'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} school classes.");
    }
}

