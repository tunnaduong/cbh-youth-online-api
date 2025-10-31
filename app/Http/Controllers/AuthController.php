<?php

namespace App\Http\Controllers;

use App\Notifications\VerifyEmail;
use App\Models\AuthAccount;
use App\Models\UserProfile;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\AuthEmailVerificationCode;
use Illuminate\Support\Facades\Http;

/**
 * Handles user authentication, including login, registration, and logout.
 */
class AuthController extends Controller
{
  /**
   * Handle a login request to the application.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
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

    // Return structured errors for invalid credentials
    if (!$user) {
      return response()->json([
        'message' => 'Tên tài khoản hoặc mật khẩu sai!',
        'errors' => [
          'username' => 'Tên đăng nhập không chính xác.',
        ],
      ], 401);
    }

    if (!Hash::check($request->password, $user->password)) {
      return response()->json([
        'message' => 'Tên tài khoản hoặc mật khẩu sai!',
        'errors' => [
          'password' => 'Mật khẩu sai.',
        ],
      ], 401);
    }

    // Load the 'profile' relationship if the user exists
    $user->load('profile');

    // Generate an API token (assuming you're using Laravel Sanctum for token-based authentication)
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
      'user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'profile_name' => $user->profile->profile_name ?? null, // Include profile_name if it exists
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'email_verified_at' => $user->email_verified_at,
        'verified' => ($user->profile->verified ?? null) == 1 ? true : false,
        'role' => $user->role ?? null, // Include role if it exists
      ],
      'token' => $token,
    ]);
  }

  /**
   * Handle a registration request for the application.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function register(Request $request)
  {
    $request->validate([
      'username' => [
        'required',
        'string',
        'min:3', // Minimum length
        'max:20', // Maximum length
        'regex:/^[a-zA-Z0-9_]+$/', // No whitespace, no Unicode characters, only alphanumeric and underscore
        'unique:cyo_auth_accounts,username', // Ensure the username is unique in the users table
      ],
      'password' => 'required|string|min:6',
      'email' => 'required|email|unique:cyo_auth_accounts',
      'name' => 'required|string|max:255',
    ]);

    $account = AuthAccount::create([
      'username' => $request->username,
      'password' => Hash::make($request->password),
      'email' => $request->email,
    ]);

    $code = Str::random(64);

    AuthEmailVerificationCode::create([
      'user_id' => $account->id,
      'verification_code' => $code,
      'expires_at' => now()->addHours(1),
    ]);

    UserProfile::create([
      'auth_account_id' => $account->id, // Assuming 'user_id' is a foreign key in cyo_user_profiles
      'profile_username' => $account->username, // Or other default values
      'profile_name' => $request->name,
    ]);

    // Optionally generate a token if using Sanctum/Passport
    $token = $account->createToken('authToken')->plainTextToken;

    // Send the verification email
    $account->notify(new VerifyEmail);

    // Retrieve the user by username or email
    $user = AuthAccount::where('username', $request->username)
      ->orWhere('email', $request->username)
      ->first()->load('profile');

    // Return a success response with the token
    return response()->json([
      'message' => 'Đăng ký thành công! Vui lòng kiểm tra email.',
      'token' => $token,
      'user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'profile_name' => $user->profile->profile_name ?? null, // Include profile_name if it exists
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'email_verified_at' => $user->email_verified_at
      ],
    ], 201);
  }

  /**
   * Log the user out of the application.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
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

  /**
   * Login with OAuth provider (facebook, google)
   */
  public function loginWithProvider(Request $request)
  {
    $request->validate([
      'provider' => 'required|string|in:facebook,google',
      'accessToken' => 'required|string',
      'idToken' => 'nullable|string',
      'profile' => 'nullable|array',
    ]);

    $provider = $request->input('provider');
    $accessToken = $request->input('accessToken');
    $idToken = $request->input('idToken');

    try {
      $verified = null;

      if ($provider === 'facebook') {
        $fbRes = Http::withoutVerifying()->get('https://graph.facebook.com/me', [
          'fields' => 'id,name,email,picture',
          'access_token' => $accessToken,
        ]);
        if (!$fbRes->ok()) {
          return response()->json(['message' => 'Xác minh Facebook token thất bại'], 401);
        }
        $verified = $fbRes->json();
      } elseif ($provider === 'google') {
        if ($idToken) {
          $verifyRes = Http::withoutVerifying()->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
          ]);
          if ($verifyRes->ok()) {
            $verified = $verifyRes->json();
          }
        }
        if (!$verified) {
          $userinfoRes = Http::withoutVerifying()->withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
          if (!$userinfoRes->ok()) {
            return response()->json(['message' => 'Xác minh Google token thất bại'], 401);
          }
          $verified = $userinfoRes->json();
        }
      }

      $providerId = (string)($verified['id'] ?? $verified['sub'] ?? '');
      $email = (string)($verified['email'] ?? '');
      $name = (string)($verified['name'] ?? '');

      if (!$email) {
        return response()->json(['message' => 'Không lấy được email từ nhà cung cấp'], 422);
      }

      $user = AuthAccount::where('email', $email)->first();

      if (!$user) {
        // Create username
        $baseUsername = Str::slug($name ?: ($provider . '-' . substr($providerId, -6)), '_');
        if ($baseUsername === '') {
          $baseUsername = $provider . '_' . substr($providerId ?: Str::random(8), -8);
        }
        $username = $baseUsername;
        $suffix = 1;
        while (AuthAccount::where('username', $username)->exists()) {
          $username = $baseUsername . '_' . $suffix;
          $suffix++;
        }

        $user = AuthAccount::create([
          'username' => $username,
          'password' => Hash::make(Str::random(32)),
          'email' => $email,
          'email_verified_at' => now(),
        ]);

        UserProfile::create([
          'auth_account_id' => $user->id,
          'profile_username' => $user->username,
          'profile_name' => $name ?: $user->username,
        ]);
      }

      $user->load('profile');
      $token = $user->createToken('api-token')->plainTextToken;

      return response()->json([
        'user' => [
          'id' => $user->id,
          'username' => $user->username,
          'email' => $user->email,
          'profile_name' => $user->profile->profile_name ?? null,
          'created_at' => $user->created_at,
          'updated_at' => $user->updated_at,
          'email_verified_at' => $user->email_verified_at,
          'verified' => ($user->profile->verified ?? null) == 1 ? true : false,
          'role' => $user->role ?? null,
        ],
        'token' => $token,
        'accessToken' => $token,
        'refreshToken' => null,
      ]);
    } catch (\Throwable $e) {
      return response()->json([
        'message' => 'Đăng nhập nhà cung cấp thất bại',
        'error' => config('app.debug') ? $e->getMessage() : null,
      ], 500);
    }
  }
}
