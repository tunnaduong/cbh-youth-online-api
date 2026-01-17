<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use App\Services\PointsService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class RewardUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reward {identifier} {amount} {reason=Thưởng từ Ban quản trị}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cộng điểm thưởng cho người dùng và gửi thông báo hệ thống';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $identifier = $this->argument('identifier');
        $amount = (int) $this->argument('amount');
        $reason = $this->argument('reason');

        if ($amount <= 0) {
            $this->error("Số điểm cộng phải lớn hơn 0.");
            return 1;
        }

        // Tìm user theo username hoặc ID
        $user = AuthAccount::where('username', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (!$user) {
            $this->error("Không tìm thấy người dùng với định danh: '{$identifier}'");
            return 1;
        }

        $userId = $user->id;

        // 1. Cộng điểm và ghi lịch sử giao dịch
        $success = PointsService::addPoints(
            $userId,
            $amount,
            'earning',
            $reason
        );

        if (!$success) {
            $this->error("Lỗi khi cộng điểm cho người dùng {$user->username}");
            return 1;
        }

        // 2. Gửi thông báo hệ thống (hiện trên web/app)
        NotificationService::createSystemNotification($userId, 'system_message', [
            'title' => 'Bạn vừa được cộng điểm thưởng!',
            'message' => "Hệ thống đã cộng cho bạn {$amount} điểm. Lý do: {$reason}.",
            'url' => '/wallet'
        ]);

        $this->info("Thành công: Đã cộng {$amount} điểm cho người dùng '{$user->username}' (ID: {$userId})");
        $this->info("Nội dung: {$reason}");

        return 0;
    }
}
