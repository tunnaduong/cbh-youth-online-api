<?php

namespace App\Services;

use App\Models\PointsTransaction;
use App\Models\AuthAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SEPayWebhookService
{
  /**
   * Parse transaction code from content
   * Expected format: code should be in the content field
   *
   * @param string $content
   * @return string|null
   */
  public static function parseTransactionCode($content)
  {
    // Try to extract code from content
    // Format might be: "NAPTIEN CBH123456" or "CBH123456" or similar
    if (preg_match('/CBH(\d+)/i', $content, $matches)) {
      return $matches[1];
    }

    // Try other patterns
    if (preg_match('/(\d{6,})/', $content, $matches)) {
      return $matches[1];
    }

    return null;
  }

  /**
   * Identify transaction type from code
   *
   * @param string|null $code
   * @return string deposit|withdrawal|null
   */
  public static function identifyTransactionType($code)
  {
    if (!$code) {
      return null;
    }

    // Check if code exists in pending withdrawal requests
    // For now, we'll use a simple heuristic:
    // If code starts with certain prefix, it's withdrawal
    // Otherwise, it's deposit

    // You can implement more sophisticated logic here
    // For example, check withdrawal_requests table for matching codes

    return 'deposit'; // Default to deposit
  }

  /**
   * Process deposit transaction
   * Adds points to user account (minus fee of 10 points = 1.000 VND)
   *
   * @param array $data Webhook data from SEPay
   * @return bool
   */
  public static function processDeposit($data)
  {
    try {
      $code = self::parseTransactionCode($data['content'] ?? '');
      if (!$code) {
        Log::warning('SEPay webhook: No transaction code found in content', $data);
        return false;
      }

      // Find user by code (you may need to store codes when user requests deposit)
      // For now, we'll need to implement a way to map codes to users
      // This could be done by storing pending deposits with codes

      // Extract amount in VND
      $amountVND = $data['transferAmount'] ?? 0;
      $feeVND = 1000; // 1.000 VND fee
      $netAmountVND = max(0, $amountVND - $feeVND);

      // Convert to points
      $points = PointsService::convertVNDToPoints($netAmountVND);

      if ($points <= 0) {
        Log::warning('SEPay webhook: Amount too small after fee', $data);
        return false;
      }

      // TODO: Find user by code - you'll need to implement this
      // For now, return false as we need user mapping
      Log::warning('SEPay webhook: User mapping not implemented yet', ['code' => $code]);

      return false;
    } catch (\Exception $e) {
      Log::error('SEPay webhook deposit processing error: ' . $e->getMessage(), $data);
      return false;
    }
  }

  /**
   * Process withdrawal transaction
   * Deducts points from user account (already includes fee)
   *
   * @param array $data Webhook data from SEPay
   * @return bool
   */
  public static function processWithdrawal($data)
  {
    try {
      $code = self::parseTransactionCode($data['content'] ?? '');
      if (!$code) {
        Log::warning('SEPay webhook: No transaction code found in content', $data);
        return false;
      }

      // Find withdrawal request by code
      $withdrawalRequest = \App\Models\WithdrawalRequest::where('status', 'approved')
        ->whereRaw("CAST(id AS CHAR) = ?", [$code])
        ->first();

      if (!$withdrawalRequest) {
        Log::warning('SEPay webhook: Withdrawal request not found', ['code' => $code]);
        return false;
      }

      // Points were already deducted when admin approved
      // Just mark the transaction as completed
      $withdrawalRequest->update(['status' => 'completed']);

      return true;
    } catch (\Exception $e) {
      Log::error('SEPay webhook withdrawal processing error: ' . $e->getMessage(), $data);
      return false;
    }
  }

  /**
   * Check if transaction is duplicate
   *
   * @param int $sepayId
   * @param string|null $referenceCode
   * @return bool
   */
  public static function isDuplicateTransaction($sepayId, $referenceCode = null)
  {
    $query = PointsTransaction::where('sepay_transaction_id', $sepayId);

    if ($referenceCode) {
      $query->orWhere('reference_code', $referenceCode);
    }

    return $query->exists();
  }

  /**
   * Find user by deposit code
   * Looks up pending deposit by code
   *
   * @param string $code
   * @return int|null User ID
   */
  public static function findUserByDepositCode($code)
  {
    $pendingDeposit = \App\Models\PendingDeposit::where('deposit_code', $code)
      ->where('status', 'pending')
      ->where('expires_at', '>', now())
      ->first();

    if ($pendingDeposit) {
      return $pendingDeposit->user_id;
    }

    return null;
  }
}

