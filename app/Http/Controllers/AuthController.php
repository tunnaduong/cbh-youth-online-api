<?php

namespace App\Http\Controllers;

use App\Notifications\VerifyEmail;
use App\Models\AuthAccount;
use App\Models\UserProfile;
use App\Models\UserContent;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Models\AuthEmailVerificationCode;
use Illuminate\Support\Facades\Http;

/**
 * Handles user authentication, including login, registration, and logout.
 */
class AuthController extends Controller
{
  /**
   * Download avatar from URL, process it, and save to storage
   * Returns UserContent ID or null on failure
   */
  private function downloadAndSaveAvatar($avatarUrl, $userId)
  {
    try {
      // Download the image from URL
      $imageData = Http::timeout(10)->get($avatarUrl);
      if (!$imageData->successful()) {
        return null;
      }

      // Create image from downloaded data
      $image = Image::make($imageData->body());

      // Crop and resize to 500x500 (1:1 ratio)
      $size = min($image->width(), $image->height());
      $image->crop($size, $size)->resize(500, 500);

      // Generate filename
      $fileName = time() . '_' . $userId . '_oauth_avatar.jpg';
      $filePath = 'avatars/' . $fileName;

      // Save to storage
      Storage::disk('public')->put($filePath, (string) $image->encode('jpg', 90));

      // Create UserContent record
      $userContent = UserContent::create([
        'user_id' => $userId,
        'file_name' => $fileName,
        'file_path' => $filePath,
        'file_type' => 'image/jpeg',
        'file_size' => Storage::disk('public')->size($filePath),
      ]);

      return $userContent->id;
    } catch (\Throwable $e) {
      // Log error but don't fail the login process
      \Log::warning('Failed to download OAuth avatar', [
        'url' => $avatarUrl,
        'error' => $e->getMessage(),
      ]);
      return null;
    }
  }
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
          'fields' => 'id,name,email,picture.type(large)',
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

      $providerId = (string) ($verified['id'] ?? $verified['sub'] ?? '');
      $email = (string) ($verified['email'] ?? '');
      $name = (string) ($verified['name'] ?? '');

      // Extract avatar URL from provider response
      $avatarUrl = null;
      if ($provider === 'facebook') {
        // Facebook: picture is an object with data.url
        if (isset($verified['picture']['data']['url'])) {
          $avatarUrl = $verified['picture']['data']['url'];
        } elseif (is_string($verified['picture'] ?? null)) {
          // If picture is directly a string URL
          $avatarUrl = $verified['picture'];
        }
      } elseif ($provider === 'google') {
        // Google: picture is directly a URL string
        $avatarUrl = $verified['picture'] ?? null;
      }

      // Find by provider or email when available
      $user = null;
      if ($providerId !== '') {
        $user = AuthAccount::where('provider', $provider)
          ->where('provider_id', $providerId)
          ->first();
      }
      if (!$user && $email) {
        $user = AuthAccount::where('email', $email)->first();
      }

      if (!$user) {
        // Create username (fallback to provider id when email missing)
        $fallbackId = $providerId !== '' ? substr($providerId, -6) : Str::random(6);
        $baseUsername = Str::slug($name ?: ($provider . '-' . $fallbackId), '_');
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
          'email' => $email ?: null,
          'email_verified_at' => $email ? now() : null,
          'provider' => $provider,
          'provider_id' => $providerId ?: null,
          'provider_token' => $accessToken,
        ]);

        // Download and save avatar if available
        $avatarContentId = null;
        if ($avatarUrl) {
          $avatarContentId = $this->downloadAndSaveAvatar($avatarUrl, $user->id);
        }

        UserProfile::create([
          'auth_account_id' => $user->id,
          'profile_username' => $user->username,
          'profile_name' => $name ?: $user->username,
          'profile_picture' => $avatarContentId,
        ]);
      } else {
        // Ensure provider info is stored/updated
        $dirty = false;
        if (!$user->provider) {
          $user->provider = $provider;
          $dirty = true;
        }
        if (!$user->provider_id && $providerId) {
          $user->provider_id = $providerId;
          $dirty = true;
        }
        if ($accessToken) {
          $user->provider_token = $accessToken;
          $dirty = true;
        }
        // Set email_verified_at if email is returned from provider and not already verified
        if ($email && !$user->email_verified_at) {
          $user->email_verified_at = now();
          $dirty = true;
        }
        if ($dirty) {
          $user->save();
        }

      }

      // Load profile and update avatar if available
      $user->load('profile');
      if ($user->profile && $avatarUrl) {
        // Download and save avatar if not already set or if we want to refresh it
        // Only update if profile_picture is not set yet
        if (!$user->profile->profile_picture) {
          $avatarContentId = $this->downloadAndSaveAvatar($avatarUrl, $user->id);
          if ($avatarContentId) {
            $user->profile->profile_picture = $avatarContentId;
            $user->profile->save();
          }
        }
      }
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

  /**
   * Handle OAuth callback from Google/Facebook
   * Redirects back to mobile app with authorization code or token
   */
  public function oauthCallback(Request $request)
  {
    // Extract provider from query or determine from request
    $provider = $request->query('provider', 'google');

    // Get authorization code or token from query parameters
    $code = $request->query('code');
    $accessToken = $request->query('access_token');
    $error = $request->query('error');
    $errorDescription = $request->query('error_description');
    $state = $request->query('state'); // May contain scheme info

    // Build query parameters
    $params = [];
    if ($error) {
      $params['error'] = $error;
      if ($errorDescription) {
        $params['error_description'] = $errorDescription;
      }
    } elseif ($code) {
      $params['code'] = $code;
      $params['provider'] = $provider;
    } elseif ($accessToken) {
      $params['access_token'] = $accessToken;
      $params['provider'] = $provider;
    } else {
      $params['error'] = 'invalid_request';
    }

    $queryString = http_build_query($params);

    // Support multiple schemes for local development and production
    // Try production scheme first, then local scheme
    $schemes = [
      'com.fatties.youth', // Production scheme
      'exp+cbh-youth-online-mobile', // Expo local development scheme
    ];

    // Try to determine scheme from state or use default
    $scheme = $schemes[0]; // Default to production scheme
    if ($state) {
      // Check if state contains scheme info
      foreach ($schemes as $s) {
        if (strpos($state, $s) !== false) {
          $scheme = $s;
          break;
        }
      }
    }

    // Build deep link URL
    $deepLink = "{$scheme}://oauth" . ($queryString ? "?{$queryString}" : "");

    // Return HTML page that tries multiple schemes for better compatibility
    // This helps with local development where scheme might vary
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting...</title>
    <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($deepLink) . '">
    <script>
        // Try to open app with multiple schemes
        var schemes = ' . json_encode($schemes) . ';
        var params = ' . json_encode($params) . ';
        var queryString = "' . $queryString . '";

        // Try primary scheme first
        window.location.href = "' . $scheme . '://oauth" + (queryString ? "?" + queryString : "");

        // Fallback: try other schemes after a short delay
        setTimeout(function() {
            for (var i = 0; i < schemes.length; i++) {
                if (schemes[i] !== "' . $scheme . '") {
                    var link = schemes[i] + "://oauth" + (queryString ? "?" + queryString : "");
                    window.location.href = link;
                    break;
                }
            }
        }, 100);
    </script>
</head>
<body>
    <p>Đang chuyển hướng về ứng dụng...</p>
    <p>Nếu không tự động chuyển, <a href="' . htmlspecialchars($deepLink) . '">click vào đây</a></p>
</body>
</html>';

    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
  }
}
