<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\TwoFactorTrustedDevice;
use App\Services\TotpService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Lets the signed-in user manage two-factor authentication on their account.
 * The methods (authenticator app, emailed code) are independent: any of them
 * can be on at the same time, and the user picks one when logging in.
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
   * the app. The method only counts once confirm() is called with a code.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function setupTotp(Request $request)
  {
    $user = $request->user();

    if ($response = $this->guardSetup($request, $user, TwoFactorService::METHOD_TOTP)) {
      return $response;
    }

    $secret = TotpService::generateSecret();

    $user->two_factor_secret = $secret;
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

    if ($response = $this->guardSetup($request, $user, TwoFactorService::METHOD_EMAIL)) {
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

    TwoFactorService::startEmailSetup($user);
    TwoFactorService::sendEmailCode($user, 'manage');

    return response()->json([
      'method' => TwoFactorService::METHOD_EMAIL,
      'email' => TwoFactorService::maskEmail($user->email),
    ]);
  }

  /**
   * Finish setting a method up by proving it works. The first method to be
   * confirmed turns two-factor on and returns the recovery codes (shown
   * once); adding another method keeps the existing codes.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function confirm(Request $request)
  {
    $request->validate([
      'code' => 'required|string|max:20',
      'method' => ['nullable', Rule::in([TwoFactorService::METHOD_TOTP, TwoFactorService::METHOD_EMAIL])],
    ]);

    $user = $request->user();
    $totpPending = $this->totpPending($user);

    // Older clients don't say which method they are confirming: it is
    // whichever setup is in progress.
    $method = $request->input('method')
      ?: ($totpPending ? TwoFactorService::METHOD_TOTP : TwoFactorService::METHOD_EMAIL);

    if (TwoFactorService::isMethodEnabled($user, $method)) {
      return response()->json(['message' => 'Phương thức này đã được bật.'], 409);
    }

    $pending = $method === TwoFactorService::METHOD_TOTP
      ? $totpPending
      : TwoFactorService::hasEmailSetupPending($user);

    if (!$pending) {
      return response()->json(['message' => 'Hãy bắt đầu thiết lập phương thức này trước.'], 422);
    }

    if ($response = $this->guardFailures($user)) {
      return $response;
    }

    $code = $request->input('code');
    $valid = $method === TwoFactorService::METHOD_TOTP
      ? TwoFactorService::verifyTotp($user, $code)
      : TwoFactorService::verifyEmailCode($user, preg_replace('/\s+/', '', $code), 'manage');

    if (!$valid) {
      TwoFactorService::hitFailure($user);

      return $this->invalidCodeResponse();
    }

    TwoFactorService::clearFailures($user);

    $wasEnabled = $user->hasTwoFactorEnabled();
    TwoFactorService::enableMethod($user, $method);

    return response()->json([
      'message' => $method === TwoFactorService::METHOD_TOTP
        ? 'Đã bật xác thực bằng ứng dụng xác thực.'
        : 'Đã bật xác thực bằng mã gửi qua email.',
      'method' => $method,
      // Null when another method was already on: the existing codes stay valid.
      'recovery_codes' => $wasEnabled ? null : TwoFactorService::generateRecoveryCodes($user),
      'status' => $this->statusPayload($user),
    ]);
  }

  /**
   * Email a code to confirm a settings change (email method is on), or
   * re-send the code of an email setup in progress.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function sendEmailCode(Request $request)
  {
    $user = $request->user();

    if (
      !TwoFactorService::isMethodEnabled($user, TwoFactorService::METHOD_EMAIL)
      && !TwoFactorService::hasEmailSetupPending($user)
    ) {
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
   * Turn one method off (`method`), or all of them when none is named.
   * Dropping a setup that was never confirmed needs no confirmation.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function disable(Request $request)
  {
    $request->validate([
      'method' => ['nullable', Rule::in([TwoFactorService::METHOD_TOTP, TwoFactorService::METHOD_EMAIL])],
    ]);

    $user = $request->user();
    $method = $request->input('method');

    // Only something confirmed is protected; an unfinished setup isn't
    // enforced anywhere yet, so cancelling it is free.
    $protected = $method
      ? TwoFactorService::isMethodEnabled($user, $method)
      : $user->hasTwoFactorEnabled();

    if ($protected && ($response = $this->confirmIdentity($request, $user))) {
      return $response;
    }

    if ($method) {
      TwoFactorService::disableMethod($user, $method);
    } else {
      TwoFactorService::disable($user);
    }

    return response()->json([
      'message' => $user->hasTwoFactorEnabled()
        ? 'Đã tắt phương thức xác thực này.'
        : 'Đã tắt xác thực hai lớp.',
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
    $methods = TwoFactorService::enabledMethods($user);
    $enabled = (bool) $methods;

    return [
      'enabled' => $enabled,
      // Every method that is on, and the one a login offers first.
      'methods' => $methods,
      'method' => $methods[0] ?? null,
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
   * An authenticator secret was generated but never confirmed with a code.
   */
  private function totpPending(AuthAccount $user): bool
  {
    return $user->two_factor_secret && $user->two_factor_totp_confirmed_at === null;
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
   * Setting a method up needs the account password - otherwise a stolen
   * session could switch it on and lock the real owner out - and makes no
   * sense for a method that is already on.
   */
  private function guardSetup(Request $request, AuthAccount $user, string $method)
  {
    if (TwoFactorService::isMethodEnabled($user, $method)) {
      return response()->json([
        'message' => 'Phương thức này đang bật. Hãy tắt trước khi thiết lập lại.',
      ], 409);
    }

    if ($this->passwordRequired($user)) {
      // Same limiter as wrong codes: a stolen session must not be able to
      // guess the password here at full speed.
      if ($response = $this->guardFailures($user)) {
        return $response;
      }
      if (Hash::check((string) $request->input('password'), $user->password)) {
        return null;
      }

      TwoFactorService::hitFailure($user);

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
   * current code (from any method that is on, or a recovery code).
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
