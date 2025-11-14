<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class AuthAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $roles = ['user', 'student', 'teacher', 'volunteer', 'admin'];
        $providers = ['google', 'facebook', null];

        // Create 30-40 auth accounts
        $count = rand(30, 40);
        
        for ($i = 0; $i < $count; $i++) {
            $role = $faker->randomElement($roles);
            $provider = $faker->randomElement($providers);
            
            DB::table('cyo_auth_accounts')->insert([
                'username' => $faker->unique()->userName(),
                'password' => $provider ? null : Hash::make('password123'),
                'email' => $faker->unique()->safeEmail(),
                'role' => $role,
                'provider' => $provider,
                'provider_id' => $provider ? $faker->numerify('##########') : null,
                'provider_token' => $provider ? $faker->sha256() : null,
                'cached_points' => $faker->numberBetween(0, 5000),
                'email_verified_at' => $faker->optional(0.8)->dateTimeBetween('-1 year', 'now'),
                'last_activity' => $faker->optional(0.7)->dateTimeBetween('-1 month', 'now'),
                'remember_token' => $faker->optional(0.3)->sha1(),
                'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} auth accounts.");
    }
}

