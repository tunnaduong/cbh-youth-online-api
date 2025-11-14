<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SchoolTimetablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get class IDs and teacher IDs
        $classIds = DB::table('cyo_school_classes')->pluck('id')->toArray();
        $teacherIds = DB::table('cyo_school_teachers')->pluck('id')->toArray();
        
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $subjects = [
            'Toán học', 'Vật lý', 'Hóa học', 'Sinh học', 'Ngữ văn',
            'Lịch sử', 'Địa lý', 'Tiếng Anh', 'Giáo dục công dân',
            'Thể dục', 'Mỹ thuật', 'Âm nhạc', 'Tin học'
        ];
        $semesters = ['1', '2', 'Summer'];
        $schoolYears = ['2023-2024', '2024-2025', '2025-2026'];
        
        // Create 40-50 timetable entries
        $count = rand(40, 50);
        
        for ($i = 0; $i < $count; $i++) {
            $startTime = $faker->time('H:i', '08:00', '17:00');
            $startHour = (int) explode(':', $startTime)[0];
            $endHour = min($startHour + rand(1, 2), 17);
            $endTime = sprintf('%02d:00', $endHour);
            
            DB::table('cyo_school_timetables')->insert([
                'class_id' => $faker->randomElement($classIds),
                'subject' => $faker->randomElement($subjects),
                'teacher_id' => $faker->randomElement($teacherIds),
                'day_of_week' => $faker->randomElement($days),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_number' => $faker->optional(0.8)->numerify('###'),
                'semester' => $faker->randomElement($semesters),
                'school_year' => $faker->randomElement($schoolYears),
                'notes' => $faker->optional(0.3)->sentence(),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} timetable entries.");
    }
}

