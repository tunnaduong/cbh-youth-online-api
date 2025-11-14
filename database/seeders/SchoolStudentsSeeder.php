<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SchoolStudentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $genders = ['male', 'female', 'other'];
        
        // Get class IDs
        $classIds = DB::table('cyo_school_classes')->pluck('id')->toArray();
        
        // Create 30-40 students
        $count = rand(30, 40);
        
        for ($i = 0; $i < $count; $i++) {
            $email = $faker->boolean(70) ? $faker->unique()->safeEmail() : null;
            
            DB::table('cyo_school_students')->insert([
                'name' => $faker->name(),
                'email' => $email,
                'phone_number' => $faker->optional(0.8)->numerify('0#########'),
                'date_of_birth' => $faker->optional(0.9)->dateTimeBetween('-18 years', '-13 years'),
                'gender' => $faker->optional(0.9)->randomElement($genders),
                'class_id' => !empty($classIds) ? $faker->randomElement($classIds) : null,
                'enrollment_date' => $faker->dateTimeBetween('-3 years', '-1 month'),
                'address' => $faker->optional(0.8)->address(),
                'parent_name' => $faker->optional(0.7)->name(),
                'parent_phone_number' => $faker->optional(0.7)->numerify('0#########'),
                'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} school students.");
    }
}

