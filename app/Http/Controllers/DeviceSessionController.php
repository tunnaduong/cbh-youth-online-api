<?php

namespace App\Http\Controllers;

use App\Events\DeviceSessionsRevoked;
use App\Services\DeviceSessionService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Logged-in devices": lists the account's active logins (one Sanctum token
 * each, described by TrackDeviceSession) and lets the user log them out.
 */
class DeviceSessionController extends Controller
{
  // Tokens never expire, so an old account can have piled up a lot of them.
  private const LIST_LIMIT = 50;

  /**
   * The account's logins, the one making this request first.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    $user = $request->user();
    $currentId = $this->currentTokenId($request);

    $tokens = $user->tokens()
      ->orderByDesc('last_used_at')
      ->orderByDesc('id')
      ->limit(self::LIST_LIMIT)
      ->get();

    $sessions = $tokens
      ->map(fn($token) => [
        'id' => $token->id,
        'platform' => $token->platform,
        'app_version' => $token->app_version,
        'device_name' => $token->device_name,
        'device_model' => $token->device_model,
        // How this login was made: password, google, facebook, apple,
        // passkey, register, app (handed to a browser by the mobile app);
        // null for logins older than this field.
        'login_method' => $token->login_method,
        'login_two_factor' => (bool) $token->login_two_factor,
        'last_used_at' => $token->last_used_at,
        'created_at' => $token->created_at,
        'is_current' => $token->id === $currentId,
      ])
      ->sortByDesc('is_current')
      ->values();

    return response()->json([
      'sessions' => $sessions,
      'total' => $user->tokens()->count(),
    ]);
  }

  /**
   * Log one other device out.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy(Request $request, $id)
  {
    if ((int) $id === $this->currentTokenId($request)) {
      return response()->json([
        'message' => 'Đây là thiết bị bạn đang dùng. Hãy dùng nút Đăng xuất để thoát.',
      ], 422);
    }

    $deleted = $request->user()->tokens()->where('id', $id)->delete();

    if (!$deleted) {
      return response()->json(['message' => 'Không tìm thấy thiết bị này.'], 404);
    }

    $this->announceRevoked($request->user()->id, [(int) $id]);
    // An app login takes the web sessions it handed over with it.
    DeviceSessionService::revokeHandedOver($request->user(), (int) $id);

    return response()->json(['message' => 'Đã đăng xuất thiết bị.']);
  }

  /**
   * Log out every device except the one making this request. Remembered
   * two-factor devices are forgotten too, so logging back in on any of them
   * needs the code again.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroyOthers(Request $request)
  {
    $user = $request->user();
    $currentId = $this->currentTokenId($request);

    $revokedIds = $user->tokens()
      ->when($currentId, fn($query) => $query->where('id', '!=', $currentId))
      ->pluck('id')
      ->all();
    $deleted = $user->tokens()->whereIn('id', $revokedIds)->delete();

    $this->announceRevoked($user->id, $revokedIds);

    TwoFactorService::forgetTrustedDevices($user->id);

    return response()->json([
      'message' => 'Đã đăng xuất khỏi tất cả thiết bị khác.',
      'logged_out' => $deleted,
    ]);
  }

  /**
   * Tells clients still open on the revoked logins to sign out now (see
   * DeviceSessionsRevoked). Best effort: the tokens are already gone, so a
   * client that misses this still signs out on its next request (401).
   */
  private function announceRevoked(int $userId, array $tokenIds): void
  {
    if (empty($tokenIds)) {
      return;
    }

    try {
      broadcast(new DeviceSessionsRevoked($userId, $tokenIds));
    } catch (\Throwable $e) {
      // Reverb being down must not fail the logout itself.
    }
  }

  private function currentTokenId(Request $request): ?int
  {
    $token = $request->user()->currentAccessToken();

    return $token instanceof PersonalAccessToken ? (int) $token->id : null;
  }
}
