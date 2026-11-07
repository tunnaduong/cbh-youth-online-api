<?php

namespace App\Http\Controllers;

use App\Events\CallAnswered;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallRejected;
use App\Events\CallSignaled;
use App\Events\MessageSent;
use App\Models\AuthAccount;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ProfileThemeService;
use App\Services\PushNotificationService;
use App\Support\UserBlocks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CallController extends Controller
{
  /**
   * Return default and environment-configured WebRTC STUN/TURN servers.
   */
  public function iceServers()
  {
    return response()->json([
      'ice_servers' => $this->getIceServersList(),
    ]);
  }

  /**
   * Get the current active or ringing call for the authenticated user, if any.
   */
  public function activeCall(Request $request)
  {
    $user = Auth::user();

    $call = Call::where(function ($q) use ($user) {
        $q->where('caller_id', $user->id)
          ->orWhere('receiver_id', $user->id)
          ->orWhereHas('conversation.participants', function ($pq) use ($user) {
            $pq->where('auth_account.id', $user->id);
          });
      })
      ->whereIn('status', ['ringing', 'accepted'])
      ->where('created_at', '>=', now()->subMinutes(2))
      ->latest()
      ->first();

    if (!$call) {
      return response()->json(['call' => null]);
    }

    $caller = AuthAccount::with('profile')->find($call->caller_id);
    $receiver = $call->receiver_id ? AuthAccount::with('profile')->find($call->receiver_id) : null;

    return response()->json([
      'call' => $this->formatCallPayload($call, $caller, $receiver),
      'ice_servers' => $this->getIceServersList(),
    ]);
  }

  /**
   * Initiate a new voice or video call in a conversation.
   */
  public function initiate(Request $request, $conversationId)
  {
    $user = Auth::user();

    $conversation = Conversation::with(['participants.profile'])
      ->find($conversationId);

    if (!$conversation || !$conversation->hasParticipant($user->id)) {
      return response()->json([
        'message' => 'Bạn không có quyền tham gia cuộc trò chuyện này.',
      ], 403);
    }

    // Direct (1-on-1) call checks
    $receiver = null;
    if ($conversation->type !== 'group') {
      $receiver = $conversation->participants->firstWhere('id', '!=', $user->id);

      if (!$receiver) {
        return response()->json([
          'message' => 'Không tìm thấy người nhận cuộc gọi.',
        ], 404);
      }

      // Check block status both ways
      if (UserBlocks::isBlockedEitherWay((int) $user->id, (int) $receiver->id)) {
        return response()->json([
          'message' => 'Không thể thực hiện cuộc gọi với người dùng này.',
        ], 403);
      }
    }

    // Check if an ongoing or ringing call is already active
    $activeCall = Call::where('conversation_id', $conversation->id)
      ->whereIn('status', ['ringing', 'accepted'])
      ->where('created_at', '>=', now()->subMinutes(3))
      ->first();

    if ($activeCall) {
      if ($activeCall->status === 'ringing' && ((int) $activeCall->caller_id === (int) $user->id || $activeCall->created_at < now()->subMinutes(1))) {
        $activeCall->status = 'cancelled';
        $activeCall->end_reason = 'cancelled';
        $activeCall->ended_at = now();
        $activeCall->save();
      } else {
        return response()->json([
          'message' => 'Đang có một cuộc gọi khác diễn ra trong cuộc trò chuyện này.',
          'call' => $this->formatCallPayload($activeCall, $user, $receiver),
        ], 409);
      }
    }

    $type = $request->input('type', 'audio') === 'video' ? 'video' : 'audio';

    $call = Call::create([
      'conversation_id' => $conversation->id,
      'caller_id' => $user->id,
      'receiver_id' => $receiver?->id,
      'type' => $type,
      'status' => 'ringing',
      'started_at' => now(),
    ]);

    $callPayload = $this->formatCallPayload($call, $user, $receiver);

    // Broadcast CallInitiated immediately to conversation and recipient's user channel
    broadcast(new CallInitiated($conversation->id, $callPayload, $receiver?->id));

    // Dispatch high-priority push notifications to offline / background participants
    try {
      if ($conversation->type === 'group') {
        foreach ($conversation->participants as $participant) {
          if ((int) $participant->id !== (int) $user->id) {
            PushNotificationService::sendExpoCallPushNotification($participant->id, $callPayload, $conversation);
          }
        }
      } elseif ($receiver) {
        PushNotificationService::sendExpoCallPushNotification($receiver->id, $callPayload, $conversation);
      }
    } catch (\Throwable $e) {
      \Illuminate\Support\Facades\Log::warning('Failed sending call push notification: ' . $e->getMessage());
    }

    return response()->json([
      'call' => $callPayload,
      'ice_servers' => $this->getIceServersList(),
    ], 201);
  }

  /**
   * Answer an incoming call.
   */
  public function answer(Request $request, $id)
  {
    $user = Auth::user();

    $call = Call::with(['conversation.participants.profile', 'caller.profile'])
      ->find($id);

    if (!$call) {
      return response()->json(['message' => 'Không tìm thấy cuộc gọi.'], 404);
    }

    if (!$call->conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
    }

    if ($call->status !== 'ringing') {
      return response()->json([
        'message' => 'Cuộc gọi không còn ở trạng thái chờ kết nối.',
        'status' => $call->status,
      ], 400);
    }

    if ((int) $call->caller_id === (int) $user->id) {
      return response()->json(['message' => 'Người gọi không thể tự trả lời cuộc gọi của mình.'], 400);
    }

    $call->status = 'accepted';
    $call->accepted_at = now();
    $call->save();

    $callPayload = $this->formatCallPayload($call, $call->caller, $user);

    broadcast(new CallAnswered($call->conversation_id, $callPayload));

    return response()->json([
      'call' => $callPayload,
      'ice_servers' => $this->getIceServersList(),
    ]);
  }

  /**
   * Reject or decline an incoming call.
   */
  public function reject(Request $request, $id)
  {
    $user = Auth::user();

    $call = Call::with(['conversation.participants.profile', 'caller.profile'])
      ->find($id);

    if (!$call) {
      return response()->json(['message' => 'Không tìm thấy cuộc gọi.'], 404);
    }

    if (!$call->conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
    }

    if ($call->status !== 'ringing') {
      return response()->json([
        'message' => 'Cuộc gọi đã kết thúc hoặc được phản hồi.',
        'status' => $call->status,
      ], 400);
    }

    $reason = $request->input('reason', 'rejected'); // 'rejected' or 'busy'
    $call->status = $reason === 'busy' ? 'busy' : 'rejected';
    $call->end_reason = $reason;
    $call->ended_at = now();
    $call->save();

    // Broadcast rejection event
    broadcast(new CallRejected($call->conversation_id, $call->id, $reason));

    // Record rejected/missed call message in conversation
    $this->recordCallMessage($call, $reason);

    return response()->json([
      'call' => $this->formatCallPayload($call, $call->caller, $user),
    ]);
  }

  /**
   * End an ongoing call or cancel a ringing call.
   */
  public function end(Request $request, $id)
  {
    $user = Auth::user();

    $call = Call::with(['conversation.participants.profile', 'caller.profile', 'receiver.profile'])
      ->find($id);

    if (!$call) {
      return response()->json(['message' => 'Không tìm thấy cuộc gọi.'], 404);
    }

    if (!$call->conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
    }

    if (in_array($call->status, ['ended', 'cancelled', 'rejected', 'busy', 'missed'], true)) {
      return response()->json([
        'call' => $this->formatCallPayload($call, $call->caller, $call->receiver),
      ]);
    }

    $endedAt = now();
    $duration = 0;
    $reason = 'completed';

    if ($call->status === 'ringing') {
      // Caller hung up before answer or timeout
      $isCaller = (int) $user->id === (int) $call->caller_id;
      $call->status = $isCaller ? 'cancelled' : 'missed';
      $reason = $isCaller ? 'cancelled' : 'missed';
    } elseif ($call->status === 'accepted') {
      $call->status = 'ended';
      $reason = 'completed';
      if ($call->accepted_at) {
        $duration = max(0, $endedAt->diffInSeconds($call->accepted_at));
      }
    }

    $call->ended_at = $endedAt;
    $call->duration = $duration;
    $call->end_reason = $reason;
    $call->save();

    $callPayload = $this->formatCallPayload($call, $call->caller, $call->receiver);

    broadcast(new CallEnded($call->conversation_id, $callPayload));

    // Create call summary message in conversation
    $this->recordCallMessage($call, $reason);

    return response()->json([
      'call' => $callPayload,
    ]);
  }

  /**
   * Relay WebRTC signaling (Offer, Answer, ICE Candidate) to peer(s).
   */
  public function signal(Request $request, $id)
  {
    $user = Auth::user();

    $request->validate([
      'type' => 'required|in:offer,answer,candidate',
      'payload' => 'required',
    ]);

    $call = Call::find($id);
    if (!$call) {
      return response()->json(['message' => 'Không tìm thấy cuộc gọi.'], 404);
    }

    if (!in_array($call->status, ['ringing', 'accepted'], true)) {
      return response()->json(['message' => 'Cuộc gọi không còn hoạt động.'], 400);
    }

    broadcast(new CallSignaled(
      $call->conversation_id,
      $call->id,
      $user->id,
      $request->input('type'),
      $request->input('payload')
    ))->toOthers();

    return response()->json(['success' => true]);
  }

  /**
   * Helper to insert a call message into the conversation timeline.
   */
  protected function recordCallMessage(Call $call, string $reason)
  {
    $isVideo = $call->type === 'video';
    $content = '';

    switch ($reason) {
      case 'completed':
        $durationStr = $this->formatDuration((int) $call->duration);
        $content = $isVideo
          ? "Cuộc gọi video đã kết thúc ({$durationStr})"
          : "Cuộc gọi thoại đã kết thúc ({$durationStr})";
        break;
      case 'rejected':
      case 'declined':
        $content = $isVideo
          ? "Cuộc gọi video bị từ chối"
          : "Cuộc gọi thoại bị từ chối";
        break;
      case 'busy':
        $content = $isVideo
          ? "Máy bận (Cuộc gọi video)"
          : "Máy bận (Cuộc gọi thoại)";
        break;
      case 'cancelled':
        $content = $isVideo
          ? "Cuộc gọi video bị hủy"
          : "Cuộc gọi thoại bị hủy";
        break;
      case 'missed':
      default:
        $content = $isVideo
          ? "Cuộc gọi video nhỡ"
          : "Cuộc gọi thoại nhỡ";
        break;
    }

    $metadata = [
      'call_id' => $call->id,
      'call_type' => $call->type,
      'status' => $call->status,
      'duration' => $call->duration ?? 0,
      'end_reason' => $reason,
      'started_at' => $call->started_at?->toIso8601String(),
      'ended_at' => $call->ended_at?->toIso8601String(),
    ];

    $message = Message::create([
      'conversation_id' => $call->conversation_id,
      'user_id' => $call->caller_id,
      'content' => $content,
      'type' => 'call',
      'metadata' => $metadata,
    ]);

    $call->conversation->touch();

    $caller = $call->caller;
    $messageData = [
      'id' => $message->id,
      'content' => $message->content,
      'type' => 'call',
      'file_url' => null,
      'is_edited' => false,
      'is_forwarded' => false,
      'is_myself' => false,
      'sender' => [
        'id' => $caller?->id,
        'username' => $caller?->username ?? 'system',
        'profile_name' => $caller?->profile?->profile_name ?? $caller?->username ?? 'Người dùng',
        'avatar_url' => $caller?->avatarUrl(),
        'is_ai' => false,
        'profile_theme' => $caller ? ProfileThemeService::forAuthor($caller) : null,
      ],
      'created_at' => $message->created_at?->toIso8601String(),
      'created_at_human' => $message->created_at?->diffForHumans(),
      'read_at' => null,
      'metadata' => $metadata,
      'reply_to' => null,
      'reactions' => ['summary' => [], 'total' => 0, 'my_reactions' => []],
    ];

    broadcast(new MessageSent($call->conversation_id, $messageData));
  }

  /**
   * Helper to format duration in MM:SS or HH:MM:SS.
   */
  protected function formatDuration(int $seconds): string
  {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
      return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%02d:%02d', $minutes, $secs);
  }

  /**
   * Helper to build clean, consistent call payload.
   */
  protected function formatCallPayload(Call $call, ?AuthAccount $caller = null, ?AuthAccount $receiver = null): array
  {
    $caller = $caller ?: $call->caller;
    $receiver = $receiver ?: $call->receiver;

    return [
      'id' => $call->id,
      'conversation_id' => (int) $call->conversation_id,
      'type' => $call->type,
      'status' => $call->status,
      'started_at' => $call->started_at?->toIso8601String(),
      'accepted_at' => $call->accepted_at?->toIso8601String(),
      'ended_at' => $call->ended_at?->toIso8601String(),
      'duration' => $call->duration,
      'end_reason' => $call->end_reason,
      'caller' => $caller ? [
        'id' => $caller->id,
        'username' => $caller->username,
        'profile_name' => $caller->profile?->profile_name ?? $caller->username,
        'avatar_url' => $caller->avatarUrl(),
        'profile_theme' => ProfileThemeService::forAuthor($caller),
      ] : null,
      'receiver' => $receiver ? [
        'id' => $receiver->id,
        'username' => $receiver->username,
        'profile_name' => $receiver->profile?->profile_name ?? $receiver->username,
        'avatar_url' => $receiver->avatarUrl(),
        'profile_theme' => ProfileThemeService::forAuthor($receiver),
      ] : null,
    ];
  }

  /**
   * Returns list of configured STUN/TURN servers.
   */
  protected function getIceServersList(): array
  {
    $servers = [
      ['urls' => 'stun:stun.l.google.com:19302'],
      ['urls' => 'stun:stun1.l.google.com:19302'],
      ['urls' => 'stun:stun2.l.google.com:19302'],
    ];

    $customStun = env('WEBRTC_STUN_SERVERS');
    if ($customStun) {
      $urls = array_filter(array_map('trim', explode(',', $customStun)));
      if (!empty($urls)) {
        $servers[] = ['urls' => $urls];
      }
    }

    $turnUrl = env('WEBRTC_TURN_SERVER');
    $turnUser = env('WEBRTC_TURN_USERNAME');
    $turnPass = env('WEBRTC_TURN_PASSWORD');
    if ($turnUrl) {
      $turnConfig = ['urls' => $turnUrl];
      if ($turnUser) $turnConfig['username'] = $turnUser;
      if ($turnPass) $turnConfig['credential'] = $turnPass;
      $servers[] = $turnConfig;
    }

    return $servers;
  }
}
