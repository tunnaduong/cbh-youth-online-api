<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuthEmailVerificationCode; // Adjust the path as needed
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify($verificationCode)
    {
        $verification = AuthEmailVerificationCode::where('verification_code', $verificationCode)->first();

        if (!$verification) {
            // return redirect()->route('login')->with('error', 'Invalid verification code.');
            return response()->json(["error" => "Mã xác minh không đúng."], 201);
        }

        // Mark the user's email as verified
        $user = $verification->user; // Assuming you have a relationship set up
        $user->markEmailAsVerified();

        // Optionally, delete the verification code record
        // $verification->delete(); // Delete the verification entry

        return response()->json(["message" => "Xác minh email thành công!", "redirect_url" => "/login"], 200);
        // return redirect()->route('login')->with('status', 'Email verified successfully!');
    }
}
