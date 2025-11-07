<?php

namespace App\Http\Controllers;

use App\Notifications\VerifyEmail;
use App\Models\AuthAccount;
use App\Models\UserProfile;
use App\Models\UserContent;
use App\Services\NotificationService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    // Create welcome notification for new user
    try {
      NotificationService::createWelcomeNotification($account->id);
    } catch (\Exception $e) {
      // Log error but don't fail registration
      \Log::warning('Failed to create welcome notification', [
        'user_id' => $account->id,
        'error' => $e->getMessage(),
      ]);
    }

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
   * Exchange OAuth authorization code for tokens (Google/Facebook OAuth with PKCE)
   */
  public function exchangeOAuthCode(Request $request)
  {
    // Log request for debugging
    \Log::info('Exchange OAuth Code Request:', [
      'code' => $request->input('code') ? 'present' : 'missing',
      'code_verifier' => $request->input('code_verifier') ? 'present' : 'missing',
      'provider' => $request->input('provider'),
      'all_inputs' => $request->all(),
    ]);

    $validated = $request->validate([
      'code' => 'required|string',
      'code_verifier' => 'required|string',
      'provider' => ['required', 'string', Rule::in(['google', 'facebook'])],
    ]);

    $code = $request->input('code');
    $codeVerifier = $request->input('code_verifier');
    $provider = $request->input('provider');

    try {
      if ($provider === 'google') {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = 'https://api.chuyenbienhoa.com/v1.0/oauth/callback';

        // Exchange authorization code for tokens
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
          'grant_type' => 'authorization_code',
          'code' => $code,
          'redirect_uri' => $redirectUri,
          'client_id' => $clientId,
          'client_secret' => $clientSecret,
          'code_verifier' => $codeVerifier,
        ]);

        if (!$tokenResponse->successful()) {
          $error = $tokenResponse->json();
          return response()->json([
            'message' => 'Token exchange failed',
            'error' => $error,
          ], $tokenResponse->status());
        }

        $tokenData = $tokenResponse->json();

        return response()->json([
          'access_token' => $tokenData['access_token'] ?? null,
          'id_token' => $tokenData['id_token'] ?? null,
          'token_type' => $tokenData['token_type'] ?? 'Bearer',
          'expires_in' => $tokenData['expires_in'] ?? null,
        ]);
      } elseif ($provider === 'facebook') {
        $clientId = config('services.facebook.client_id');
        $clientSecret = config('services.facebook.client_secret');
        $redirectUri = 'https://api.chuyenbienhoa.com/v1.0/oauth/callback';

        // Exchange authorization code for tokens
        // Facebook OAuth with PKCE
        $tokenResponse = Http::asForm()->post('https://graph.facebook.com/v18.0/oauth/access_token', [
          'grant_type' => 'authorization_code',
          'code' => $code,
          'redirect_uri' => $redirectUri,
          'client_id' => $clientId,
          'client_secret' => $clientSecret,
          'code_verifier' => $codeVerifier,
        ]);

        if (!$tokenResponse->successful()) {
          $error = $tokenResponse->json();
          return response()->json([
            'message' => 'Token exchange failed',
            'error' => $error,
          ], $tokenResponse->status());
        }

        $tokenData = $tokenResponse->json();

        return response()->json([
          'access_token' => $tokenData['access_token'] ?? null,
          'token_type' => $tokenData['token_type'] ?? 'Bearer',
          'expires_in' => $tokenData['expires_in'] ?? null,
        ]);
      }

      return response()->json([
        'message' => 'Unsupported provider',
      ], 400);
    } catch (\Exception $e) {
      return response()->json([
        'message' => 'Token exchange failed',
        'error' => $e->getMessage(),
      ], 500);
    }
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
      $email = trim((string) ($verified['email'] ?? ''));
      $name = trim((string) ($verified['name'] ?? ''));

      // Fallback to profile data from client if API doesn't return email
      // (Facebook/Google API sometimes doesn't return email even if user has email)
      $profileData = $request->input('profile');
      if (empty($email) && $profileData && is_array($profileData)) {
        $emailFromProfile = trim((string) ($profileData['email'] ?? ''));
        if (!empty($emailFromProfile)) {
          $email = $emailFromProfile;
        }
        if (empty($name) && isset($profileData['name'])) {
          $name = trim((string) $profileData['name']);
        }
      }

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

        // Check if email exists and is not empty
        // Email is already trimmed above, so just check if it's not empty
        $hasEmail = !empty($email);

        \Log::info('OAuth verified data', $verified);
        \Log::info('Parsed email', ['email' => $email]);

        $user = AuthAccount::create([
          'username' => $username,
          'password' => Hash::make(Str::random(32)),
          'email' => $hasEmail ? $email : null,
          // Set email_verified_at immediately if email exists (OAuth providers verify email)
          // Users logging in via OAuth providers have already verified their email with the provider
          'email_verified_at' => $hasEmail ? now() : null,
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

        // Create welcome notification for new OAuth user
        try {
          NotificationService::createWelcomeNotification($user->id);
        } catch (\Exception $e) {
          // Log error but don't fail login
          \Log::warning('Failed to create welcome notification for OAuth user', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
          ]);
        }
      } else {
        // Ensure provider info is stored/updated
        // Use a clear flag to track if we need to save
        $shouldSave = false;

        if (!$user->provider) {
          $user->provider = $provider;
          $shouldSave = true;
        }
        if (!$user->provider_id && $providerId) {
          $user->provider_id = $providerId;
          $shouldSave = true;
        }
        if ($accessToken && $accessToken !== $user->provider_token) {
          $user->provider_token = $accessToken;
          $shouldSave = true;
        }

        // Update email if provider returns one and it's different
        $hasEmailFromProvider = !empty(trim($email));
        if ($hasEmailFromProvider && $email !== $user->email) {
          $user->email = $email;
          $shouldSave = true;
        }

        // Set email_verified_at if user has email (from provider or already in DB)
        // and is logging in via OAuth provider (which proves email ownership)
        // and not already verified
        // This is the critical fix: check if user has email in DB, regardless of provider response
        $userHasEmail = !empty(trim($user->email ?? ''));
        if ($userHasEmail && !$user->email_verified_at) {
          $user->email_verified_at = now();
          $shouldSave = true;
        }

        // Always save if there are any changes
        if ($shouldSave) {
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
    // Get authorization code or token from query parameters
    $code = $request->query('code');
    $accessToken = $request->query('access_token');
    $error = $request->query('error');
    $errorDescription = $request->query('error_description');
    $state = $request->query('state'); // May contain scheme info
    $providerFromQuery = $request->query('provider'); // May be passed from OAuth provider

    // Try to determine provider from state or query
    // State format could be: "provider:random" or just random
    $provider = 'google'; // Default
    if ($providerFromQuery) {
      $provider = $providerFromQuery;
    } elseif ($state) {
      // Check if state contains provider info (format: "provider:random" or encoded JSON)
      if (strpos($state, 'facebook') !== false) {
        $provider = 'facebook';
      } elseif (strpos($state, 'google') !== false) {
        $provider = 'google';
      }
      // If state doesn't contain provider, try to detect from referrer or other clues
      // Facebook typically has different patterns than Google
    }

    // Additional detection: Check referrer if available
    $referrer = $request->header('referer') ?? $request->header('referrer');
    if (!$providerFromQuery && $referrer) {
      if (strpos($referrer, 'facebook.com') !== false) {
        $provider = 'facebook';
      } elseif (strpos($referrer, 'google.com') !== false || strpos($referrer, 'accounts.google.com') !== false) {
        $provider = 'google';
      }
    }

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
    // Use single colon (:) instead of :// to avoid Android browser stripping trailing slashes
    $deepLink = "{$scheme}:oauth" . ($queryString ? "?{$queryString}" : "");

    // Return HTML page with green button to redirect to app
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chuyển hướng về ứng dụng</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        p {
            color: #666;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 32px;
        }
        .button {
            display: inline-block;
            background-color: #319527;
            color: white;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 4px 12px rgba(49, 149, 39, 0.3);
        }
        .button:hover {
            background-color: #2a7e1f;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(49, 149, 39, 0.4);
        }
        .button:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✓</div>
        <h1>Đăng nhập thành công!</h1>
        <p>Nhấn nút bên dưới để quay lại ứng dụng và hoàn tất đăng nhập.</p>
        <a href="' . htmlspecialchars($deepLink) . '" class="button" id="redirectButton">Quay lại ứng dụng</a>
    </div>
    <script>
        // Fallback: try other schemes if primary fails
        var schemes = ' . json_encode($schemes) . ';
        var queryString = "' . $queryString . '";
        var primaryScheme = "' . $scheme . '";

        document.getElementById("redirectButton").addEventListener("click", function(e) {
            // Try primary scheme first
            // Use single colon (:) instead of :// to avoid Android browser stripping trailing slashes
            var primaryLink = primaryScheme + ":oauth" + (queryString ? "?" + queryString : "");
            window.location.href = primaryLink;

            // Fallback to other schemes after a delay
            setTimeout(function() {
                for (var i = 0; i < schemes.length; i++) {
                    if (schemes[i] !== primaryScheme) {
                        var fallbackLink = schemes[i] + ":oauth" + (queryString ? "?" + queryString : "");
                        window.location.href = fallbackLink;
                        break;
                    }
                }
            }, 500);
        });
    </script>
</body>
</html>';

    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
  }
}
