<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\Topic;
use App\Services\NotificationService;
use App\Services\PointsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PointsController extends Controller
{
  /**
   * Get top users using points
   *
   * @param int $limit
   * @return JsonResponse
   */
  public function getTopUsers(Request $request): JsonResponse
  {
    try {
      $limit = $request->query('limit', 8);

      $topUsers = AuthAccount::with(['profile'])
        ->where('role', '!=', 'admin')
        ->orderByDesc('points')
        ->limit($limit)
        ->get();

      $formattedUsers = $topUsers->map(function ($user) {
        return [
          'uid' => $user->id,
          'username' => $user->username,
          'profile_name' => $user->profile->profile_name ?? $user->username,
          'profile_picture' => $user->profile->profile_picture ?? null,
          'oauth_profile_picture' => $user->profile->oauth_profile_picture ?? null,
          'avatar_url' => $user->avatarUrl(),
          'total_points' => $user->getPoints()
        ];
      });

      return response()->json($formattedUsers);
    } catch (\Exception $e) {
      Log::error('Failed to get top users: ' . $e->getMessage());

      return response()->json([
        'status' => 'error',
        'message' => 'Failed to fetch top users: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Gift points to another member.
   *
   * The recipient is given either as the author of a topic (`topic_id`) -
   * which keeps an anonymous author anonymous to the sender - or directly by
   * `username`. The sender needs the 'gift_points_to_others' tier privilege
   * (Thành viên tích cực, 150+ points) and enough balance.
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function gift(Request $request): JsonResponse
  {
    $request->validate([
      'amount' => 'required|integer|min:' . PointsService::GIFT_MIN_AMOUNT . '|max:' . PointsService::GIFT_MAX_AMOUNT,
      'topic_id' => 'required_without:username|nullable|integer',
      'username' => 'required_without:topic_id|nullable|string|max:255',
      'message' => 'nullable|string|max:200',
    ]);

    $sender = Auth::user();
    $amount = (int) $request->amount;
    $message = trim((string) $request->input('message', ''));

    $topic = null;
    if ($request->filled('topic_id')) {
      $topic = Topic::with('user')->find($request->topic_id);
      if (!$topic || !$topic->user) {
        return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
      }
      $recipient = $topic->user;
    } else {
      $recipient = AuthAccount::where('username', $request->username)->first();
      if (!$recipient) {
        return response()->json(['message' => 'Không tìm thấy người nhận.'], 404);
      }
    }

    if ($recipient->id === $sender->id) {
      return response()->json(['message' => 'Bạn không thể tự tặng điểm cho chính mình.'], 422);
    }
    if (!PointsService::canGiftPoints($sender)) {
      return response()->json([
        'message' => 'Bạn cần đạt hạng Thành viên tích cực (150 điểm) để tặng điểm cho người khác.',
        'code' => 'gift_privilege_required',
      ], 403);
    }

    $senderName = $sender->profile->profile_name ?? $sender->username;
    $topicTitle = $topic ? mb_strimwidth((string) $topic->title, 0, 60, '…') : null;
    $isAnonymousTopic = $topic && (bool) $topic->anonymous;

    // Never leak an anonymous author's identity through the sender's own
    // wallet history.
    $senderDescription = $topic
      ? ($isAnonymousTopic
        ? "Tặng điểm cho tác giả bài viết \"{$topicTitle}\""
        : "Tặng điểm cho @{$recipient->username} (bài viết \"{$topicTitle}\")")
      : "Tặng điểm cho @{$recipient->username}";
    $recipientDescription = $topic
      ? "{$senderName} tặng điểm cho bài viết \"{$topicTitle}\""
      : "{$senderName} tặng điểm cho bạn";
    if ($message !== '') {
      $senderDescription .= ": {$message}";
      $recipientDescription .= ": {$message}";
    }

    try {
      $result = PointsService::giftPoints(
        $sender->id,
        $recipient->id,
        $amount,
        $senderDescription,
        $recipientDescription,
        $topic?->id
      );
    } catch (\InvalidArgumentException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    } catch (\Throwable $e) {
      Log::error('Gift points failed: ' . $e->getMessage(), [
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'amount' => $amount,
      ]);
      return response()->json(['message' => 'Không thể tặng điểm lúc này. Vui lòng thử lại sau.'], 500);
    }

    try {
      NotificationService::createPointsGiftedNotification($recipient->id, $sender->id, $amount, $message, $topic);
    } catch (\Throwable $e) {
      Log::error('Gift points notification failed: ' . $e->getMessage());
    }

    return response()->json([
      'message' => "Đã tặng " . number_format($amount) . " điểm.",
      'amount' => $amount,
      'remaining_points' => $result['sender_points'],
      'recipient' => $isAnonymousTopic
        ? ['anonymous' => true]
        : [
          'id' => $recipient->id,
          'username' => $recipient->username,
          'profile_name' => $recipient->profile->profile_name ?? $recipient->username,
        ],
    ]);
  }
}
