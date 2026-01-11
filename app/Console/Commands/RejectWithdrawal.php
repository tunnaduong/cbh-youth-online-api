<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use App\Models\WithdrawalRequest;
use App\Services\PointsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RejectWithdrawal extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'withdrawal:reject {id : ID của yêu cầu rút tiền} {--note= : Ghi chú của admin}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Từ chối yêu cầu rút tiền và hoàn điểm cho user';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $id = $this->argument('id');
    $note = $this->option('note') ?? 'Admin từ chối qua CLI';

    $withdrawal = WithdrawalRequest::find($id);

    if (!$withdrawal) {
      $this->error("Không tìm thấy yêu cầu rút tiền với ID: $id");
      return 1;
    }

    if ($withdrawal->status !== 'pending') {
      $this->error("Yêu cầu này có trạng thái '{$withdrawal->status}', không thể từ chối được nữa.");
      return 1;
    }

    // Tìm một admin để gán trách nhiệm
    $admin = AuthAccount::where('role', 'admin')->first();
    $adminId = $admin ? $admin->id : null;

    $this->info("Đang xử lý yêu cầu #$id của User ID {$withdrawal->user_id}...");
    $this->info("Số tiền: {$withdrawal->amount} điểm");
    if ($admin) {
      $this->info("Assign cho Admin ID: $adminId");
    } else {
      $this->warn('Không tìm thấy Admin nào. Admin ID sẽ là NULL.');
    }

    try {
      DB::transaction(function () use ($withdrawal, $note, $adminId) {
        // 1. Update status
        $withdrawal->update([
          'status' => 'rejected',
          'admin_note' => $note,
          'admin_id' => $adminId,
        ]);

        // 2. Hoàn tiền
        $addResult = PointsService::addPoints(
          $withdrawal->user_id,
          $withdrawal->amount,
          'withdrawal',
          "Hoàn điểm từ chối rút tiền #MW{$withdrawal->id} (CLI)",
          $withdrawal->id
        );

        if (!$addResult) {
          throw new \Exception('Lỗi khi cộng điểm hoàn lại.');
        }
      });

      $this->info("✅ Đã từ chối thành công và hoàn {$withdrawal->amount} điểm cho user.");

      // Show new balance
      $user = AuthAccount::find($withdrawal->user_id);
      $this->info("Sodium dư hiện tại của user: {$user->points}");
    } catch (\Exception $e) {
      $this->error('Lỗi: ' . $e->getMessage());
      return 1;
    }

    return 0;
  }
}
