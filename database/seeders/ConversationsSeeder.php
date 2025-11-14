<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ConversationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $types = ['private', 'group'];
        
        // Create 30-40 conversations
        $count = rand(30, 40);
        
        for ($i = 0; $i < $count; $i++) {
            $type = $faker->randomElement($types);
            DB::table('cyo_conversations')->insert([
                'type' => $type,
                'name' => $type === 'group' ? $faker->optional(0.8)->words(rand(2, 4), true) : null,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} conversations.");
    }
}

