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
}
