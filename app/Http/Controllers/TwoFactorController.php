<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\TwoFactorTrustedDevice;
use App\Services\TotpService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Lets the signed-in user manage two-factor authentication on their account.
 * The login side (challenge + code check) lives in AuthController.
 */
class TwoFactorController extends Controller
{
  /**
   * Current two-factor state of the account.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function status(Request $request)
  {
    return response()->json($this->statusPayload($request->user()));
  }

  /**
   * Start setting up an authenticator app: generates the secret to add to
   * the app. Nothing is enforced until confirm() is called with a code.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function setupTotp(Request $request)
  {
    $user = $request->user();

    if ($response = $this->guardSetup($request, $user)) {
      return $response;
    }

    $secret = TotpService::generateSecret();

    $user->two_factor_method = TwoFactorService::METHOD_TOTP;
    $user->two_factor_secret = $secret;
    $user->two_factor_recovery_codes = null;
    $user->two_factor_confirmed_at = null;
    $user->two_factor_last_step = null;
    $user->save();

    return response()->json([
      'method' => TwoFactorService::METHOD_TOTP,
      'secret' => $secret,
      'otpauth_url' => TotpService::provisioningUri($secret, $user->email ?: $user->username, TwoFactorService::ISSUER),
    ]);
  }

  /**
   * Start setting up email codes: sends a code to the account email, which
   * confirm() then checks.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function setupEmail(Request $request)
  {
    $user = $request->user();

    if ($response = $this->guardSetup($request, $user)) {
      return $response;
    }

    if (!$user->email || !$user->email_verified_at) {
      return response()->json([
        'message' => 'Bạn cần xác minh địa chỉ email trước khi dùng mã xác thực qua email.',
      ], 422);
    }

    if ($response = $this->guardEmailCooldown($user)) {
      return $response;
    }

    $user->two_factor_method = TwoFactorService::METHOD_EMAIL;
    $user->two_factor_secret = null;
    $user->two_factor_recovery_codes = null;
    $user->two_factor_confirmed_at = null;
    $user->two_factor_last_step = null;
    $user->save();

    TwoFactorService::sendEmailCode($user, 'manage');

    return response()->json([
      'method' => TwoFactorService::METHOD_EMAIL,
      'email' => TwoFactorService::maskEmail($user->email),
    ]);
  }

  /**
   * Finish setup by proving the chosen method works. Turns two-factor on and
   * returns the recovery codes (shown once).
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function confirm(Request $request)
  {
    $request->validate([
      'code' => 'required|string|max:20',
    ]);

    $user = $request->user();

    if ($user->hasTwoFactorEnabled()) {
      return response()->json(['message' => 'Xác thực hai lớp đã được bật.'], 409);
    }

    if (!$user->two_factor_method) {
      return response()->json(['message' => 'Hãy bắt đầu thiết lập xác thực hai lớp trước.'], 422);
    }

    if ($response = $this->guardFailures($user)) {
      return $response;
    }

    if (!TwoFactorService::verifyCode($user, $request->input('code'), 'manage')) {
      TwoFactorService::hitFailure($user);

      return $this->invalidCodeResponse();
    }

    TwoFactorService::clearFailures($user);

    $user->two_factor_confirmed_at = now();
    $user->save();

    $recoveryCodes = TwoFactorService::generateRecoveryCodes($user);

    return response()->json([
      'message' => 'Đã bật xác thực hai lớp.',
      'recovery_codes' => $recoveryCodes,
      'status' => $this->statusPayload($user),
    ]);
  }

  /**
   * Email a code to confirm a settings change (or resend the setup code) for
   * accounts using the email method.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function sendEmailCode(Request $request)
  {
    $user = $request->user();

    if ($user->two_factor_method !== TwoFactorService::METHOD_EMAIL) {
      return response()->json(['message' => 'Tài khoản này không dùng mã xác thực qua email.'], 422);
    }

    if ($response = $this->guardEmailCooldown($user)) {
      return $response;
    }

    TwoFactorService::sendEmailCode($user, 'manage');

    return response()->json([
      'message' => 'Đã gửi mã xác thực.',
      'email' => TwoFactorService::maskEmail($user->email),
    ]);
  }

  /**
   * Turn two-factor off (or abandon a setup that was never confirmed).
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function disable(Request $request)
  {
    $user = $request->user();

    if ($user->hasTwoFactorEnabled() && ($response = $this->confirmIdentity($request, $user))) {
      return $response;
    }

    TwoFactorService::disable($user);

    return response()->json([
      'message' => 'Đã tắt xác thực hai lớp.',
      'status' => $this->statusPayload($user),
    ]);
  }

  /**
   * Replace the recovery codes with a new set (the old ones stop working).
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function regenerateRecoveryCodes(Request $request)
  {
    $user = $request->user();

    if (!$user->hasTwoFactorEnabled()) {
      return response()->json(['message' => 'Xác thực hai lớp chưa được bật.'], 422);
    }

    if ($response = $this->confirmIdentity($request, $user)) {
      return $response;
    }

    $recoveryCodes = TwoFactorService::generateRecoveryCodes($user);

    return response()->json([
      'message' => 'Đã tạo mã khôi phục mới.',
      'recovery_codes' => $recoveryCodes,
      'status' => $this->statusPayload($user),
    ]);
  }

  /**
   * Forget every remembered device, so each one is challenged again.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function forgetTrustedDevices(Request $request)
  {
    $user = $request->user();

    TwoFactorService::forgetTrustedDevices($user->id);

    return response()->json([
      'message' => 'Đã xóa các thiết bị tin cậy.',
      'status' => $this->statusPayload($user),
    ]);
  }

  private function statusPayload(AuthAccount $user): array
  {
    $enabled = $user->hasTwoFactorEnabled();

    return [
      'enabled' => $enabled,
      'method' => $enabled ? $user->two_factor_method : null,
      'confirmed_at' => $enabled ? $user->two_factor_confirmed_at : null,
      'recovery_codes_remaining' => $enabled ? count($user->two_factor_recovery_codes ?? []) : 0,
      'trusted_devices' => TwoFactorTrustedDevice::where('user_id', $user->id)
        ->where('expires_at', '>', now())
        ->count(),
      'email' => TwoFactorService::maskEmail($user->email),
      'email_verified' => (bool) $user->email_verified_at,
      'password_required' => $this->passwordRequired($user),
    ];
  }

  /**
   * Accounts created through Google/Facebook/Apple have a random password
   * the user never saw, so they can't be asked for it.
   */
  private function passwordRequired(AuthAccount $user): bool
  {
    return $user->provider === null;
  }

  /**
   * Setup can't start while two-factor is already on, and needs the account
   * password - otherwise a stolen session could switch it on and lock the
   * real owner out.
   */
  private function guardSetup(Request $request, AuthAccount $user)
  {
    if ($user->hasTwoFactorEnabled()) {
      return response()->json([
        'message' => 'Xác thực hai lớp đang bật. Hãy tắt trước khi thiết lập lại.',
      ], 409);
    }

    if ($this->passwordRequired($user) && !Hash::check((string) $request->input('password'), $user->password)) {
      return response()->json([
        'message' => 'Mật khẩu không chính xác.',
        'errors' => [
          'password' => 'Mật khẩu không chính xác.',
        ],
      ], 422);
    }

    return null;
  }

  /**
   * Changing settings while two-factor is on needs either the password or a
   * current code (from the app/email, or a recovery code).
   */
  private function confirmIdentity(Request $request, AuthAccount $user)
  {
    $password = (string) $request->input('password');
    $code = (string) $request->input('code');

    if ($password === '' && $code === '') {
      return response()->json([
        'message' => 'Vui lòng nhập mã xác thực hoặc mật khẩu.',
        'errors' => [
          'code' => 'Vui lòng nhập mã xác thực hoặc mật khẩu.',
        ],
      ], 422);
    }

    if ($response = $this->guardFailures($user)) {
      return $response;
    }

    $confirmed = $password !== ''
      ? Hash::check($password, $user->password)
      : TwoFactorService::verifyAny($user, $code, 'manage') !== null;

    if (!$confirmed) {
      TwoFactorService::hitFailure($user);

      return $password !== ''
        ? response()->json([
          'message' => 'Mật khẩu không chính xác.',
          'errors' => [
            'password' => 'Mật khẩu không chính xác.',
          ],
        ], 422)
        : $this->invalidCodeResponse();
    }

    TwoFactorService::clearFailures($user);

    return null;
  }

  private function guardFailures(AuthAccount $user)
  {
    if (!TwoFactorService::tooManyFailures($user)) {
      return null;
    }

    return response()->json([
      'message' => 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau '
        . TwoFactorService::failureLockMinutes($user) . ' phút.',
    ], 429);
  }

  private function guardEmailCooldown(AuthAccount $user)
  {
    $wait = TwoFactorService::emailCooldown($user, 'manage');
    if ($wait <= 0) {
      return null;
    }

    return response()->json([
      'message' => "Vui lòng đợi {$wait} giây trước khi gửi lại mã.",
      'retry_after' => $wait,
    ], 429);
  }

  private function invalidCodeResponse()
  {
    return response()->json([
      'message' => 'Mã xác thực không đúng hoặc đã hết hạn.',
      'errors' => [
        'code' => 'Mã xác thực không đúng hoặc đã hết hạn.',
      ],
    ], 422);
  }
}
