<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AuthAccount;
use App\Services\CustomFrameService;
use App\Services\ProfileThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * A member's own image as their avatar frame / profile frame (Pro Plus).
 * The rules an image has to meet are in CustomFrameService.
 */
class CustomFrameController extends Controller
{
  /**
   * Upload (or replace) the caller's frame of one kind. The image is stored
   * at once; it is shown once the theme's avatar_frame / profile_frame is
   * saved as "custom".
   */
  public function store(Request $request, $username)
  {
    $user = Auth::user();
    if ($user->username !== $username) {
      return response()->json(['message' => 'Bạn không có quyền thay đổi khung của người khác.'], 403);
    }

    $request->validate([
      'kind' => ['required', Rule::in(array_keys(CustomFrameService::KINDS))],
      'image' => 'required|file',
    ]);

    if (!ProfileThemeService::canUseCustomFrames($user)) {
      $points = ProfileThemeService::customFramePoints();

      return response()->json([
        'message' => "Khung tùy chỉnh cần đạt {$points} điểm.",
        'required_points' => $points,
      ], 403);
    }

    $user->load('profile');
    if (!$user->profile) {
      return response()->json(['message' => 'Trang cá nhân người dùng không tồn tại.'], 404);
    }

    $kind = $request->input('kind');
    $result = CustomFrameService::store($user, $kind, $request->file('image'));
    if (!$result['ok']) {
      return response()->json(['message' => $result['message'], 'errors' => ['image' => [$result['message']]]], 422);
    }

    return response()->json([
      'message' => 'Đã tải khung lên.',
      'kind' => $kind,
      'url' => $result['url'],
    ]);
  }

  /**
   * Delete the caller's frame of one kind. Allowed below the tier too: it
   * only takes something away.
   */
  public function destroy($username, $kind)
  {
    $user = Auth::user();
    if ($user->username !== $username) {
      return response()->json(['message' => 'Bạn không có quyền thay đổi khung của người khác.'], 403);
    }

    $user->load('profile');
    CustomFrameService::remove($user, $kind);

    return response()->json(['message' => 'Đã xóa khung.', 'kind' => $kind]);
  }

  /**
   * Admin: take down a member's frame (an image that breaks the rules).
   */
  public function adminDestroy($id, $kind)
  {
    $user = AuthAccount::with('profile')->findOrFail($id);

    if (!CustomFrameService::remove($user, $kind)) {
      return response()->json(['message' => 'Tài khoản này không có khung tùy chỉnh loại đó.'], 404);
    }

    AuditLog::record('ADMIN_REMOVE_CUSTOM_FRAME', $user->id, [
      'target_type' => 'account',
      'target_id' => $user->id,
      'old' => ['kind' => $kind],
    ]);

    return response()->json(['message' => "Đã gỡ khung tùy chỉnh của @{$user->username}."]);
  }
}
