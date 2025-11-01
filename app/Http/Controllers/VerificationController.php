<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuthEmailVerificationCode;
use App\Models\AuthAccount;
use Illuminate\Http\Request;

/**
 * Handles the email verification process.
 */
class VerificationController extends Controller
{
  /**
   * Verify the user's email address.
   *
   * @param  string  $verificationCode
   * @return \Illuminate\Http\JsonResponse
   */
  public function verify($verificationCode)
  {
    $verification = AuthEmailVerificationCode::where('verification_code', $verificationCode)
      ->first();

    if (!$verification) {
      return response()->json(["error" => "Mã xác minh không đúng hoặc đã hết hạn."], 400);
    }

    // Check if code has expired
    if ($verification->expires_at && now()->isAfter($verification->expires_at)) {
      return response()->json(["error" => "Mã xác minh đã hết hạn."], 400);
    }

    // Get the user
    $user = AuthAccount::find($verification->user_id);

    if (!$user) {
      return response()->json(["error" => "Không tìm thấy người dùng."], 404);
    }

    // Mark the user's email as verified if not already verified
    if (!$user->hasVerifiedEmail()) {
      $user->markEmailAsVerified();
    }

    // Delete the verification code as it's been used
    $verification->delete();

    return response()->json([
      "message" => "Xác minh email thành công!",
      "redirect_url" => "/login"
    ], 200);
  }

  /**
   * Resend email verification notification.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function resend(Request $request)
  {
    $user = $request->user();

    if (!$user) {
      return response()->json(['message' => 'Người dùng chưa xác thực.'], 401);
    }

    // Check if email is already verified
    if ($user->hasVerifiedEmail()) {
      return response()->json([
        'message' => 'Email đã được xác minh.',
      ], 200);
    }

    // Delete old verification codes
    AuthEmailVerificationCode::where('user_id', $user->id)->delete();

    // Create new verification code
    $verificationCode = \Illuminate\Support\Str::random(64);
    AuthEmailVerificationCode::create([
      'user_id' => $user->id,
      'verification_code' => $verificationCode,
      'expires_at' => now()->addHours(24), // Expires after 24 hours
    ]);

    // Send verification email
    $user->notify(new \App\Notifications\VerifyEmail);

    return response()->json([
      'message' => 'Email xác minh đã được gửi lại. Vui lòng kiểm tra hộp thư của bạn.',
    ], 200);
  }
}
