<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class AuthEmailVerificationCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        // Create 20-30 verification codes
        $count = rand(20, 30);
        $selectedIds = $faker->randomElements($authAccountIds, min($count, count($authAccountIds)));
        
        foreach ($selectedIds as $userId) {
            DB::table('cyo_auth_email_verification_code')->insert([
                'user_id' => $userId,
                'verification_code' => $faker->unique()->bothify('????##??##'),
                'expires_at' => $faker->optional(0.7)->dateTimeBetween('now', '+1 day'),
                'created_at' => $faker->dateTimeBetween('-1 month', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} email verification codes.");
    }
}

