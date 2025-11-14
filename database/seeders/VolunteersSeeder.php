<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class VolunteersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $genders = ['male', 'female', 'other'];
        $statuses = ['active', 'inactive', 'archived'];
        
        // Get auth account IDs and class IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $classIds = DB::table('cyo_school_classes')->pluck('id')->toArray();
        
        // Create 25-35 volunteers
        $count = rand(25, 35);
        
        for ($i = 0; $i < $count; $i++) {
            $email = $faker->boolean(80) ? $faker->unique()->safeEmail() : null;
            
            DB::table('cyo_volunteers')->insert([
                'user_id' => !empty($authAccountIds) ? $faker->optional(0.6)->randomElement($authAccountIds) : null,
                'full_name' => $faker->name(),
                'gender' => $faker->randomElement($genders),
                'date_of_birth' => $faker->dateTimeBetween('-30 years', '-18 years'),
                'class_id' => $faker->randomElement($classIds),
                'contact_number' => $faker->optional(0.9)->numerify('0#########'),
                'email' => $email,
                'join_date' => $faker->dateTimeBetween('-2 years', 'now'),
                'status' => $faker->randomElement($statuses),
            ]);
        }

        $this->command->info("Created {$count} volunteers.");
    }
}

