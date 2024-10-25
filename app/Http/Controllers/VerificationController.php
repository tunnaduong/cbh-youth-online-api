<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthEmailVerificationCode; // Adjust the path as needed
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify($verificationCode)
    {
        $verification = AuthEmailVerificationCode::where('verification_code', $verificationCode)->first();

        if (!$verification) {
            return redirect()->route('login')->with('error', 'Invalid verification code.');
        }

        // Mark the user's email as verified
        $user = $verification->user; // Assuming you have a relationship set up
        $user->markEmailAsVerified();

        // Optionally, delete the verification code record
        // $verification->delete(); // Delete the verification entry

        return response()->json(["message" => "Xác minh email thành công!"], 201);
    }
}
