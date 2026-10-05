<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\AuthEmailVerificationCode;
use App\Models\UserContent;
use App\Models\UserProfile;
use App\Notifications\VerifyEmail;
use App\Services\DeviceSessionService;
use App\Services\NotificationService;
use App\Services\LoginApprovalService;
use App\Services\TwoFactorService;
use App\Services\WebAuthnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;

/**
 * Handles user authentication, including login, registration, and logout.
 */
class AuthController extends Controller
{
  /**
   * Build the standard 403 JSON response for a banned account trying to log in.
   *
   * @param  \App\Models\AuthAccount  $user
   * @return \Illuminate\Http\JsonResponse
   */
  private function bannedResponse($user)
  {
    $bannedUntil = $user->banned_until;

    $message = $bannedUntil
      ? 'Tài khoản của bạn đã bị khóa đến ' . $bannedUntil->format('d/m/Y H:i') . '.'
      : 'Tài khoản của bạn đã bị khóa vĩnh viễn.';

    if ($user->ban_reason) {
      $message .= ' Lý do: ' . $user->ban_reason;
    }

    return response()->json([
      'message' => $message,
      'banned' => true,
      'ban_reason' => $user->ban_reason,
      'banned_until' => $bannedUntil,
    ], 403);
  }

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
   * Cloudflare Turnstile on the password login, for logins made from a web
   * browser. Off until services.turnstile.secret (TURNSTILE_SECRET_KEY) is
   * set.
   *
   * The mobile apps have no Turnstile widget and are let through (they say
   * who they are with X-Client-Platform; older versions are recognised by
   * not having a browser's User-Agent). A bot can claim to be the app, so
   * this stops bots driving the web form or replaying its requests - not a
   * script written against the API itself.
   */
  private function passesBotCheck(Request $request): bool
  {
    $secret = (string) config('services.turnstile.secret');
    if ($secret === '') {
      return true;
    }

    $platform = strtolower((string) $request->header('X-Client-Platform'));
    $fromBrowser = $platform === 'web'
      || ($platform === '' && str_starts_with((string) $request->userAgent(), 'Mozilla/'));
    if (!$fromBrowser) {
      return true;
    }

    $token = (string) $request->input('turnstile_token');
    if ($token === '') {
      return false;
    }

    try {
      $response = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $request->ip(),
      ]);
    } catch (\Throwable $e) {
      // Cloudflare can't be reached: don't lock everybody out of the site.
      report($e);

      return true;
    }

    if (!$response->successful()) {
      return true;
    }

    return (bool) $response->json('success');
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
      'device_token' => 'nullable|string',
      'turnstile_token' => 'nullable|string|max:4096',
    ]);

    if (!$this->passesBotCheck($request)) {
      return response()->json([
        'message' => 'Không xác minh được bạn không phải robot. Vui lòng tải lại trang và thử lại.',
        'errors' => [
          'captcha' => 'Không xác minh được bạn không phải robot. Vui lòng tải lại trang và thử lại.',
        ],
        'captcha_required' => true,
      ], 422);
    }

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

    if ($user->isCurrentlyBanned()) {
      return $this->bannedResponse($user);
    }

    // Accounts with two-factor on don't get a token yet - the client has to
    // finish the challenge through loginTwoFactor() first (unless it comes
    // from a device the user chose to remember).
    if ($challenge = TwoFactorService::challengeFor($user, $request->input('device_token'))) {
      return response()->json($challenge);
    }

    return response()->json($this->loginResponseData($user, $request));
  }

  /**
   * Issue an API token and build the payload every successful login returns.
   *
   * @param  \App\Models\AuthAccount  $user
   * @param  \Illuminate\Http\Request  $request
   * @return array
   */
  private function loginResponseData($user, Request $request, string $method = 'password', bool $twoFactor = false)
  {
    // Load the 'profile' relationship if the user exists
    $user->load('profile');

    // Generate an API token (assuming you're using Laravel Sanctum for token-based authentication)
    $newToken = $user->createToken('api-token');

    // Note which device this login is on, and email the owner if the account
    // has never been used on it before (not for an account created just now).
    DeviceSessionService::recordLogin($user, $newToken->accessToken, $request, !$user->wasRecentlyCreated);
    DeviceSessionService::recordLoginMethod($newToken->accessToken, $method, $twoFactor);

    return [
      'user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'profile_name' => $user->profile->profile_name ?? null,  // Include profile_name if it exists
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'email_verified_at' => $user->email_verified_at,
        'verified' => ($user->profile->verified ?? null) == 1 ? true : false,
        'role' => $user->role ?? null,  // Include role if it exists
        // Name style, avatar frame, name icon: so the client can draw the
        // signed-in user (header, sidebar) without another request.
        'profile_theme' => \App\Services\ProfileThemeService::forAuthor($user),
      ],
      'token' => $newToken->plainTextToken,
    ];
  }

  /**
   * Finish a login that was paused for two-factor: check the code (from the
   * authenticator app, the email, or a recovery code) and issue the token.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function loginTwoFactor(Request $request)
  {
    $request->validate([
      'challenge_token' => 'required|string',
      'code' => 'required|string|max:20',
      // Which method the user picked when several are on (optional: without
      // it the code is checked against each of them).
      'method' => 'nullable|string|in:totp,email',
      'remember_device' => 'nullable|boolean',
      'device_name' => 'nullable|string|max:255',
      'device_token' => 'nullable|string',
    ]);

    $challengeToken = $request->input('challenge_token');
    $user = TwoFactorService::challengeUser($challengeToken);

    if (!$user || !$user->hasTwoFactorEnabled()) {
      return $this->challengeExpiredResponse();
    }

    if ($user->isCurrentlyBanned()) {
      TwoFactorService::forgetChallenge($challengeToken);

      return $this->bannedResponse($user);
    }

    if (TwoFactorService::tooManyFailures($user)) {
      return response()->json([
        'message' => 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau '
          . TwoFactorService::failureLockMinutes($user) . ' phút.',
      ], 429);
    }

    $used = TwoFactorService::verifyAny($user, $request->input('code'), 'login', $request->input('method'));

    if (!$used) {
      TwoFactorService::hitFailure($user);
      TwoFactorService::recordChallengeFailure($challengeToken);

      return response()->json([
        'message' => 'Mã xác thực không đúng hoặc đã hết hạn.',
        'errors' => [
          'code' => 'Mã xác thực không đúng hoặc đã hết hạn.',
        ],
      ], 422);
    }

    TwoFactorService::clearFailures($user);

    // The challenge came from a social login matched by email: attach that
    // provider account now, so its next logins skip the second step. An
    // account already linked to a provider keeps that link.
    $link = TwoFactorService::challengeLink($challengeToken);
    if ($link && !$user->provider && !$user->provider_id) {
      $user->forceFill([
        'provider' => $link['provider'],
        'provider_id' => $link['provider_id'],
      ])->save();
    }

    TwoFactorService::forgetChallenge($challengeToken);

    // The first step was either the password or a social login.
    $data = $this->loginResponseData($user, $request, $link['provider'] ?? 'password', true);

    if ($request->boolean('remember_device')) {
      $data['device_token'] = TwoFactorService::trustDevice(
        $user,
        $request->input('device_name') ?: $request->userAgent(),
        $request->input('device_token')
      );
    }

    // Lets the client warn the user when they are running low.
    if ($used === 'recovery') {
      $data['recovery_codes_remaining'] = count($user->two_factor_recovery_codes ?? []);
    }

    return response()->json($data);
  }

  /**
   * Two-factor by approval on a logged-in device: start (or restart) the
   * request for a pending login challenge. The account's other devices are
   * notified; the answer is the number to show on this one.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function startLoginApproval(Request $request)
  {
    $request->validate(['challenge_token' => 'required|string']);

    $challengeToken = $request->input('challenge_token');
    $user = TwoFactorService::challengeUser($challengeToken);

    if (!$user || !$user->hasTwoFactorEnabled()) {
      return $this->challengeExpiredResponse();
    }

    if (!TwoFactorService::isMethodEnabled($user, TwoFactorService::METHOD_DEVICE)) {
      return response()->json([
        'message' => 'Tài khoản này không dùng xác nhận trên thiết bị đã đăng nhập.',
      ], 422);
    }

    if ($user->isCurrentlyBanned()) {
      TwoFactorService::forgetChallenge($challengeToken);

      return $this->bannedResponse($user);
    }

    $approval = LoginApprovalService::start($user, $challengeToken, $request);

    if (!$approval) {
      return response()->json([
        'message' => 'Bạn đã gửi yêu cầu quá nhiều lần. Hãy dùng phương thức khác hoặc đăng nhập lại.',
      ], 429);
    }

    return response()->json($approval);
  }

  /**
   * Polled by the device logging in. While nobody has answered: status
   * "pending". Denied or timed out: "denied" / "expired". Approved: the
   * normal login payload (once), plus status "approved".
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function loginApprovalStatus(Request $request)
  {
    $request->validate([
      'challenge_token' => 'required|string',
      'remember_device' => 'nullable|boolean',
      'device_name' => 'nullable|string|max:255',
      'device_token' => 'nullable|string',
    ]);

    $challengeToken = $request->input('challenge_token');
    $user = TwoFactorService::challengeUser($challengeToken);

    if (!$user || !TwoFactorService::isMethodEnabled($user, TwoFactorService::METHOD_DEVICE)) {
      return $this->challengeExpiredResponse();
    }

    $status = LoginApprovalService::statusForChallenge($challengeToken);

    if ($status !== LoginApprovalService::APPROVED) {
      return response()->json(['status' => $status]);
    }

    if ($user->isCurrentlyBanned()) {
      TwoFactorService::forgetChallenge($challengeToken);

      return $this->bannedResponse($user);
    }

    // Once: a second poll that raced this one finds nothing to consume.
    if (!LoginApprovalService::consume($challengeToken)) {
      return response()->json(['status' => 'expired']);
    }

    // Same hand-over as a typed code (see loginTwoFactor).
    $link = TwoFactorService::challengeLink($challengeToken);
    if ($link && !$user->provider && !$user->provider_id) {
      $user->forceFill([
        'provider' => $link['provider'],
        'provider_id' => $link['provider_id'],
      ])->save();
    }

    TwoFactorService::forgetChallenge($challengeToken);

    $data = $this->loginResponseData($user, $request, $link['provider'] ?? 'password', true);

    if ($request->boolean('remember_device')) {
      $data['device_token'] = TwoFactorService::trustDevice(
        $user,
        $request->input('device_name') ?: $request->userAgent(),
        $request->input('device_token')
      );
    }

    return response()->json($data + ['status' => LoginApprovalService::APPROVED]);
  }

  /**
   * Start a passkey login: the challenge for navigator.credentials.get().
   * No username is involved - the device offers the passkeys it holds.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function passkeyLoginOptions()
  {
    return response()->json(WebAuthnService::loginOptions());
  }

  /**
   * Finish a passkey login. The passkey is the whole login: no password and
   * no two-factor step (it is bound to the user's device and unlocked by its
   * screen lock, which already is two factors).
   *
   * The mobile app runs the passkey prompt in its in-app browser, where it
   * must not receive the token directly: it sends `app_challenge`
   * (sha256 of a secret it keeps) and gets a one-time `code` back, which the
   * app itself exchanges through redeemPasskeyLogin() with the secret.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function passkeyLogin(Request $request)
  {
    $request->validate([
      'request_id' => 'required|string|max:64',
      'credential' => 'required|array',
      'credential.id' => 'required|string|max:2000',
      'credential.response.clientDataJSON' => 'required|string',
      'credential.response.authenticatorData' => 'required|string',
      'credential.response.signature' => 'required|string',
      'app_challenge' => 'nullable|string|size:64',
    ]);

    $user = WebAuthnService::verifyLogin($request->input('credential'), $request->input('request_id'));

    if (!$user) {
      // 422, not 401: the clients treat 401 as "your session died".
      return response()->json([
        'message' => 'Không thể đăng nhập bằng passkey này. Vui lòng thử lại.',
      ], 422);
    }

    if ($user->isCurrentlyBanned()) {
      return $this->bannedResponse($user);
    }

    if ($request->filled('app_challenge')) {
      $code = Str::random(64);
      Cache::put('passkey_login:' . hash('sha256', $code), [
        'user_id' => $user->id,
        'app_challenge' => strtolower($request->input('app_challenge')),
      ], 120);

      return response()->json(['code' => $code]);
    }

    return response()->json($this->loginResponseData($user, $request, 'passkey'));
  }

  /**
   * Mobile app: exchange the one-time code from passkeyLogin() for a token,
   * proving with `verifier` that this is the app that started the login.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function redeemPasskeyLogin(Request $request)
  {
    $request->validate([
      'code' => 'required|string|size:64',
      'verifier' => 'required|string|min:32|max:128',
    ]);

    // pull = read + delete, so a code works exactly once.
    $pending = Cache::pull('passkey_login:' . hash('sha256', $request->input('code')));
    $user = $pending ? AuthAccount::find($pending['user_id']) : null;

    if (!$user || !hash_equals($pending['app_challenge'], hash('sha256', $request->input('verifier')))) {
      return response()->json([
        'message' => 'Phiên đăng nhập bằng passkey đã hết hạn. Vui lòng thử lại.',
      ], 422);
    }

    if ($user->isCurrentlyBanned()) {
      return $this->bannedResponse($user);
    }

    return response()->json($this->loginResponseData($user, $request, 'passkey'));
  }

  /**
   * Email a code for a pending two-factor login: a re-send, or the first
   * send when the user picks email instead of the method offered first.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function resendTwoFactorCode(Request $request)
  {
    $request->validate([
      'challenge_token' => 'required|string',
    ]);

    $user = TwoFactorService::challengeUser($request->input('challenge_token'));

    if (!$user || !$user->hasTwoFactorEnabled()) {
      return $this->challengeExpiredResponse();
    }

    if (!TwoFactorService::isMethodEnabled($user, TwoFactorService::METHOD_EMAIL)) {
      return response()->json([
        'message' => 'Tài khoản này chưa bật mã xác thực qua email.',
      ], 422);
    }

    $wait = TwoFactorService::emailCooldown($user, 'login');
    if ($wait > 0) {
      return response()->json([
        'message' => "Vui lòng đợi {$wait} giây trước khi gửi lại mã.",
        'retry_after' => $wait,
      ], 429);
    }

    TwoFactorService::sendEmailCode($user, 'login');

    return response()->json([
      'message' => 'Đã gửi lại mã xác thực.',
      'email' => TwoFactorService::maskEmail($user->email),
    ]);
  }

  /**
   * 410 rather than 401: the clients treat any 401 as "your session died"
   * and sign the user out, which is not what an expired challenge means.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  private function challengeExpiredResponse()
  {
    return response()->json([
      'message' => 'Phiên xác thực đã hết hạn. Vui lòng đăng nhập lại.',
      'challenge_expired' => true,
    ], 410);
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
        'min:3',  // Minimum length
        'max:21',  // Maximum length
        'regex:/^[a-zA-Z0-9_.-]+$/',  // No whitespace, no Unicode characters, only alphanumeric, underscore and dash
        'unique:cyo_auth_accounts,username',  // Ensure the username is unique in the users table
        'not_in:all',  // Reserved: conflicts with @all mention-everyone
        // Reserved: AI persona usernames (e.g. yoyo.ai) - belt-and-suspenders
        // on top of the 'unique' rule above, in case an AI account is ever
        // temporarily absent when this runs. Case-insensitive since
        // usernames aren't otherwise unique-checked case-sensitively either.
        function ($attribute, $value, $fail) {
          $reserved = AuthAccount::where('is_ai', true)->pluck('username');
          if ($reserved->contains(fn($u) => strcasecmp($u, $value) === 0)) {
            $fail('Tên người dùng này đã được sử dụng.');
          }
        },
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
      'auth_account_id' => $account->id,  // Assuming 'user_id' is a foreign key in cyo_user_profiles
      'profile_username' => $account->username,  // Or other default values
      'profile_name' => $request->name,
    ]);

    // Optionally generate a token if using Sanctum/Passport
    $newToken = $account->createToken('authToken');
    $token = $newToken->plainTextToken;

    // Remember the device they signed up on, so it isn't reported as a new
    // device the next time they log in from it.
    DeviceSessionService::recordLogin($account, $newToken->accessToken, $request, false);
    DeviceSessionService::recordLoginMethod($newToken->accessToken, 'register');

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

    // Also drop a welcome chat message into their inbox from an admin
    // account - covers both the mobile app and the web frontend, since both
    // register through this same endpoint.
    try {
      app(ChatController::class)->sendWelcomeMessage($account);
    } catch (\Exception $e) {
      \Log::warning('Failed to send welcome chat message', [
        'user_id' => $account->id,
        'error' => $e->getMessage(),
      ]);
    }

    // Retrieve the user by username or email
    $user = AuthAccount::where('username', $request->username)
      ->orWhere('email', $request->username)
      ->first()
      ->load('profile');

    // Return a success response with the token
    return response()->json([
      'message' => 'Đăng ký thành công! Vui lòng kiểm tra email.',
      'token' => $token,
      'user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'profile_name' => $user->profile->profile_name ?? null,  // Include profile_name if it exists
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
      // Revoke the token that was used to authenticate the current request,
      // and the web sessions it handed over (the app's WebViews and in-app
      // browser are logged out together with the app).
      $current = $user->currentAccessToken();
      if ($current instanceof \Laravel\Sanctum\PersonalAccessToken) {
        DeviceSessionService::revokeHandedOver($user, (int) $current->id);
      }
      $current->delete();

      return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    return response()->json(['message' => 'Người dùng chưa xác thực.'], 401);
  }

  /**
   * Issue a short-lived, single-use code the mobile app can hand to the web
   * site (via the in-app browser) so the user lands there already signed in.
   *
   * The in-app browser keeps its own cookie jar the app can't write into, so
   * the only way across is a URL - and a URL ends up in browser history (and,
   * on Android, Chrome's synced history). Putting the bearer token itself
   * there would leak a long-lived credential, so the URL carries this code
   * instead: it expires after a minute and stops working once redeemed.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function createWebHandoff(Request $request)
  {
    $code = Str::random(64);
    // With the id of the app's own token, so the web session can be ended
    // together with it (see DeviceSessionService::revokeHandedOver).
    $current = $request->user()->currentAccessToken();
    Cache::put('web_handoff:' . $code, [
      'user' => $request->user()->id,
      'token' => $current instanceof \Laravel\Sanctum\PersonalAccessToken ? (int) $current->id : null,
    ], now()->addSeconds(60));

    return response()->json(['code' => $code, 'expires_in' => 60]);
  }

  /**
   * End the web sessions this login handed over (the app's WebViews and
   * in-app browser), keeping the login itself. The mobile app calls it when
   * the user switches to another saved account or adds one: the account being
   * left stays signed in on the device, but nothing may stay signed in as it
   * on the web.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function revokeWebHandoffs(Request $request)
  {
    $current = $request->user()->currentAccessToken();
    $revoked = $current instanceof \Laravel\Sanctum\PersonalAccessToken
      ? DeviceSessionService::revokeHandedOver($request->user(), (int) $current->id)
      : [];

    return response()->json(['revoked' => count($revoked)]);
  }

  /**
   * Redeem a code from createWebHandoff for a fresh Sanctum token belonging
   * to the web session. A separate token (rather than the app's own) means
   * signing out on the web doesn't sign the app out, and vice versa.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function redeemWebHandoff(Request $request)
  {
    $request->validate(['code' => 'required|string|size:64']);

    // pull = read + delete, so a code works exactly once.
    // An array since the app's token id travels with it; a bare user id is a
    // code issued just before that change was deployed.
    $handoff = Cache::pull('web_handoff:' . $request->input('code'));
    $userId = is_array($handoff) ? ($handoff['user'] ?? null) : $handoff;
    $appTokenId = is_array($handoff) ? ($handoff['token'] ?? null) : null;
    $user = $userId ? AuthAccount::find($userId) : null;

    if (!$user) {
      return response()->json(['message' => 'Mã đăng nhập không hợp lệ hoặc đã hết hạn.'], 401);
    }

    if ($user->isCurrentlyBanned()) {
      return $this->bannedResponse($user);
    }

    $newToken = $user->createToken(DeviceSessionService::handoffTokenName($appTokenId));
    DeviceSessionService::recordLoginMethod($newToken->accessToken, 'app');

    return response()->json([
      'token' => $newToken->plainTextToken,
    ]);
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
      'provider' => 'required|string|in:facebook,google,apple',
      'accessToken' => 'nullable|string',  // accessToken is not always required for Apple
      'idToken' => 'nullable|string',
      'profile' => 'nullable|array',
      'email' => 'nullable|string',  // Allow direct email input (from Apple)
      'fullName' => 'nullable',  // Allow direct name input (from Apple)
      'device_token' => 'nullable|string',
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
          $userinfoRes = Http::withoutVerifying()
            ->withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
          if (!$userinfoRes->ok()) {
            return response()->json(['message' => 'Xác minh Google token thất bại'], 401);
          }
          $verified = $userinfoRes->json();
        }
      } elseif ($provider === 'apple') {
        if (!$idToken) {
          return response()->json(['message' => 'Apple Sign In yêu cầu idToken'], 400);
        }

        // Verifies the RS256 signature against Apple's published public keys
        // plus issuer/expiry (see AppleIdTokenVerifier) - previously this just
        // base64-decoded the payload and trusted whatever "sub"/"email" it
        // contained, so anyone could forge a token claiming to be any Apple
        // user and get logged in (or a new account created) under that identity.
        $payload = \App\Services\AppleIdTokenVerifier::verify($idToken);
        if ($payload) {
          $verified = [
            'id' => $payload['sub'],
            'email' => $payload['email'] ?? $request->input('email'),
            // Not in the signed token = only the client's word for it.
            'email_from_client' => !isset($payload['email']),
            'email_verified' => $payload['email_verified'] ?? false,
          ];

          // Get name from request if available (only sent on first login)
          $fullName = $request->input('fullName');
          if ($fullName) {
            if (is_array($fullName)) {
              $givenName = $fullName['givenName'] ?? '';
              $familyName = $fullName['familyName'] ?? '';
              $verified['name'] = trim("$givenName $familyName");
            } else {
              $verified['name'] = $fullName;
            }
          }
        }

        if (!$verified) {
          return response()->json(['message' => 'Token Apple không hợp lệ'], 401);
        }
      }

      $providerId = (string) ($verified['id'] ?? $verified['sub'] ?? '');
      $email = trim((string) ($verified['email'] ?? ''));
      $name = trim((string) ($verified['name'] ?? ''));

      // Whether the email was vouched for by the provider itself. Only such
      // an email may be used to find an existing account: an email the
      // client merely typed into the request proves nothing, and matching on
      // it let anyone log in as the owner of any address.
      $emailTrusted = $email !== '' && empty($verified['email_from_client']);

      // Fallback to profile data from client if API doesn't return email
      // (Facebook/Google API sometimes doesn't return email even if user has email)
      $profileData = $request->input('profile');
      if (empty($email) && $profileData && is_array($profileData)) {
        $emailFromProfile = trim((string) ($profileData['email'] ?? ''));
        if (!empty($emailFromProfile)) {
          $email = $emailFromProfile;
          $emailTrusted = false;
        }
        if (empty($name) && isset($profileData['name'])) {
          $name = trim((string) $profileData['name']);
        }
      }

      // Separate check for Apple name if not found in verified passed from profile logic
      if ($provider === 'apple' && empty($name)) {
        $fullName = $request->input('fullName');
        if ($fullName && is_array($fullName)) {
          $givenName = $fullName['givenName'] ?? '';
          $familyName = $fullName['familyName'] ?? '';
          $name = trim("$givenName $familyName");
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
      if (!$user && $email && $emailTrusted) {
        $user = AuthAccount::where('email', $email)->first();
      }

      // An unverified email that already belongs to an account is not
      // attached to the new one: it could be someone else's address.
      if (!$user && $email && !$emailTrusted && AuthAccount::where('email', $email)->exists()) {
        $email = '';
      }

      if (!$user) {
        // Prefer email prefix (e.g. tunnaduong from tunnaduong@gmail.com), then
        // display name, then provider id as a last resort.
        $fallbackId = $providerId !== '' ? substr($providerId, -6) : Str::random(6);
        $emailPrefix = $email ? Str::slug(explode('@', $email)[0], '_') : '';
        $baseUsername = $emailPrefix
          ?: Str::slug($name ?: ($provider . '-' . $fallbackId), '_');
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
          'email_verified_at' => $hasEmail && $emailTrusted ? now() : null,
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

        // Same admin welcome chat message as a regular register() signup -
        // this branch is also a brand new account, just created via OAuth.
        try {
          app(ChatController::class)->sendWelcomeMessage($user);
        } catch (\Exception $e) {
          \Log::warning('Failed to send welcome chat message for OAuth user', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
          ]);
        }
      } else {
        // Whether a login through Google/Facebook/Apple still goes through
        // two-factor is the user's own setting (on = skip, the default: the
        // provider has already signed the person in). When they turned the
        // skip off, the challenge comes before anything below touches the
        // account.
        if (
          !$user->skipsTwoFactorOnSocialLogin()
          && !$user->isCurrentlyBanned()
          && ($challenge = TwoFactorService::challengeFor($user, $request->input('device_token'), [
            'provider' => $provider,
            'provider_id' => $providerId,
          ]))
        ) {
          return response()->json($challenge);
        }

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
        $hasEmailFromProvider = !empty(trim($email)) && $emailTrusted;
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

      if ($user->isCurrentlyBanned()) {
        return $this->bannedResponse($user);
      }

      $data = $this->loginResponseData($user, $request, $provider);

      return response()->json($data + [
        'accessToken' => $data['token'],
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
    $state = $request->query('state');  // May contain scheme info
    $providerFromQuery = $request->query('provider');  // May be passed from OAuth provider

    // Try to determine provider from state or query
    // State format could be: "provider:random" or just random
    $provider = 'google';  // Default
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
      // Preserve state parameter for OAuth state verification
      if ($state) {
        $params['state'] = $state;
      }
    } elseif ($accessToken) {
      $params['access_token'] = $accessToken;
      $params['provider'] = $provider;
      // Preserve state parameter for OAuth state verification
      if ($state) {
        $params['state'] = $state;
      }
    } else {
      $params['error'] = 'invalid_request';
    }

    $queryString = http_build_query($params);

    // Support multiple schemes for local development and production
    // Try production scheme first, then local scheme
    $schemes = [
      'com.fatties.youth',  // Production scheme
      'exp+cbh-youth-online-mobile',  // Expo local development scheme
    ];

    // Try to determine scheme from state or use default
    $scheme = $schemes[0];  // Default to production scheme
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
    $deepLink = "{$scheme}:oauth" . ($queryString ? "?{$queryString}" : '');

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
