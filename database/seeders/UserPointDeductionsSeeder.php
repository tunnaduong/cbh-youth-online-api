<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class UserPointDeductionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        
        // Get auth account IDs
        $authAccountIds = DB::table('cyo_auth_accounts')->pluck('id')->toArray();
        
        // Get admin IDs
        $adminIds = DB::table('cyo_auth_accounts')
            ->where('role', 'admin')
            ->pluck('id')
            ->toArray();
        
        $reasons = [
            'Vi phạm nội quy diễn đàn',
            'Spam nội dung',
            'Đăng nội dung không phù hợp',
            'Gây gổ với thành viên khác',
            'Quảng cáo trái phép',
        ];
        
        // Create 20-30 point deductions
        $count = rand(20, 30);
        
        for ($i = 0; $i < $count; $i++) {
            if (empty($adminIds)) {
                break;
            }
            
            DB::table('cyo_user_point_deductions')->insert([
                'user_id' => $faker->randomElement($authAccountIds),
                'points_deducted' => $faker->numberBetween(5, 100),
                'reason' => $faker->randomElement($reasons),
                'description' => $faker->optional(0.7)->paragraph(),
                'admin_id' => $faker->randomElement($adminIds),
                'is_active' => $faker->boolean(80),
                'expires_at' => $faker->optional(0.4)->dateTimeBetween('now', '+1 year'),
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created {$count} point deductions.");
    }
}

