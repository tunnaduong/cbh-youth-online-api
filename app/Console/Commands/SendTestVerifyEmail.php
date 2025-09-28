<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AuthAccount; // hoặc App\Models\User nếu bạn dùng model User
use App\Notifications\VerifyEmail;

class SendTestVerifyEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan test:verify-email {username}
     */
    protected $signature = 'test:verify-email {username}';

    /**
     * The console command description.
     */
    protected $description = 'Gửi thử email xác minh cho user theo ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = $this->argument('username');

        $user = AuthAccount::where('username', $username)->first();

        if (!$user) {
            $this->error("Không tìm thấy user với username {$username}");
            return 1;
        }

        $user->notify(new VerifyEmail);

        $this->info("Đã gửi email xác minh tới {$user->email}");
        return 0;
    }
}
