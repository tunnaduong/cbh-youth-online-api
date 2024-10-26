<?php

namespace App\Http\Controllers;

use App\Mail\VerifyEmail;
use App\Models\AuthAccount;
use App\Models\UserProfile;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\AuthEmailVerificationCode;

class AuthController extends Controller
{
    //
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Retrieve the user by username or email
        $user = AuthAccount::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        // Check if user exists and the password matches
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Tên tài khoản hoặc mật khảu sai!'], 401);
        }

        // Generate an API token (assuming you're using Laravel Sanctum for token-based authentication)
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }


    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:cyo_auth_accounts',
            'password' => 'required|string|min:6',
            'email' => 'required|email|unique:cyo_auth_accounts',
            'name' => 'required|string|max:255',
        ]);

        $account = AuthAccount::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'email' => $request->email,
        ]);

        UserProfile::create([
            'auth_account_id' => $account->id, // Assuming 'user_id' is a foreign key in cyo_user_profiles
            'profile_username' => $account->username, // Or other default values
            'profile_name' => $request->name,
        ]);

        $verificationCode = AuthEmailVerificationCode::create([
            'user_id' => $account->id,
            'verification_code' => Str::random(60), // Generate token
            'created_at' => now(),
            'expires_at' => now()->addMinutes(30), // Set expiry time for 30 minutes
        ]);


        // Optionally generate a token if using Sanctum/Passport
        $token = $account->createToken('authToken')->plainTextToken;

        // Send the verification email
        Mail::to($account->email)->send(new VerifyEmail($account, $verificationCode->verification_code));

        // Return a success response with the token
        return response()->json([
            'message' => 'Đăng ký thành công! Vui lòng kiểm tra email.',
            'token' => $token,
            'user' => $account,
        ], 201);
    }

    public function logout(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        if ($user) {
            // Revoke the token that was used to authenticate the current request
            $user->currentAccessToken()->delete();

            return response()->json(['message' => 'Đăng xuất thành công.']);
        }

        return response()->json(['message' => 'Người dùng chưa xác thực.'], 401);
    }
}
