<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Find the user by their username
        $user = AuthAccount::where('username', $request->username)->first();

        // Check if user exists and the password matches
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
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
        Log::info('Register method called');
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

        // Optionally generate a token if using Sanctum/Passport
        $token = $account->createToken('authToken')->plainTextToken;

        // Return a success response with the token
        return response()->json([
            'message' => 'User registered successfully!',
            'token' => $token,
            'user' => $account,
        ], 201);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}
