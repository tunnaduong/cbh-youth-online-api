<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SchoolTeachersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $genders = ['male', 'female', 'other'];
        $subjects = [
            'Toán học', 'Vật lý', 'Hóa học', 'Sinh học', 'Ngữ văn',
            'Lịch sử', 'Địa lý', 'Tiếng Anh', 'Giáo dục công dân',
            'Thể dục', 'Mỹ thuật', 'Âm nhạc', 'Tin học'
        ];

        // Create 25-35 teachers
        $count = rand(25, 35);
        
        for ($i = 0; $i < $count; $i++) {
            DB::table('cyo_school_teachers')->insert([
                'name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'phone_number' => $faker->optional(0.9)->numerify('0#########'),
                'subject' => $faker->randomElement($subjects),
                'hire_date' => $faker->optional(0.8)->dateTimeBetween('-10 years', '-1 year'),
                'date_of_birth' => $faker->optional(0.7)->dateTimeBetween('-60 years', '-25 years'),
                'gender' => $faker->randomElement($genders),
                'address' => $faker->optional(0.8)->address(),
                'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} school teachers.");
    }
}

