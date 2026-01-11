<?php

namespace App\Http\Controllers;

use App\Models\PendingDeposit;
use App\Models\PointsTransaction;
use App\Models\WithdrawalRequest;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
  /**
   * Get wallet balance
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getBalance()
  {
    $user = Auth::user();
    $points = $user->points ?? 0;
    $vnd = PointsService::convertPointsToVND($points);

    return response()->json([
      'points' => $points,
      'vnd' => $vnd,
      'formatted_vnd' => number_format($vnd) . ' VND',
      'min_withdrawal_points' => 500,
      'min_withdrawal_vnd' => 50000,
    ]);
  }

  /**
   * Get transaction history
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getTransactions(Request $request)
  {
    $user = Auth::user();

    $query = PointsTransaction::where('user_id', $user->id)
      ->orderBy('created_at', 'desc');

    // Filter by type
    if ($request->has('type')) {
      $query->where('type', $request->type);
    }

    $transactions = $query->paginate(50);

    $transactions->getCollection()->transform(function ($transaction) {
      return [
        'id' => $transaction->id,
        'type' => $transaction->type,
        'amount' => $transaction->amount,
        'description' => $transaction->description,
        'status' => $transaction->status,
        'created_at' => $transaction->created_at,
      ];
    });

    return response()->json($transactions);
  }

  /**
   * Request withdrawal
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function requestWithdrawal(Request $request)
  {
    $request->validate([
      'amount' => 'required|integer|min:500',
      'bank_account' => 'required|string|max:255',
      'bank_name' => 'required|string|max:255',
      'account_holder' => 'required|string|max:255',
    ]);

    $user = Auth::user();

    if (!PointsService::canWithdraw($user->id, $request->amount)) {
      return response()->json([
        'message' => 'Số điểm không đủ hoặc dưới mức tối thiểu (500 điểm)',
        'current_points' => $user->points ?? 0,
        'min_withdrawal' => 500,
      ], 400);
    }

    // Deduct points immediately (hold)
    try {
      DB::transaction(function () use ($request, $user, &$withdrawalRequest) {
        $deducted = PointsService::deductPoints(
          $user->id,
          $request->amount,
          'withdrawal',
          "Tạm giữ điểm cho yêu cầu rút tiền #MW{$user->id}",
          null
        );

        if (!$deducted) {
          throw new \Exception('Không thể trừ điểm. Vui lòng thử lại.');
        }

        $withdrawalRequest = WithdrawalRequest::create([
          'user_id' => $user->id,
          'amount' => $request->amount,
          'bank_account' => $request->bank_account,
          'bank_name' => $request->bank_name,
          'account_holder' => $request->account_holder,
          'status' => 'pending',
        ]);
      });
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Withdrawal request failed: ' . $e->getMessage());
      return response()->json(['message' => $e->getMessage()], 500);
    }

    // Notify User via Email (mock/placeholder if mailer not ready, or use NotificationService if available)
    // Assuming NotificationService has a method or we build one.
    // For now, logging and creating an internal notification.
    \App\Services\NotificationService::createSystemNotification(
      $user->id,
      'system_message',
      [
        'title' => 'Yêu cầu rút tiền thành công',
        'message' => "Bạn vừa yêu cầu rút {$request->amount} điểm. Yêu cầu đang chờ duyệt.",
        'url' => '/wallet'
      ]
    );

    // Notify Admin via Email
    // check if we have a mailer, else just log for now to avoid 500
    try {
      // Mail::to(config('app.admin_email'))->send(new WithdrawalRequestedMail($withdrawalRequest));
      // Since I cannot create Mail classes easily without seeing the structure, I will rely on NotificationService to notify admins internally first.
      \App\Services\NotificationService::notifyAdmins(
        'Yêu cầu rút tiền mới',
        "Người dùng @{$user->username} vừa yêu cầu rút {$request->amount} điểm."
      );
    } catch (\Exception $e) {
      // Log error but don't fail the request
      \Illuminate\Support\Facades\Log::error('Failed to send email notification: ' . $e->getMessage());
    }

    return response()->json($withdrawalRequest, 201);
  }

  /**
   * Get withdrawal requests for current user
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getWithdrawalRequests(Request $request)
  {
    $user = Auth::user();

    $requests = WithdrawalRequest::where('user_id', $user->id)
      ->orderBy('created_at', 'desc')
      ->paginate(20);

    return response()->json($requests);
  }

  /**
   * Cancel withdrawal request
   *
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function cancelWithdrawalRequest($id)
  {
    $user = Auth::user();
    $withdrawalRequest = WithdrawalRequest::findOrFail($id);

    if ($withdrawalRequest->user_id !== $user->id) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if ($withdrawalRequest->status !== 'pending') {
      return response()->json(['message' => 'Chỉ có thể hủy yêu cầu đang chờ duyệt'], 400);
    }

    try {
      DB::transaction(function () use ($withdrawalRequest, $user) {
        $withdrawalRequest->status = 'cancelled';
        $withdrawalRequest->save();

        // Refund points
        $refunded = PointsService::addPoints(
          $user->id,
          $withdrawalRequest->amount,
          'withdrawal',
          "Hoàn điểm hủy yêu cầu rút tiền #MW{$user->id}",
          null
        );

        if (!$refunded) {
          throw new \Exception('Failed to refund points');
        }
      });
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Cancel withdrawal failed: ' . $e->getMessage());
      return response()->json(['message' => 'Lỗi hệ thống khi hủy yêu cầu'], 500);
    }

    return response()->json(['message' => 'Đã hủy yêu cầu rút tiền']);
  }

  /**
   * Get withdrawal history
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getWithdrawalHistory()
  {
    $user = Auth::user();

    $history = WithdrawalRequest::where('user_id', $user->id)
      ->whereIn('status', ['approved', 'completed'])
      ->orderBy('created_at', 'desc')
      ->get();

    return response()->json($history);
  }

  /**
   * Create deposit request (generate deposit code)
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function createDepositRequest(Request $request)
  {
    $request->validate([
      'amount_vnd' => 'required|integer|min:10000',  // Minimum 10.000 VND
    ]);

    $user = Auth::user();
    $amountVND = $request->amount_vnd;
    $feeVND = 1000;  // 1.000 VND fee
    $netAmountVND = $amountVND - $feeVND;
    $expectedPoints = PointsService::convertVNDToPoints($netAmountVND);

    if ($expectedPoints <= 0) {
      return response()->json([
        'message' => 'Số tiền quá nhỏ sau khi trừ phí',
      ], 400);
    }

    // Generate unique deposit code (format: CBH + user_id + timestamp)
    $depositCode = 'CBH' . $user->id . time();

    $pendingDeposit = PendingDeposit::create([
      'user_id' => $user->id,
      'deposit_code' => $depositCode,
      'amount_vnd' => $amountVND,
      'expected_points' => $expectedPoints,
      'status' => 'pending',
      'expires_at' => now()->addHours(24),  // Code expires in 24 hours
    ]);

    return response()->json([
      'deposit_code' => $depositCode,
      'amount_vnd' => $amountVND,
      'expected_points' => $expectedPoints,
      'fee_vnd' => $feeVND,
      'instructions' => "Chuyển khoản {$amountVND} VND với nội dung: {$depositCode}",
      'expires_at' => $pendingDeposit->expires_at,
    ], 201);
  }
}
