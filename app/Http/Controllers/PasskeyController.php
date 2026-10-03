<?php

namespace App\Http\Controllers;

use App\Models\Passkey;
use App\Services\TwoFactorService;
use App\Services\WebAuthnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Lets the signed-in user manage the passkeys they can log in with. The
 * login itself (no password, no two-factor step) lives in AuthController.
 */
class PasskeyController extends Controller
{
  // Plenty for phones, laptops and a security key or two.
  private const MAX_PASSKEYS = 10;

  /**
   * The account's passkeys.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    return response()->json($this->listPayload($request));
  }

  /**
   * Start adding a passkey: options for navigator.credentials.create().
   * Needs the account password - a passkey is a permanent way in, so a
   * stolen session must not be able to add one.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function options(Request $request)
  {
    $user = $request->user();

    // Accounts created through Google/Facebook/Apple have a random password
    // the user never saw, so they can't be asked for it.
    if ($user->provider === null && TwoFactorService::tooManyFailures($user)) {
      return response()->json([
        'message' => 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau '
          . TwoFactorService::failureLockMinutes($user) . ' phút.',
      ], 429);
    }

    if ($user->provider === null && !Hash::check((string) $request->input('password'), $user->password)) {
      // Counted, so a stolen session can't guess the password at full speed.
      TwoFactorService::hitFailure($user);

      return response()->json([
        'message' => 'Mật khẩu không chính xác.',
        'errors' => [
          'password' => 'Mật khẩu không chính xác.',
        ],
      ], 422);
    }

    if (Passkey::where('user_id', $user->id)->count() >= self::MAX_PASSKEYS) {
      return response()->json([
        'message' => 'Bạn đã đạt số passkey tối đa. Hãy xóa bớt trước khi thêm mới.',
      ], 422);
    }

    $user->loadMissing('profile');

    return response()->json(['publicKey' => WebAuthnService::registrationOptions($user)]);
  }

  /**
   * Finish adding a passkey with what navigator.credentials.create() returned.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $request->validate([
      'credential' => 'required|array',
      'credential.id' => 'required|string|max:2000',
      'credential.response.clientDataJSON' => 'required|string',
      'credential.response.attestationObject' => 'required|string',
      'name' => 'nullable|string|max:100',
    ]);

    $passkey = WebAuthnService::register(
      $request->user(),
      $request->input('credential'),
      $request->input('name') ?: $this->defaultName($request)
    );

    if (!$passkey) {
      return response()->json([
        'message' => 'Không thể tạo passkey. Vui lòng thử lại.',
      ], 422);
    }

    return response()->json(['message' => 'Đã thêm passkey.'] + $this->listPayload($request), 201);
  }

  /**
   * Remove a passkey; it can no longer be used to log in.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy(Request $request, $id)
  {
    $deleted = Passkey::where('user_id', $request->user()->id)->where('id', $id)->delete();

    if (!$deleted) {
      return response()->json(['message' => 'Không tìm thấy passkey này.'], 404);
    }

    return response()->json(['message' => 'Đã xóa passkey.'] + $this->listPayload($request));
  }

  private function listPayload(Request $request): array
  {
    $user = $request->user();

    return [
      'passkeys' => Passkey::where('user_id', $user->id)
        ->orderByDesc('id')
        ->get(['id', 'name', 'last_used_at', 'created_at']),
      'password_required' => $user->provider === null,
    ];
  }

  /**
   * "Chrome 129 · Windows" from the device headers the clients send, so the
   * user can tell their passkeys apart without naming them.
   */
  private function defaultName(Request $request): ?string
  {
    $details = \App\Services\DeviceSessionService::detailsFromRequest($request);
    $name = implode(' · ', array_filter([$details['device_model'] ?? null, $details['device_name'] ?? null]));

    return $name !== '' ? $name : null;
  }
}
