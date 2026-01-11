<?php

namespace App\Http\Controllers;

use App\Services\SEPayWebhookService;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SEPayWebhookController extends Controller
{
  /**
   * Handle webhook from SEPay
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function handleWebhook(Request $request)
  {
    try {
      $data = $request->all();

      Log::info('SEPay webhook received', $data);

      // Validate required fields
      if (!isset($data['id']) || !isset($data['transferType']) || !isset($data['transferAmount'])) {
        Log::warning('SEPay webhook: Missing required fields', $data);
        return response()->json(['success' => false, 'message' => 'Missing required fields'], 400);
      }

      $sepayId = $data['id'];
      $referenceCode = $data['referenceCode'] ?? null;
      $transferType = $data['transferType']; // 'in' or 'out'
      $transferAmount = $data['transferAmount'];

      // Check for duplicate transaction
      if (SEPayWebhookService::isDuplicateTransaction($sepayId, $referenceCode)) {
        Log::info('SEPay webhook: Duplicate transaction detected', [
          'id' => $sepayId,
          'reference_code' => $referenceCode
        ]);
        return response()->json(['success' => true, 'message' => 'Duplicate transaction'], 200);
      }

      // Process based on transfer type
      if ($transferType === 'in') {
        // Deposit - money coming in
        $success = $this->processDeposit($data);
      } elseif ($transferType === 'out') {
        // Withdrawal - money going out
        $success = $this->processWithdrawal($data);
      } else {
        Log::warning('SEPay webhook: Unknown transfer type', ['type' => $transferType]);
        return response()->json(['success' => false, 'message' => 'Unknown transfer type'], 400);
      }

      if ($success) {
        return response()->json(['success' => true], 201);
      } else {
        return response()->json(['success' => false, 'message' => 'Processing failed'], 500);
      }
    } catch (\Exception $e) {
      Log::error('SEPay webhook error: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'data' => $request->all()
      ]);

      return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
    }
  }

  /**
   * Process deposit transaction
   *
   * @param array $data
   * @return bool
   */
  private function processDeposit($data)
  {
    try {
      $content = $data['content'] ?? '';
      $userId = null;
      $code = null;

      // Try to parse MW pattern first (MW<UserID><Timestamp>)
      // Pattern: MW471732757864 where if length > 10, first part is user ID, last 10 digits are timestamp
      if (preg_match('/MW(\d+)/i', $content, $matches)) {
        $rawId = $matches[1];
        
        // If length > 10, assume last 10 digits are timestamp, rest is user ID
        if (strlen($rawId) > 10) {
          $userId = (int) substr($rawId, 0, -10);
        } else {
          $userId = (int) $rawId;
        }

        Log::info('SEPay webhook: Parsed MW pattern', [
          'raw_id' => $rawId,
          'user_id' => $userId,
          'content' => $content
        ]);
      } else {
        // Try to parse MW pattern (MW{user_id}{timestamp})
        $code = SEPayWebhookService::parseTransactionCode($content);
        if ($code) {
          // Find user by deposit code
          $userId = SEPayWebhookService::findUserByDepositCode($code);
          if (!$userId) {
            Log::warning('SEPay webhook: User not found for deposit code', ['code' => $code]);
            return false;
          }
        } else {
          Log::warning('SEPay webhook: No transaction code found in content', [
            'content' => $content,
            'data' => $data
          ]);
          return false;
        }
      }

      if (!$userId) {
        Log::warning('SEPay webhook: Could not determine user ID', ['content' => $content]);
        return false;
      }

      // Verify user exists
      $user = \App\Models\AuthAccount::find($userId);
      if (!$user) {
        Log::warning('SEPay webhook: User not found', ['user_id' => $userId]);
        return false;
      }

      // Calculate points (amount - fee)
      $amountVND = $data['transferAmount'] ?? 0;
      $feeVND = 1000; // 1.000 VND fee
      $netAmountVND = max(0, $amountVND - $feeVND);
      $points = PointsService::convertVNDToPoints($netAmountVND);

      if ($points <= 0) {
        Log::warning('SEPay webhook: Amount too small after fee', $data);
        return false;
      }

      // Find and mark pending deposit as completed (if using MW code)
      $pendingDeposit = null;
      if ($code) {
        $pendingDeposit = \App\Models\PendingDeposit::where('deposit_code', $code)
          ->where('status', 'pending')
          ->where('user_id', $userId)
          ->first();
      }

      // Add points to user
      DB::transaction(function () use ($userId, $points, $data, $pendingDeposit) {
        PointsService::addPoints(
          $userId,
          $points,
          'deposit',
          "Nạp tiền qua SEPay: " . number_format($data['transferAmount'] ?? 0) . " VND",
          null
        );

        // Store SEPay transaction info
        $transaction = \App\Models\PointsTransaction::where('user_id', $userId)
          ->where('type', 'deposit')
          ->whereNull('sepay_transaction_id')
          ->latest()
          ->first();
        
        if ($transaction) {
          $transaction->update([
            'sepay_transaction_id' => $data['id'],
            'reference_code' => $data['referenceCode'] ?? null,
          ]);
        }

        // Mark pending deposit as completed
        if ($pendingDeposit) {
          $pendingDeposit->update(['status' => 'completed']);
        }
      });

      // Send notification to user
      try {
        \App\Services\NotificationService::createSystemNotification(
          $userId,
          'payment_received',
          [
            'title' => 'Nạp tiền thành công',
            'message' => 'Hệ thống đã nhận được ' . number_format($data['transferAmount'] ?? 0) . "đ và cộng {$points} điểm vào ví của bạn.",
            'url' => '/wallet'
          ]
        );
      } catch (\Exception $e) {
        Log::error('SEPay notification error: ' . $e->getMessage());
      }

      Log::info('SEPay webhook: Successfully processed deposit', [
        'user_id' => $userId,
        'points' => $points,
        'amount_vnd' => $amountVND,
        'sepay_id' => $data['id']
      ]);

      return true;
    } catch (\Exception $e) {
      Log::error('SEPay deposit processing error: ' . $e->getMessage(), [
        'data' => $data,
        'trace' => $e->getTraceAsString()
      ]);
      return false;
    }
  }

  /**
   * Process withdrawal transaction
   *
   * @param array $data
   * @return bool
   */
  private function processWithdrawal($data)
  {
    try {
      $code = SEPayWebhookService::parseTransactionCode($data['content'] ?? '');
      if (!$code) {
        Log::warning('SEPay webhook: No transaction code found', $data);
        return false;
      }

      // Find withdrawal request
      $withdrawalRequest = \App\Models\WithdrawalRequest::where('status', 'approved')
        ->whereRaw("CAST(id AS CHAR) = ?", [$code])
        ->first();

      if (!$withdrawalRequest) {
        Log::warning('SEPay webhook: Withdrawal request not found', ['code' => $code]);
        return false;
      }

      // Update withdrawal request status
      $withdrawalRequest->update(['status' => 'completed']);

      // Create transaction record for tracking
      \App\Models\PointsTransaction::create([
        'user_id' => $withdrawalRequest->user_id,
        'type' => 'withdrawal',
        'amount' => -($withdrawalRequest->amount + 10), // Include fee
        'sepay_transaction_id' => $data['id'],
        'reference_code' => $data['referenceCode'] ?? null,
        'status' => 'completed',
        'description' => "Rút tiền qua SEPay: " . number_format(PointsService::convertPointsToVND($withdrawalRequest->amount)) . " VND",
        'related_id' => $withdrawalRequest->id,
      ]);

      return true;
    } catch (\Exception $e) {
      Log::error('SEPay withdrawal processing error: ' . $e->getMessage(), $data);
      return false;
    }
  }
}

