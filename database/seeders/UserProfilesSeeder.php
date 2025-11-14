<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class UserProfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $genders = ['Male', 'Female'];
        
        // Get all auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        $contentIds = DB::table('cyo_cdn_user_content')->pluck('id')->toArray();
        
        // Create profiles for 25-35 users
        $count = rand(25, 35);
        $selectedIds = $faker->randomElements($authAccountIds, min($count, count($authAccountIds)));
        
        foreach ($selectedIds as $authAccountId) {
            // Generate bio with length limit
            $bio = null;
            if ($faker->boolean(60)) {
                $bioText = $faker->randomElement([
                    $faker->sentence(rand(5, 15)),
                    $faker->words(rand(3, 8), true),
                ]);
                $bio = Str::limit($bioText, 250);
            }
            
            DB::table('cyo_user_profiles')->insert([
                'auth_account_id' => $authAccountId,
                'profile_name' => $faker->optional(0.9)->name(),
                'profile_username' => $faker->optional(0.8)->userName(),
                'bio' => $bio,
                'profile_picture' => !empty($contentIds) ? $faker->optional(0.5)->randomElement($contentIds) : null,
                'oauth_profile_picture' => $faker->optional(0.3) 
                    ? 'https://placehold.co/200x200' 
                    : null,
                'birthday' => $faker->optional(0.7)->dateTimeBetween('-30 years', '-13 years'),
                'gender' => $faker->optional(0.8)->randomElement($genders),
                'location' => $faker->optional(0.6)->city(),
                'verified' => $faker->randomElement(['0', '1']),
                'last_username_change' => $faker->optional(0.3)->dateTimeBetween('-1 year', 'now'),
                'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} user profiles.");
    }
}

