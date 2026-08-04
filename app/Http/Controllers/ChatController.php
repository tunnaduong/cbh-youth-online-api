<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Jobs\ProcessImageCompression;
use App\Jobs\ProcessVideoCompression;
use App\Events\MessageReacted;
use App\Events\MessageRead;
use App\Events\MessageRecalled;
use App\Events\MessageSent;
use App\Models\AuthAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Notification;
use App\Models\NotificationSettings;
use App\Models\ConversationBackgroundHistory;
use App\Models\UserBlock;
use App\Models\UserContent;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Handles all chat-related functionalities, including conversations and messages.
 */
class ChatController extends Controller
{
  /**
   * Get all conversations for the authenticated user.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getConversations()
  {
    $user = Auth::user();
    $blockedUserIds = UserBlock::where('user_id', $user->id)->pluck('blocked_user_id')->toArray();

    $conversations = Conversation::whereHas('participants', function ($query) use ($user) {
      $query->where('user_id', $user->id);
    })
      ->orderBy('updated_at', 'desc')
      ->with(['participants.profile', 'latestMessage.user.profile', 'backgroundContent'])
      ->get()
      ->filter(function ($conversation) use ($blockedUserIds, $user) {
        if ($conversation->type === 'private') {
          foreach ($conversation->participants as $participant) {
            if ($participant->id !== $user->id && in_array($participant->id, $blockedUserIds)) {
              return false;
            }
          }
        }
        return true;
      })
      ->values()  // Reset keys after filter
      ->map(function ($conversation) use ($user) {
        // Private chats only ever show "the other person", so participants
        // there deliberately excludes you. Groups need the real member count
        // (including yourself) — otherwise it always reads one member short
        // compared to Group Info, which does include you.
        $displayParticipants = $conversation->type === 'group'
          ? $conversation->participants
          : $conversation->participants->where('id', '!=', $user->id)->values();

        return [
          'id' => $conversation->id,
          'type' => $conversation->type,
          'name' => $conversation->type === 'group' ? $conversation->name : null,
          'avatar_url' => $conversation->type === 'group' && $conversation->avatar_url
            ? $this->absoluteStorageUrl($conversation->avatar_url)
            : null,
          'background_url' => $conversation->is_public
            ? null
            : $this->absoluteStorageUrl($conversation->backgroundContent->file_path ?? null),
          'participants' => $displayParticipants->map(function ($participant) {
            return [
              'id' => $participant->id,
              'username' => $participant->username,
              'profile_name' => $participant->profile->profile_name ?? $participant->username,
              'avatar_url' => config('app.url') . "/v1.0/users/{$participant->username}/avatar",
            ];
          }),
          'latest_message' => $conversation->latestMessage ? [
            'content' => $conversation->latestMessage->content,
            'type' => $conversation->latestMessage->type,
            'sender' => $conversation->latestMessage->user
              ? $conversation->latestMessage->user->username
              : ($conversation->latestMessage->type === 'system' ? 'system' : ($conversation->latestMessage->guest_name ?? 'Ẩn danh')),
            'is_myself' => $conversation->latestMessage->user_id === $user->id,
            'is_recalled' => (bool) $conversation->latestMessage->is_recalled,
            'created_at' => $conversation->latestMessage->created_at ? $conversation->latestMessage->created_at->toISOString() : null,
            'created_at_human' => $conversation->latestMessage->created_at ? $conversation->latestMessage->created_at->diffForHumans() : null,
          ] : null,
          'unread_count' => $conversation->unreadMessagesCount($user->id)
        ];
      });

    // Always include the public chat "Tán gẫu linh tinh" for all users
    $publicChat = Conversation::where('is_public', true)
      ->with(['participants.profile', 'latestMessage.user.profile'])
      ->first();

    if ($publicChat) {
      // Check if public chat is already in the conversations list
      $publicChatExists = $conversations->contains(function ($conv) use ($publicChat) {
        return $conv['id'] === $publicChat->id;
      });

      if (!$publicChatExists) {
        // Get all participants (for public chat, we don't exclude the current user)
        $allParticipants = $publicChat->participants->map(function ($participant) {
          return [
            'id' => $participant->id,
            'username' => $participant->username,
            'profile_name' => $participant->profile->profile_name ?? $participant->username,
            'avatar_url' => config('app.url') . "/v1.0/users/{$participant->username}/avatar",
          ];
        });

        // Add public chat to conversations
        $publicChatData = [
          'id' => $publicChat->id,
          'type' => $publicChat->type,
          'name' => $publicChat->name,
          'avatar_url' => null,
          'participants' => $allParticipants,
          'latest_message' => $publicChat->latestMessage ? [
            'content' => $publicChat->latestMessage->content,
            'type' => $publicChat->latestMessage->type,
            'sender' => $publicChat->latestMessage->user ? $publicChat->latestMessage->user->username : ($publicChat->latestMessage->guest_name ?? 'Ẩn danh'),
            'is_myself' => $publicChat->latestMessage->user_id === $user->id,
            'is_recalled' => (bool) $publicChat->latestMessage->is_recalled,
            'created_at' => $publicChat->latestMessage->created_at ? $publicChat->latestMessage->created_at->toISOString() : null,
            'created_at_human' => $publicChat->latestMessage->created_at ? $publicChat->latestMessage->created_at->diffForHumans() : null,
          ] : null,
          'unread_count' => $publicChat->unreadMessagesCount($user->id)
        ];

        // Prepend public chat to the beginning of the list
        $conversations = $conversations->prepend($publicChatData);
      }
    }

    return response()->json($conversations->values());
  }

  /**
   * Get messages for a specific conversation.
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getMessages($conversationId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    // Allow access to public chat "Tán gẫu linh tinh" even if user is not a participant
    $isPublicChat = $conversation->is_public;

    if (!$isPublicChat && !$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    // For participant-restricted mention resolution (private/group, non-public)
    $participantIds = $isPublicChat ? null : $conversation->participants()->pluck('cyo_auth_accounts.id')->toArray();

    // Get blocked user IDs
    $blockedUserIds = UserBlock::where('user_id', $user->id)->pluck('blocked_user_id')->toArray();

    $perPage = 50;
    $totalMessages = $conversation
      ->messages()
      ->whereNotIn('user_id', $blockedUserIds)
      ->count();
    $lastPage = (int) max(1, ceil($totalMessages / $perPage));
    $page = (int) request()->get('page', 1);
    $page = max(1, min($page, $lastPage));

    // Calculate the offset from the end
    $offset = max(0, $totalMessages - ($page * $perPage));
    $limit = max(0, min($perPage, $totalMessages - (($page - 1) * $perPage)));

    $rawMessages = $conversation
      ->messages()
      ->whereNotIn('user_id', $blockedUserIds)
      ->with(['user.profile', 'reactions.user.profile', 'replyTo.user.profile'])
      ->orderBy('created_at', 'asc')
      ->skip($offset)
      ->take($limit)
      ->get();

    // Batch-resolve @mentions for all messages in one DB query
    $allMentionedUsernames = [];
    foreach ($rawMessages as $msg) {
      if ($msg->content && !$msg->is_recalled) {
        foreach (NotificationService::parseMentions($msg->content) as $un) {
          $allMentionedUsernames[] = $un;
        }
      }
    }
    $allMentionedUsernames = array_unique($allMentionedUsernames);
    $resolvedUsers = [];
    // @all is a reserved virtual mention — always resolve it
    if (in_array('all', $allMentionedUsernames)) {
      $resolvedUsers['all'] = ['username' => 'all', 'user_id' => null];
    }
    $regularMentionedUsernames = array_filter($allMentionedUsernames, fn($u) => $u !== 'all');
    if (!empty($regularMentionedUsernames)) {
      $q = \App\Models\AuthAccount::whereIn('username', $regularMentionedUsernames)->select('id', 'username');
      if ($participantIds !== null) {
        $q->whereIn('id', $participantIds);
      }
      foreach ($q->get() as $u) {
        $resolvedUsers[strtolower($u->username)] = ['username' => $u->username, 'user_id' => $u->id];
      }
    }

    $messages = $rawMessages->map(function ($message) use ($user, $resolvedUsers) {
        $isSystem = $message->type === 'system';
        $isGuest = !$isSystem && ($message->guest_name !== null || $message->user_id === null);
        $isMyself = !$isGuest && !$isSystem && $message->user_id !== null && $message->user_id === $user->id;

        // Determine sender information
        $senderData = [
          'id' => null,
          'username' => 'Ẩn danh',
          'profile_name' => 'Ẩn danh',
          'avatar_url' => null,
        ];

        if ($isSystem) {
          $senderData['username'] = 'system';
          $senderData['profile_name'] = 'Hệ thống';
        } elseif ($isGuest) {
          $senderData['username'] = $message->guest_name ?? 'Ẩn danh';
          $senderData['profile_name'] = $message->guest_name ?? 'Ẩn danh';
        } elseif ($message->user) {
          try {
            $senderData['id'] = $message->user->id;
            $senderData['username'] = $message->user->username ?? 'Ẩn danh';

            // Safely access profile - use null coalescing to avoid errors
            $profile = $message->user->profile ?? null;
            $senderData['profile_name'] = ($profile && isset($profile->profile_name))
              ? $profile->profile_name
              : ($message->user->username ?? 'Ẩn danh');

            if ($message->user->username) {
              $senderData['avatar_url'] = config('app.url') . "/v1.0/users/{$message->user->username}/avatar";
            }
          } catch (\Exception $e) {
            // Fallback if any error occurs accessing user properties
            $senderData['username'] = 'Ẩn danh';
            $senderData['profile_name'] = 'Ẩn danh';
          }
        }

        // Resolve mentions for this message from the pre-fetched map
        $msgMentions = [];
        if (!$message->is_recalled && $message->content) {
          foreach (NotificationService::parseMentions($message->content) as $un) {
            $key = strtolower($un);
            if (isset($resolvedUsers[$key])) {
              $msgMentions[] = $resolvedUsers[$key];
            }
          }
        }

        return [
          'id' => $message->id,
          'content' => $message->is_recalled ? null : $message->content,
          'type' => $message->type,
          'file_url' => ($message->is_recalled || !$message->file_url) ? null : $this->absoluteStorageUrl($message->file_url),
          'is_edited' => $message->is_edited,
          'is_recalled' => (bool) $message->is_recalled,
          'is_forwarded' => (bool) $message->is_forwarded,
          'is_myself' => $isMyself,
          'is_guest' => $isGuest,
          'sender' => $senderData,
          'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
          'created_at_human' => $message->created_at ? $message->created_at->diffForHumans() : null,
          'read_at' => $message->read_at?->toISOString(),
          'metadata' => $message->is_recalled ? null : $message->metadata,
          'reply_to' => $this->formatReplyTo($message->replyTo),
          'reactions' => $this->formatReactions($message, $user->id),
          'mentions' => $msgMentions,
        ];
      });

    // Calculate pagination data
    $hasMorePages = $page < $lastPage;

    $paginationData = [
      'current_page' => $page,
      'data' => $messages->values()->all(),
      'first_page_url' => url("/v1.0/chat/conversations/{$conversationId}/messages?page=1"),
      'from' => $offset + 1,
      'last_page' => $lastPage,
      'last_page_url' => url("/v1.0/chat/conversations/{$conversationId}/messages?page={$lastPage}"),
      'next_page_url' => $hasMorePages ? url("/v1.0/chat/conversations/{$conversationId}/messages?page=" . ($page + 1)) : null,
      'path' => url("/v1.0/chat/conversations/{$conversationId}/messages"),
      'per_page' => $perPage,
      'prev_page_url' => $page > 1 ? url("/v1.0/chat/conversations/{$conversationId}/messages?page=" . ($page - 1)) : null,
      'to' => $offset + $limit,
      'total' => $totalMessages,
    ];

    // Mark messages as read
    $conversation
      ->messages()
      ->where('user_id', '!=', $user->id)
      ->whereNull('read_at')
      ->update(['read_at' => now()]);

    // Update last_read_at for the user
    $conversation
      ->participants()
      ->where('user_id', $user->id)
      ->update(['last_read_at' => now()]);

    return response()->json($paginationData);
  }

  /**
   * Create a new private conversation.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function createPrivateConversation(Request $request)
  {
    $request->validate([
      'participant_id' => 'required|exists:cyo_auth_accounts,id'
    ]);

    $user = Auth::user();
    $conversation = $this->findOrCreatePrivateConversation($user->id, (int) $request->participant_id);

    return response()->json(['conversation_id' => $conversation->id], $conversation->wasRecentlyCreated ? 201 : 200);
  }

  /**
   * Find the existing 1-on-1 private conversation between two users, or create one.
   *
   * @param  int  $userAId
   * @param  int  $userBId
   * @return \App\Models\Conversation
   */
  private function findOrCreatePrivateConversation(int $userAId, int $userBId): Conversation
  {
    $existingConversation = Conversation::whereHas('participants', function ($query) use ($userAId) {
      $query->where('user_id', $userAId);
    })->whereHas('participants', function ($query) use ($userBId) {
      $query->where('user_id', $userBId);
    })->where('type', 'private')->first();

    if ($existingConversation) {
      return $existingConversation;
    }

    $conversation = Conversation::create(['type' => 'private']);
    $conversation->participants()->attach([$userAId, $userBId]);

    return $conversation;
  }

  /**
   * Send a message to a conversation.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function sendMessage(Request $request, $conversationId)
  {
    $type = $request->input('type');

    $fileRules = ['nullable', 'file'];
    if ($type === 'video') {
      $fileRules[] = 'max:102400';  // 100MB max for video
      $fileRules[] = 'mimes:mp4,mov,avi,webm';
    } elseif ($type === 'image') {
      $fileRules[] = 'max:10240';  // 10MB max for image
      $fileRules[] = 'mimes:jpeg,png,jpg,gif,webp';
    } else {
      $fileRules[] = 'max:10240';  // 10MB max for other files
    }

    $request->validate([
      'content' => 'required_without:file|string',
      'type' => 'required|in:text,image,video,file',
      'file' => $fileRules,
      'reply_to_message_id' => 'nullable|integer|exists:cyo_conversation_messages,id',
    ]);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type === 'private') {
      $otherParticipant = $conversation->participants()->where('cyo_auth_accounts.id', '!=', $user->id)->first();
      if ($otherParticipant) {
        $isBlocked = UserBlock::where('user_id', $user->id)->where('blocked_user_id', $otherParticipant->id)->exists();
        if ($isBlocked) {
          return response()->json(['message' => 'Bạn đã chặn người dùng này.'], 403);
        }
      }
    }

    // Allow access to public chat "Tán gẫu linh tinh" even if user is not a participant
    $isPublicChat = $conversation->is_public;

    if (!$isPublicChat && !$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Auto-add user to public chat participants if they're not already a participant
    if ($isPublicChat && !$conversation->hasParticipant($user->id)) {
      $conversation->participants()->attach($user->id, [
        'last_read_at' => now(),
      ]);
    }

    // Validate that reply_to message belongs to the same conversation
    if ($request->reply_to_message_id) {
      $replyTarget = Message::find($request->reply_to_message_id);
      if (!$replyTarget || (int) $replyTarget->conversation_id !== (int) $conversationId) {
        return response()->json(['message' => 'Tin nhắn được trả lời không thuộc cuộc trò chuyện này.'], 422);
      }
    }

    $messageData = [
      'conversation_id' => $conversationId,
      'user_id' => $user->id,
      'content' => $request->content,
      'type' => $request->type,
      'reply_to_message_id' => $request->reply_to_message_id,
    ];

    // Handle file upload
    if ($request->hasFile('file')) {
      $file = $request->file('file');
      $path = $file->store('chat_files', 'public');
      $messageData['file_url'] = $path;

      if ($request->type === 'video') {
        ProcessVideoCompression::dispatch($path);
        $thumbnailUrl = $this->createVideoFirstFrame($path);
        if ($thumbnailUrl) {
          $messageData['metadata'] = ['thumbnail_url' => $thumbnailUrl];
        }
      } elseif ($request->type === 'image') {
        ProcessImageCompression::dispatch($path);
      }
    }

    $message = Message::create($messageData);

    $messageData = $this->finalizeAndBroadcastMessage($conversation, $message, $user);

    return response()->json($messageData, 201);
  }

  /**
   * Shared post-processing for a newly created message, regardless of how it was created
   * (typed by the user, or forwarded from another message): persists the conversation's
   * updated_at timestamp, builds the API/broadcast representation, pushes the real-time
   * event, sends push notifications, and resolves @mentions (including reply and @all
   * notifications). Used by both sendMessage() and forwardMessage().
   *
   * @param  \App\Models\Conversation  $conversation
   * @param  \App\Models\Message  $message
   * @param  \App\Models\AuthAccount  $user  The sender
   * @return array The API-shaped message data (includes 'mentions')
   */
  private function finalizeAndBroadcastMessage(Conversation $conversation, Message $message, AuthAccount $user): array
  {
    // Update conversation's updated_at timestamp
    $conversation->touch();

    // Load relationships for the response
    $message->load('user.profile', 'replyTo.user.profile');

    // Prepare message data for broadcasting
    $senderData = [
      'id' => null,
      'username' => 'Ẩn danh',
      'profile_name' => 'Ẩn danh',
      'avatar_url' => null,
    ];

    if ($message->user) {
      try {
        $senderData['id'] = $message->user->id;
        $senderData['username'] = $message->user->username ?? 'Ẩn danh';

        // Safely access profile - use null coalescing to avoid errors
        $profile = $message->user->profile ?? null;
        $senderData['profile_name'] = ($profile && isset($profile->profile_name))
          ? $profile->profile_name
          : ($message->user->username ?? 'Ẩn danh');

        if ($message->user->username) {
          $senderData['avatar_url'] = config('app.url') . "/v1.0/users/{$message->user->username}/avatar";
        }
      } catch (\Exception $e) {
        // Fallback if any error occurs accessing user properties
        $senderData['username'] = 'Ẩn danh';
        $senderData['profile_name'] = 'Ẩn danh';
      }
    }

    $messageData = [
      'id' => $message->id,
      'content' => $message->content,
      'type' => $message->type,
      'file_url' => $message->file_url ? $this->absoluteStorageUrl($message->file_url) : null,
      'is_edited' => $message->is_edited,
      'is_forwarded' => (bool) $message->is_forwarded,
      'is_myself' => $message->user_id === $user->id,
      'sender' => $senderData,
      'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
      'created_at_human' => $message->created_at ? $message->created_at->diffForHumans() : null,
      'read_at' => $message->read_at?->toISOString(),
      'metadata' => $message->metadata,
      'reply_to' => $this->formatReplyTo($message->replyTo),
      'reactions' => $this->formatReactions($message, $user->id),
    ];

    // Broadcast the message to other participants
    broadcast(new MessageSent($conversation->id, $messageData))->toOthers();

    // Send push notifications to other participants
    $this->sendChatPushNotifications($conversation, $messageData, $user->id);

    // Notify the author of the original message if this is a reply
    if ($message->reply_to_message_id && $message->replyTo) {
      NotificationService::createMessageReplyNotification($message->replyTo, $message, $user->id);
    }

    // Handle @mentions in message content
    $resolvedMentions = [];
    if ($message->content) {
      $convParticipantIds = $conversation->participants()->pluck('cyo_auth_accounts.id')->toArray();
      $resolvedMentions = NotificationService::resolveMentions($message->content, $convParticipantIds);
      $hasAllMention = false;
      foreach ($resolvedMentions as $m) {
        if ($m['user_id'] === null) {
          $hasAllMention = true;
          continue;
        }
        NotificationService::createMentionedInMessageNotification($m['user_id'], $message, $user->id);
      }
      // @all — notify every participant except the sender (group chats only)
      if ($hasAllMention && $conversation->type !== 'private') {
        foreach ($convParticipantIds as $pid) {
          if ($pid !== $user->id) {
            NotificationService::createMentionedInMessageNotification($pid, $message, $user->id);
          }
        }
      }
    }

    $messageData['mentions'] = $resolvedMentions;

    return $messageData;
  }

  /**
   * Extract the first frame of an uploaded chat video as a static JPG thumbnail.
   *
   * @param  string  $videoPath
   * @return string|null
   */
  private function createVideoFirstFrame(string $videoPath): ?string
  {
    $disk = Storage::disk('public');
    $framePath = 'chat_files/video-frames/' . Str::uuid() . '.jpg';
    $inputPath = $disk->path($videoPath);
    $outputPath = $disk->path($framePath);

    $disk->makeDirectory('chat_files/video-frames');

    try {
      $process = new Process([
        env('FFMPEG_BINARY', 'ffmpeg'),
        '-y',
        '-i',
        $inputPath,
        '-vframes',
        '1',
        '-vf',
        'scale=480:-1:flags=lanczos',
        $outputPath,
      ]);
      $process->setTimeout(60);
      $process->run();

      if (!$process->isSuccessful() || !$disk->exists($framePath)) {
        Log::warning('Unable to create first-frame thumbnail for chat video', [
          'video_path' => $videoPath,
          'error' => trim($process->getErrorOutput()),
        ]);

        return null;
      }

      return $this->absoluteStorageUrl($framePath);
    } catch (\Throwable $exception) {
      Log::warning('Error creating first-frame thumbnail for chat video', [
        'video_path' => $videoPath,
        'error' => $exception->getMessage(),
      ]);

      return null;
    }
  }

  /**
   * Send push notifications for chat messages to participants.
   *
   * @param \App\Models\Conversation $conversation
   * @param array $messageData
   * @param int $senderId
   * @return void
   */
  private function sendChatPushNotifications(Conversation $conversation, array $messageData, int $senderId): void
  {
    PushNotificationService::sendChatPushNotifications($conversation, $messageData, $senderId);
  }

  /**
   * Mark messages in a conversation as read.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function markAsRead(Request $request, $conversationId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $settings = NotificationSettings::where('user_id', $user->id)->first();
    $readReceiptsEnabled = $settings ? ($settings->chat_read_receipts ?? true) : true;

    if ($readReceiptsEnabled) {
      // Mark messages as read
      $conversation
        ->messages()
        ->where('user_id', '!=', $user->id)
        ->whereNull('read_at')
        ->update(['read_at' => now()]);

      // Update last_read_at for the user
      $conversation
        ->participants()
        ->where('user_id', $user->id)
        ->update(['last_read_at' => now()]);

      // Broadcast message read event
      broadcast(new MessageRead($conversation->id, $user->id))->toOthers();
    }

    return response()->json(['message' => 'Messages marked as read']);
  }

  /**
   * Delete a message.
   *
   * @param  int  $messageId
   * @return \Illuminate\Http\JsonResponse
   */
  public function deleteMessage($messageId)
  {
    $user = Auth::user();
    $message = Message::findOrFail($messageId);

    if ($message->user_id !== $user->id) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $message->delete();

    // Broadcast message deleted event
    broadcast(new MessageDeleted($message->conversation_id, $messageId))->toOthers();

    return response()->json(['message' => 'Message deleted']);
  }

  /**
   * Add or update the authenticated user's reaction to a message.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $messageId
   * @return \Illuminate\Http\JsonResponse
   */
  public function reactToMessage(Request $request, $messageId)
  {
    $request->validate([
      'reaction_type' => 'required|in:like,love,haha,wow,sad,angry',
    ]);

    $user = Auth::user();
    $message = Message::findOrFail($messageId);
    $conversation = $message->conversation;

    if (!$conversation || !$conversation->hasParticipant($user->id)) {
      $isPublicChat = $conversation && $conversation->is_public;
      if (!$isPublicChat) {
        return response()->json(['message' => 'Unauthorized'], 403);
      }
    }

    MessageReaction::create([
      'message_id' => $message->id,
      'user_id' => $user->id,
      'reaction_type' => $request->reaction_type,
    ]);

    NotificationService::createMessageReactionNotification($message, $user->id, $request->reaction_type);

    $reactions = $this->formatReactions($message->fresh('reactions.user.profile'), $user->id);

    // Broadcast the updated reactions to other participants
    broadcast(new MessageReacted($message->conversation_id, $message->id, $reactions))->toOthers();

    return response()->json([
      'message_id' => $message->id,
      'reactions' => $reactions,
    ]);
  }

  /**
   * Remove the authenticated user's reaction from a message.
   *
   * @param  int  $messageId
   * @return \Illuminate\Http\JsonResponse
   */
  public function removeMessageReaction($messageId)
  {
    $user = Auth::user();
    $message = Message::findOrFail($messageId);
    $conversation = $message->conversation;

    if (!$conversation || !$conversation->hasParticipant($user->id)) {
      $isPublicChat = $conversation && $conversation->is_public;
      if (!$isPublicChat) {
        return response()->json(['message' => 'Unauthorized'], 403);
      }
    }

    MessageReaction::where('message_id', $message->id)
      ->where('user_id', $user->id)
      ->delete();

    $reactions = $this->formatReactions($message->fresh('reactions.user.profile'), $user->id);

    // Broadcast the updated reactions to other participants
    broadcast(new MessageReacted($message->conversation_id, $message->id, $reactions))->toOthers();

    return response()->json([
      'message_id' => $message->id,
      'reactions' => $reactions,
    ]);
  }

  /**
   * Format the replied-to message for inclusion in a response.
   *
   * @param  \App\Models\Message|null  $message
   * @return array|null
   */
  private function formatReplyTo(?Message $message): ?array
  {
    if (!$message) {
      return null;
    }

    $isGuest = $message->user_id === null;

    if ($isGuest) {
      $sender = [
        'id' => null,
        'username' => $message->guest_name ?? 'Ẩn danh',
        'profile_name' => $message->guest_name ?? 'Ẩn danh',
        'avatar_url' => null,
      ];
    } elseif ($message->user) {
      $profile = $message->user->profile ?? null;
      $sender = [
        'id' => $message->user->id,
        'username' => $message->user->username ?? 'Ẩn danh',
        'profile_name' => ($profile->profile_name ?? null) ?? $message->user->username ?? 'Ẩn danh',
        'avatar_url' => $message->user->username
          ? config('app.url') . "/v1.0/users/{$message->user->username}/avatar"
          : null,
      ];
    } else {
      $sender = [
        'id' => null,
        'username' => 'Ẩn danh',
        'profile_name' => 'Ẩn danh',
        'avatar_url' => null,
      ];
    }

    return [
      'id' => $message->id,
      'content' => $message->content,
      'type' => $message->type,
      'file_url' => $message->file_url ? $this->absoluteStorageUrl($message->file_url) : null,
      'sender' => $sender,
    ];
  }

  /**
   * Build a reaction summary for a message: counts per type, total, the
   * current user's reaction (if any), and the list of reactors.
   *
   * @param  \App\Models\Message  $message
   * @param  int  $currentUserId
   * @return array
   */
  private function formatReactions(Message $message, int $currentUserId): array
  {
    $reactions = $message->relationLoaded('reactions') ? $message->reactions : $message->reactions()->with('user.profile')->get();

    $summary = [];
    $myReactions = [];

    foreach ($reactions as $reaction) {
      $type = $reaction->reaction_type;

      if (!isset($summary[$type])) {
        $summary[$type] = [
          'type' => $type,
          'count' => 0,
          'users' => [],
        ];
      }

      $summary[$type]['count']++;
      $uid = $reaction->user_id;
      if (!isset($summary[$type]['users'][$uid])) {
        $summary[$type]['users'][$uid] = [
          'id' => $uid,
          'username' => $reaction->user->username ?? 'Ẩn danh',
          'profile_name' => $reaction->user->profile->profile_name ?? ($reaction->user->username ?? 'Ẩn danh'),
          'count' => 0,
        ];
      }
      $summary[$type]['users'][$uid]['count']++;

      if ($reaction->user_id === $currentUserId) {
        $myReactions[] = $type;
      }
    }

    foreach ($summary as &$entry) {
      $entry['users'] = array_values($entry['users']);
    }

    return [
      'summary' => array_values($summary),
      'total' => $reactions->count(),
      'my_reactions' => $myReactions,
    ];
  }

  /**
   * Edit a message.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $messageId
   * @return \Illuminate\Http\JsonResponse
   */
  public function editMessage(Request $request, $messageId)
  {
    $request->validate([
      'content' => 'required|string'
    ]);

    $user = Auth::user();
    $message = Message::findOrFail($messageId);

    if ($message->user_id !== $user->id) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if ($message->is_recalled) {
      return response()->json(['message' => 'Không thể sửa tin nhắn đã bị thu hồi.'], 422);
    }

    $message->edit($request->content);

    $responseData = [
      'id' => $message->id,
      'content' => $message->content,
      'is_edited' => true,
      'updated_at' => $message->updated_at ? $message->updated_at->toISOString() : null,
      'updated_at_human' => $message->updated_at->diffForHumans(),
    ];

    broadcast(new MessageEdited(
      $message->conversation_id,
      $message->id,
      $message->content,
      $message->updated_at->toISOString()
    ))->toOthers();

    return response()->json($responseData);
  }

  /**
   * Recall (unsend) a message — replaces content with a recall notice visible to all participants.
   *
   * @param  int  $messageId
   * @return \Illuminate\Http\JsonResponse
   */
  public function recallMessage($messageId)
  {
    $user = Auth::user();
    $message = Message::findOrFail($messageId);

    if ($message->user_id !== $user->id) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if ($message->is_recalled) {
      return response()->json(['message' => 'Tin nhắn đã được thu hồi trước đó.'], 422);
    }

    $message->update([
      'is_recalled' => true,
    ]);

    // Delete notifications directly tied to this message (reactions, reply-sent notifications)
    Notification::where('notifiable_type', Message::class)
      ->where('notifiable_id', $message->id)
      ->delete();

    // Delete reply notifications sent to this message's owner (original_message_id in data)
    Notification::where('type', 'message_replied')
      ->where('data->original_message_id', $message->id)
      ->delete();

    broadcast(new MessageRecalled($message->conversation_id, $message->id))->toOthers();

    return response()->json([
      'id' => $message->id,
      'is_recalled' => true,
    ]);
  }

  /**
   * Forward a message to one or more conversations and/or users. Forwarding to a
   * user_id finds (or creates) the 1-on-1 private conversation with that user.
   * Each target is processed independently — one failing target (e.g. blocked user,
   * no longer a participant) doesn't stop the others from succeeding.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $messageId
   * @return \Illuminate\Http\JsonResponse
   */
  public function forwardMessage(Request $request, $messageId)
  {
    $request->validate([
      'conversation_ids' => 'required_without:user_ids|array',
      'conversation_ids.*' => 'integer|exists:cyo_conversations,id',
      'user_ids' => 'required_without:conversation_ids|array',
      'user_ids.*' => 'integer|exists:cyo_auth_accounts,id',
    ]);

    $user = Auth::user();
    $message = Message::findOrFail($messageId);
    $sourceConversation = $message->conversation;

    if (!$sourceConversation || !$sourceConversation->isAccessibleBy($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if ($message->is_recalled) {
      return response()->json(['message' => 'Không thể chuyển tiếp tin nhắn đã bị thu hồi.'], 422);
    }

    if (!$message->content && !$message->file_url) {
      return response()->json(['message' => 'Tin nhắn không có nội dung để chuyển tiếp.'], 422);
    }

    $conversationIds = array_map('intval', $request->input('conversation_ids', []));
    $userIds = array_unique(array_map('intval', $request->input('user_ids', [])));

    // Resolve target user_ids into (find-or-create) 1-on-1 private conversations
    foreach ($userIds as $targetUserId) {
      if ($targetUserId === $user->id) {
        continue;
      }
      $conversationIds[] = $this->findOrCreatePrivateConversation($user->id, $targetUserId)->id;
    }

    $conversationIds = array_values(array_unique($conversationIds));

    if (empty($conversationIds)) {
      return response()->json(['message' => 'Vui lòng chọn ít nhất một cuộc trò chuyện hoặc người dùng để chuyển tiếp.'], 422);
    }

    if (count($conversationIds) > 20) {
      return response()->json(['message' => 'Chỉ có thể chuyển tiếp đến tối đa 20 cuộc trò chuyện cùng lúc.'], 422);
    }

    // Snapshot the original sender's identity now, so the attribution shown on the
    // forwarded copy survives even if the source message/conversation is later
    // recalled, edited, or deleted.
    $originalSenderName = $message->guest_name;
    if ($message->user_id) {
      $originalSender = $message->user ?: AuthAccount::with('profile')->find($message->user_id);
      $originalSenderName = $originalSender ? $this->displayName($originalSender) : $originalSenderName;
    }
    $forwardedFrom = [
      'message_id' => $message->id,
      'conversation_id' => $message->conversation_id,
      'sender_user_id' => $message->user_id,
      'sender_name' => $originalSenderName,
    ];

    $results = [];

    foreach ($conversationIds as $targetConversationId) {
      $targetConversation = Conversation::find($targetConversationId);

      if (!$targetConversation) {
        $results[] = ['conversation_id' => $targetConversationId, 'status' => 'error', 'error' => 'Cuộc trò chuyện không tồn tại.'];
        continue;
      }

      if (!$targetConversation->isAccessibleBy($user->id)) {
        $results[] = ['conversation_id' => $targetConversationId, 'status' => 'error', 'error' => 'Bạn không phải thành viên của cuộc trò chuyện này.'];
        continue;
      }

      if ($targetConversation->type === 'private') {
        $otherParticipant = $targetConversation->participants()->where('cyo_auth_accounts.id', '!=', $user->id)->first();
        if ($otherParticipant) {
          $isBlocked = UserBlock::where('user_id', $user->id)->where('blocked_user_id', $otherParticipant->id)->exists()
            || UserBlock::where('user_id', $otherParticipant->id)->where('blocked_user_id', $user->id)->exists();
          if ($isBlocked) {
            $results[] = ['conversation_id' => $targetConversationId, 'status' => 'error', 'error' => 'Không thể gửi tin nhắn cho người dùng này.'];
            continue;
          }
        }
      }

      // Auto-join the public chat, mirroring sendMessage()'s behavior.
      if ($targetConversation->is_public && !$targetConversation->hasParticipant($user->id)) {
        $targetConversation->participants()->attach($user->id, ['last_read_at' => now()]);
      }

      $forwardedMessage = Message::create([
        'conversation_id' => $targetConversation->id,
        'user_id' => $user->id,
        'content' => $message->content,
        'type' => $message->type,
        'file_url' => $message->file_url,
        'metadata' => array_merge($message->metadata ?? [], ['forwarded_from' => $forwardedFrom]),
        'is_forwarded' => true,
      ]);

      $forwardedMessageData = $this->finalizeAndBroadcastMessage($targetConversation, $forwardedMessage, $user);

      $results[] = [
        'conversation_id' => $targetConversation->id,
        'status' => 'sent',
        'message' => $forwardedMessageData,
      ];
    }

    return response()->json(['results' => $results]);
  }

  /**
   * Create a new group conversation.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function createGroupConversation(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'participants' => 'required|array|min:1',
      'participants.*' => 'exists:cyo_auth_accounts,id'
    ]);

    $user = Auth::user();

    $otherParticipantIds = array_values(array_diff(array_unique($request->participants), [$user->id]));

    if (empty($otherParticipantIds)) {
      return response()->json(['message' => 'Nhóm cần có ít nhất một thành viên khác ngoài bạn.'], 422);
    }

    // Create new group conversation
    $conversation = Conversation::create([
      'type' => 'group',
      'name' => $request->name,
      'created_by' => $user->id,
    ]);

    // The creator is the owner; everyone else joins as a regular member
    $conversation->participants()->attach($user->id, ['role' => 'owner']);
    foreach ($otherParticipantIds as $participantId) {
      $conversation->participants()->attach($participantId, ['role' => 'member']);
    }

    $conversation->load('participants.profile');

    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã tạo nhóm "%s"', $this->displayName($user), $conversation->name),
      ['event' => 'group_created', 'actor_name' => $this->displayName($user), 'group_name' => $conversation->name]
    );

    foreach ($otherParticipantIds as $participantId) {
      NotificationService::createAddedToGroupNotification($participantId, $conversation, $user->id);
    }

    return response()->json([
      'id' => $conversation->id,
      'name' => $conversation->name,
      'type' => 'group',
      'avatar_url' => null,
      'created_by' => $conversation->created_by,
      'participants' => $conversation->participants->map(fn($p) => $this->formatParticipant($p, true)),
    ], 201);
  }

  /**
   * Update group conversation details. Any participant may rename the group — renaming
   * and changing the avatar are open to everyone; only kicking members, assigning
   * deputies, and deleting the group are restricted to owner/deputy.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateGroupConversation(Request $request, $conversationId)
  {
    $request->validate([
      'name' => 'required|string|max:255'
    ]);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if (!$conversation->canPerform($user->id, 'perm_change_name')) {
      return response()->json(['message' => 'Bạn không có quyền đổi tên nhóm.'], 403);
    }

    $oldName = $conversation->name;
    $conversation->update([
      'name' => $request->name
    ]);

    if ($oldName !== $conversation->name) {
      $this->createSystemMessage(
        $conversation,
        sprintf('%s đã đổi tên nhóm thành "%s"', $this->displayName($user), $conversation->name),
        ['event' => 'group_renamed', 'actor_name' => $this->displayName($user), 'group_name' => $conversation->name]
      );
    }

    return response()->json([
      'id' => $conversation->id,
      'name' => $conversation->name
    ]);
  }

  /**
   * Update a group conversation's avatar. Open to any participant, same as renaming.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateGroupAvatar(Request $request, $conversationId)
  {
    $request->validate([
      'avatar' => 'required|image|max:5120',
    ]);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if (!$conversation->canPerform($user->id, 'perm_change_avatar')) {
      return response()->json(['message' => 'Bạn không có quyền đổi ảnh đại diện nhóm.'], 403);
    }

    $oldAvatarPath = $conversation->avatar_url;
    $path = $request->file('avatar')->store('group_avatars', 'public');
    $conversation->update(['avatar_url' => $path]);

    if ($oldAvatarPath) {
      Storage::disk('public')->delete($oldAvatarPath);
    }

    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã đổi ảnh đại diện nhóm', $this->displayName($user)),
      ['event' => 'group_avatar_changed', 'actor_name' => $this->displayName($user)]
    );

    return response()->json(['avatar_url' => $this->absoluteStorageUrl($path)]);
  }

  /**
   * Verify the conversation supports a chat background (any private or group
   * conversation the user participates in — never the single app-wide public
   * chat, which has no per-conversation settings at all) and the user may see it.
   *
   * @return \App\Models\Conversation|\Illuminate\Http\JsonResponse
   */
  private function conversationForBackground($conversationId, $userId, bool $requireChangePermission = false)
  {
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->is_public) {
      return response()->json(['message' => 'The public chat has no custom background'], 400);
    }

    if (!$conversation->hasParticipant($userId)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if ($requireChangePermission && $conversation->type === 'group' && !$conversation->canPerform($userId, 'perm_change_background')) {
      return response()->json(['message' => 'Bạn không có quyền đổi ảnh nền cuộc trò chuyện.'], 403);
    }

    return $conversation;
  }

  private function formatBackgroundHistoryEntry(ConversationBackgroundHistory $entry): array
  {
    return [
      'id' => $entry->user_content_id,
      'url' => $this->absoluteStorageUrl($entry->content->file_path ?? null),
      'used_at' => $entry->used_at?->toISOString(),
    ];
  }

  /**
   * Notify every other participant that the chat background changed.
   */
  private function notifyBackgroundChanged(Conversation $conversation, int $actorId): void
  {
    foreach ($conversation->participants as $participant) {
      if ($participant->id === $actorId) {
        continue;
      }
      NotificationService::createConversationBackgroundChangedNotification($participant->id, $conversation, $actorId);
    }
  }

  /**
   * Get the current chat background and this conversation's background history
   * (every image previously used, most recently used first, so participants can
   * pick an old one again without re-uploading).
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getConversationBackground($conversationId)
  {
    $user = Auth::user();
    $conversation = $this->conversationForBackground($conversationId, $user->id);
    if (!$conversation instanceof Conversation) {
      return $conversation;
    }

    $conversation->load('backgroundContent', 'backgroundHistory.content');

    return response()->json([
      'background_url' => $this->absoluteStorageUrl($conversation->backgroundContent->file_path ?? null),
      'history' => $conversation->backgroundHistory->map(fn($entry) => $this->formatBackgroundHistoryEntry($entry))->values(),
    ]);
  }

  /**
   * Upload a new image and set it as this conversation's chat background.
   * Any participant of a private or group conversation may do this. Goes
   * through the same async compression pipeline as every other image upload.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function uploadConversationBackground(Request $request, $conversationId)
  {
    $request->validate([
      'image' => 'required|image|max:10240',
    ]);

    $user = Auth::user();
    $conversation = $this->conversationForBackground($conversationId, $user->id, true);
    if (!$conversation instanceof Conversation) {
      return $conversation;
    }

    $file = $request->file('image');
    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs('chat_backgrounds', $fileName, 'public');

    $userContent = UserContent::create([
      'user_id' => $user->id,
      'file_name' => $fileName,
      'file_path' => $path,
      'file_type' => $file->getMimeType(),
      'file_size' => $file->getSize(),
    ]);

    // Chat backgrounds are stored uncompressed — unlike regular post/message
    // images, they're viewed full-bleed behind text so compression artifacts
    // are much more visible.
    ConversationBackgroundHistory::updateOrCreate(
      ['conversation_id' => $conversation->id, 'user_content_id' => $userContent->id],
      ['set_by' => $user->id, 'used_at' => now()]
    );

    $conversation->update(['background_content_id' => $userContent->id]);

    $backgroundUrl = $this->absoluteStorageUrl($userContent->file_path);

    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã đổi ảnh nền cuộc trò chuyện', $this->displayName($user)),
      ['event' => 'background_changed', 'actor_name' => $this->displayName($user), 'background_url' => $backgroundUrl]
    );

    $this->notifyBackgroundChanged($conversation, $user->id);

    return response()->json(['background_url' => $backgroundUrl]);
  }

  /**
   * Re-select a previously used background image from this conversation's
   * history, without re-uploading it.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function selectConversationBackground(Request $request, $conversationId)
  {
    $request->validate([
      'user_content_id' => 'required|integer',
    ]);

    $user = Auth::user();
    $conversation = $this->conversationForBackground($conversationId, $user->id, true);
    if (!$conversation instanceof Conversation) {
      return $conversation;
    }

    $historyEntry = ConversationBackgroundHistory::where('conversation_id', $conversation->id)
      ->where('user_content_id', $request->user_content_id)
      ->with('content')
      ->first();

    if (!$historyEntry) {
      return response()->json(['message' => 'This image is not in this conversation\'s background history'], 404);
    }

    $historyEntry->update(['used_at' => now()]);
    $conversation->update(['background_content_id' => $historyEntry->user_content_id]);

    $backgroundUrl = $this->absoluteStorageUrl($historyEntry->content->file_path ?? null);

    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã đổi ảnh nền cuộc trò chuyện', $this->displayName($user)),
      ['event' => 'background_changed', 'actor_name' => $this->displayName($user), 'background_url' => $backgroundUrl]
    );

    $this->notifyBackgroundChanged($conversation, $user->id);

    return response()->json(['background_url' => $backgroundUrl]);
  }

  /**
   * Clear the chat background back to the default appearance. The image stays
   * in the conversation's background history so it can still be picked again.
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function resetConversationBackground($conversationId)
  {
    $user = Auth::user();
    $conversation = $this->conversationForBackground($conversationId, $user->id, true);
    if (!$conversation instanceof Conversation) {
      return $conversation;
    }

    $conversation->update(['background_content_id' => null]);

    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã đặt lại ảnh nền mặc định', $this->displayName($user)),
      ['event' => 'background_reset', 'actor_name' => $this->displayName($user), 'background_url' => null]
    );

    $this->notifyBackgroundChanged($conversation, $user->id);

    return response()->json(['background_url' => null]);
  }

  /**
   * Get details of a group conversation, including participants and their roles.
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getGroupDetails($conversationId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $conversation->load('participants.profile');

    return response()->json([
      'id' => $conversation->id,
      'name' => $conversation->name,
      'type' => $conversation->type,
      'avatar_url' => $conversation->avatar_url ? $this->absoluteStorageUrl($conversation->avatar_url) : null,
      'created_by' => $conversation->created_by,
      'created_at' => $conversation->created_at?->toISOString(),
      'is_owner' => $conversation->isOwner($user->id),
      'is_deputy' => $conversation->isDeputy($user->id),
      'can_manage' => $conversation->isManager($user->id),
      'permissions' => $this->formatGroupPermissions($conversation, $user->id),
      'participants' => $conversation->participants->map(fn($p) => $this->formatParticipant($p, true)),
    ]);
  }

  /**
   * Build the permissions payload for a group: the raw setting per action plus
   * whether the current user is allowed to perform each one right now.
   *
   * @param  \App\Models\Conversation  $conversation
   * @param  int  $userId
   * @return array
   */
  private function formatGroupPermissions(Conversation $conversation, $userId): array
  {
    $settings = [];
    $canDo = [];

    foreach (array_keys(Conversation::PERMISSION_KEYS) as $key) {
      $settings[$key] = $conversation->{$key};
      $canDo[$key] = $key === 'perm_share_invite_link' && $conversation->{$key} === 'none'
        ? false
        : $conversation->canPerform($userId, $key);
    }

    return [
      'settings' => $settings,
      'can' => $canDo,
    ];
  }

  /**
   * Update a group's permission settings (who may rename, change avatar/background,
   * remove members, share the invite link, invite members). Owner only.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateGroupPermissions(Request $request, $conversationId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->isOwner($user->id)) {
      return response()->json(['message' => 'Chỉ trưởng nhóm mới có thể thay đổi cài đặt quản lý nhóm.'], 403);
    }

    $rules = [];
    foreach (Conversation::PERMISSION_KEYS as $key => $allowedValues) {
      $rules[$key] = ['sometimes', 'string', 'in:' . implode(',', $allowedValues)];
    }
    $request->validate($rules);

    $updates = array_intersect_key($request->all(), Conversation::PERMISSION_KEYS);

    if (empty($updates)) {
      return response()->json(['message' => 'Không có thay đổi nào được gửi lên.'], 422);
    }

    // Only mention keys whose value actually changed, so re-saving the same
    // setting doesn't spam the conversation with a no-op system message.
    $changedUpdates = array_filter(
      $updates,
      fn($value, $key) => $conversation->{$key} !== $value,
      ARRAY_FILTER_USE_BOTH
    );

    $conversation->update($updates);

    // Sharing disabled entirely — kill the active link so it can't keep circulating.
    if (($updates['perm_share_invite_link'] ?? null) === 'none' && $conversation->invite_token) {
      $conversation->update(['invite_token' => null]);
    }

    if (!empty($changedUpdates)) {
      $actorName = $this->displayName($user);
      foreach ($changedUpdates as $key => $value) {
        $this->createSystemMessage(
          $conversation,
          $this->formatPermissionChangeMessage($actorName, $key, $value),
          [
            'event' => 'permission_changed',
            'actor_id' => $user->id,
            'actor_name' => $actorName,
            'permission_key' => $key,
            'permission_value' => $value,
          ]
        );
      }
    }

    return response()->json([
      'message' => 'Đã cập nhật cài đặt quản lý nhóm thành công.',
      'permissions' => $this->formatGroupPermissions($conversation, $user->id),
    ]);
  }

  /**
   * Build a human-readable system message describing a single permission change,
   * e.g. "Đào Phúc đã giới hạn chia sẻ liên kết mời cho chỉ trưởng nhóm".
   *
   * @param  string  $actorName
   * @param  string  $key  One of Conversation::PERMISSION_KEYS
   * @param  string  $value
   * @return string
   */
  private function formatPermissionChangeMessage(string $actorName, string $key, string $value): string
  {
    $actionLabels = [
      'perm_change_name' => 'đổi tên nhóm',
      'perm_change_avatar' => 'đổi ảnh đại diện nhóm',
      'perm_change_background' => 'đổi ảnh nền cuộc trò chuyện',
      'perm_remove_members' => 'xóa thành viên',
      'perm_share_invite_link' => 'chia sẻ liên kết mời',
      'perm_invite_members' => 'mời thành viên',
    ];

    $valueLabels = [
      'owner' => 'chỉ trưởng nhóm',
      'deputy' => 'trưởng nhóm và phó nhóm',
      'member' => 'tất cả thành viên',
    ];

    $action = $actionLabels[$key] ?? $key;

    if ($value === 'none') {
      // Only perm_share_invite_link supports 'none' — disabling it entirely
      // reads more naturally than "cho phép ... không có ai".
      return sprintf('%s đã tắt tính năng %s', $actorName, $action);
    }

    return sprintf('%s đã giới hạn quyền %s cho %s', $actorName, $action, $valueLabels[$value] ?? $value);
  }

  /**
   * Get read-receipt data for a group conversation: every other participant's
   * last_read_at timestamp. The frontend derives "seen by" avatars from this by
   * finding, per participant, the newest message whose created_at they've read
   * past — there's no per-message read table, just like Messenger doesn't need
   * one either (a single "read up to this point in time" timestamp is enough).
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getGroupSeenReceipts($conversationId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group') {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $settings = NotificationSettings::where('user_id', $user->id)->first();
    $readReceiptsEnabled = $settings ? ($settings->chat_read_receipts ?? true) : true;

    if (!$readReceiptsEnabled) {
      return response()->json(['participants' => []]);
    }

    $participants = $conversation->participants()
      ->where('cyo_auth_accounts.id', '!=', $user->id)
      ->with('profile')
      ->get();

    // Filter out participants who have disabled read receipts (they appear unread to others)
    $participantSettings = NotificationSettings::whereIn('user_id', $participants->pluck('id'))
      ->pluck('chat_read_receipts', 'user_id');

    return response()->json([
      'participants' => $participants
        ->filter(function ($p) use ($participantSettings) {
          return $participantSettings->get($p->id, true) !== false;
        })
        ->map(function ($p) {
          $data = $this->formatParticipant($p);
          $data['last_read_at'] = $p->pivot->last_read_at
            ? Carbon::parse($p->pivot->last_read_at)->toISOString()
            : null;

          return $data;
        })->values(),
    ]);
  }

  /**
   * Add participants to a group conversation. Any current member may add new participants.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function addGroupParticipants(Request $request, $conversationId)
  {
    $request->validate([
      'participants' => 'required|array|min:1',
      'participants.*' => 'exists:cyo_auth_accounts,id'
    ]);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if (!$conversation->canPerform($user->id, 'perm_invite_members')) {
      return response()->json(['message' => 'Bạn không có quyền mời thành viên vào nhóm.'], 403);
    }

    $existingIds = $conversation->participants()->pluck('cyo_auth_accounts.id')->toArray();
    $newParticipantIds = array_values(array_diff(array_unique($request->participants), $existingIds));

    if (empty($newParticipantIds)) {
      return response()->json(['message' => 'Tất cả người dùng đã ở trong nhóm.'], 422);
    }

    foreach ($newParticipantIds as $participantId) {
      $conversation->participants()->attach($participantId, ['role' => 'member']);
    }

    $conversation->load('participants.profile');

    $addedNames = $conversation->participants
      ->whereIn('id', $newParticipantIds)
      ->map(fn($p) => $this->displayName($p))
      ->implode(', ');
    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã thêm %s vào nhóm', $this->displayName($user), $addedNames),
      ['event' => 'members_added', 'actor_name' => $this->displayName($user), 'member_names' => $addedNames]
    );

    foreach ($newParticipantIds as $participantId) {
      NotificationService::createAddedToGroupNotification($participantId, $conversation, $user->id);
    }

    return response()->json([
      'participants' => $conversation->participants->map(fn($p) => $this->formatParticipant($p, true)),
    ]);
  }

  /**
   * Remove a participant from a group conversation. The owner or a deputy may remove
   * a regular member; only the owner may remove a deputy; the owner can never be
   * removed this way (they must leave, transfer ownership, or delete the group).
   * Any participant may always remove themselves (i.e. leave the group).
   *
   * @param  int  $conversationId
   * @param  int  $userId
   * @return \Illuminate\Http\JsonResponse
   */
  public function removeGroupParticipant($conversationId, $userId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $isSelfLeave = (int) $userId === (int) $user->id;

    if (!$isSelfLeave) {
      if (!$conversation->canPerform($user->id, 'perm_remove_members')) {
        return response()->json(['message' => 'Bạn không có quyền xóa thành viên khỏi nhóm.'], 403);
      }
      if ($conversation->isOwner($userId)) {
        return response()->json(['message' => 'Không thể xóa trưởng nhóm khỏi nhóm.'], 403);
      }
      if (!$conversation->isOwner($user->id) && $conversation->isDeputy($userId)) {
        return response()->json(['message' => 'Chỉ trưởng nhóm mới có thể xóa phó nhóm khỏi nhóm.'], 403);
      }
    }

    if (!$conversation->hasParticipant($userId)) {
      return response()->json(['message' => 'Người dùng không ở trong nhóm.'], 404);
    }

    $removedUser = AuthAccount::with('profile')->find($userId);
    $wasOwner = $conversation->isOwner($userId);

    $conversation->participants()->detach($userId);

    $this->handlePostParticipantRemoval($conversation, $removedUser, $wasOwner, $isSelfLeave, $user);

    return response()->json(['message' => 'Participant removed successfully']);
  }

  /**
   * Leave a group conversation. Thin convenience wrapper around removeGroupParticipant()
   * that always targets the authenticated user.
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function leaveGroupConversation($conversationId)
  {
    return $this->removeGroupParticipant($conversationId, Auth::id());
  }

  /**
   * Delete a group conversation entirely (as opposed to just leaving it). Only the
   * owner may do this — deputies can manage members but can't dissolve the group.
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function deleteGroupConversation($conversationId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->isOwner($user->id)) {
      return response()->json(['message' => 'Chỉ trưởng nhóm mới có thể xóa nhóm.'], 403);
    }

    // Messages/participants cascade at the DB level.
    $conversation->delete();

    return response()->json(['message' => 'Đã xóa nhóm thành công.']);
  }

  /**
   * Promote a participant to deputy (phó nhóm). Owner only. A group can have multiple
   * deputies; deputies get the same management powers as the owner except deleting
   * the group or assigning/removing other deputies.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function addGroupDeputy(Request $request, $conversationId)
  {
    $request->validate(['user_id' => 'required|integer|exists:cyo_auth_accounts,id']);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->isOwner($user->id)) {
      return response()->json(['message' => 'Chỉ trưởng nhóm mới có thể chỉ định phó nhóm.'], 403);
    }

    $targetId = (int) $request->user_id;

    if (!$conversation->hasParticipant($targetId)) {
      return response()->json(['message' => 'Người dùng không ở trong nhóm.'], 404);
    }

    if ($conversation->isOwner($targetId)) {
      return response()->json(['message' => 'Trưởng nhóm đã có toàn quyền quản lý.'], 422);
    }

    if ($conversation->isDeputy($targetId)) {
      return response()->json(['message' => 'Người dùng đã là phó nhóm.'], 422);
    }

    $conversation->participants()->updateExistingPivot($targetId, ['role' => 'deputy']);

    $target = AuthAccount::with('profile')->find($targetId);
    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã chỉ định %s làm phó nhóm', $this->displayName($user), $this->displayName($target)),
      ['event' => 'deputy_assigned', 'actor_name' => $this->displayName($user), 'target_name' => $this->displayName($target)]
    );
    NotificationService::createGroupRoleChangedNotification($targetId, $conversation, $user->id, 'deputy');

    return response()->json(['message' => 'Đã chỉ định phó nhóm thành công.']);
  }

  /**
   * Demote a deputy back to a regular member. Owner only.
   *
   * @param  int  $conversationId
   * @param  int  $userId
   * @return \Illuminate\Http\JsonResponse
   */
  public function removeGroupDeputy($conversationId, $userId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->isOwner($user->id)) {
      return response()->json(['message' => 'Chỉ trưởng nhóm mới có thể gỡ phó nhóm.'], 403);
    }

    if (!$conversation->isDeputy($userId)) {
      return response()->json(['message' => 'Người dùng không phải là phó nhóm.'], 422);
    }

    $conversation->participants()->updateExistingPivot($userId, ['role' => 'member']);

    $target = AuthAccount::with('profile')->find($userId);
    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã gỡ %s khỏi vai trò phó nhóm', $this->displayName($user), $this->displayName($target)),
      ['event' => 'deputy_removed', 'actor_name' => $this->displayName($user), 'target_name' => $this->displayName($target)]
    );
    NotificationService::createGroupRoleChangedNotification($userId, $conversation, $user->id, 'member');

    return response()->json(['message' => 'Đã gỡ phó nhóm thành công.']);
  }

  /**
   * Transfer group ownership to another participant. Owner only, and voluntary
   * (as opposed to the random succession that kicks in when an owner leaves/is
   * removed without nominating a successor). The previous owner becomes a regular
   * member — they can be re-promoted to deputy afterwards if desired.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function transferGroupOwnership(Request $request, $conversationId)
  {
    $request->validate(['user_id' => 'required|integer|exists:cyo_auth_accounts,id']);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->isOwner($user->id)) {
      return response()->json(['message' => 'Chỉ trưởng nhóm mới có thể chuyển quyền trưởng nhóm.'], 403);
    }

    $newOwnerId = (int) $request->user_id;

    if ($newOwnerId === $user->id) {
      return response()->json(['message' => 'Bạn đã là trưởng nhóm.'], 422);
    }

    if (!$conversation->hasParticipant($newOwnerId)) {
      return response()->json(['message' => 'Người dùng không ở trong nhóm.'], 404);
    }

    $newOwner = AuthAccount::with('profile')->find($newOwnerId);

    $conversation->participants()->updateExistingPivot($user->id, ['role' => 'member']);
    $conversation->participants()->updateExistingPivot($newOwnerId, ['role' => 'owner']);

    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã chuyển quyền trưởng nhóm cho %s', $this->displayName($user), $this->displayName($newOwner)),
      ['event' => 'ownership_transferred', 'actor_name' => $this->displayName($user), 'new_owner_name' => $this->displayName($newOwner)]
    );
    NotificationService::createGroupRoleChangedNotification($newOwnerId, $conversation, $user->id, 'owner');

    return response()->json(['message' => 'Đã chuyển quyền trưởng nhóm thành công.']);
  }

  /**
   * Get (or lazily create) this group's invite link. Open to any participant.
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getGroupInviteLink($conversationId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if ($conversation->perm_share_invite_link === 'none') {
      if ($conversation->invite_token) {
        $conversation->update(['invite_token' => null]);
      }
      return response()->json(['message' => 'Liên kết mời nhóm đã bị vô hiệu hóa.'], 403);
    }

    if (!$conversation->canPerform($user->id, 'perm_share_invite_link')) {
      return response()->json(['message' => 'Bạn không có quyền chia sẻ liên kết mời nhóm.'], 403);
    }

    if (!$conversation->invite_token) {
      $conversation->update(['invite_token' => Str::random(32)]);
    }

    return response()->json($this->formatInviteLink($conversation));
  }

  /**
   * Regenerate this group's invite link, invalidating the previous one.
   *
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function regenerateGroupInviteLink($conversationId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if ($conversation->type !== 'group' || $conversation->is_public) {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    if ($conversation->perm_share_invite_link === 'none' || !$conversation->canPerform($user->id, 'perm_share_invite_link')) {
      return response()->json(['message' => 'Bạn không có quyền chia sẻ liên kết mời nhóm.'], 403);
    }

    $conversation->update(['invite_token' => Str::random(32)]);

    return response()->json($this->formatInviteLink($conversation));
  }

  /**
   * Build the shareable invite-link payload for a group.
   *
   * @param  \App\Models\Conversation  $conversation
   * @return array
   */
  private function formatInviteLink(Conversation $conversation): array
  {
    $baseUrl = rtrim(env('APP_UI_URL', 'https://chuyenbienhoa.com'), '/');

    return [
      'invite_token' => $conversation->invite_token,
      'invite_url' => "{$baseUrl}/invite/{$conversation->invite_token}",
    ];
  }

  /**
   * Public preview of a group invite link (no auth required) — the web landing page
   * needs enough info to render "You've been invited to join <name>" before the
   * visitor necessarily logs in.
   *
   * @param  string  $token
   * @return \Illuminate\Http\JsonResponse
   */
  public function getGroupInvitePreview($token)
  {
    $conversation = Conversation::where('invite_token', $token)->first();

    if (!$conversation) {
      return response()->json(['message' => 'Liên kết mời không hợp lệ hoặc đã hết hạn.'], 404);
    }

    $user = Auth::guard('sanctum')->user() ?? Auth::user();

    return response()->json([
      'conversation_id' => $conversation->id,
      'name' => $conversation->name,
      'avatar_url' => $conversation->avatar_url ? $this->absoluteStorageUrl($conversation->avatar_url) : null,
      'member_count' => $conversation->participants()->count(),
      'already_member' => $user ? $conversation->hasParticipant($user->id) : false,
    ]);
  }

  /**
   * Join a group via its invite link. Requires auth. Idempotent — joining a group
   * you're already a member of just reports success without duplicating anything.
   *
   * @param  string  $token
   * @return \Illuminate\Http\JsonResponse
   */
  public function joinGroupViaInvite($token)
  {
    $user = Auth::user();
    $conversation = Conversation::where('invite_token', $token)->first();

    if (!$conversation) {
      return response()->json(['message' => 'Liên kết mời không hợp lệ hoặc đã hết hạn.'], 404);
    }

    if ($conversation->hasParticipant($user->id)) {
      return response()->json(['conversation_id' => $conversation->id, 'already_member' => true]);
    }

    $conversation->participants()->attach($user->id, ['role' => 'member']);

    $this->createSystemMessage(
      $conversation,
      sprintf('%s đã tham gia nhóm qua lời mời', $this->displayName($user)),
      ['event' => 'joined_via_invite', 'actor_name' => $this->displayName($user)]
    );

    return response()->json(['conversation_id' => $conversation->id, 'already_member' => false], 201);
  }

  /**
   * After a participant is detached from a group: delete the group if it's now empty,
   * otherwise post a system message, notify the removed user (if kicked), and promote
   * a random remaining participant to owner if the one who left/was removed was the owner.
   *
   * @param  \App\Models\Conversation  $conversation
   * @param  \App\Models\AuthAccount|null  $removedUser
   * @param  bool  $wasOwner
   * @param  bool  $isSelfLeave
   * @param  \App\Models\AuthAccount  $actor
   * @return void
   */
  private function handlePostParticipantRemoval(Conversation $conversation, ?AuthAccount $removedUser, bool $wasOwner, bool $isSelfLeave, AuthAccount $actor): void
  {
    $remaining = $conversation->participants()->with('profile')->get();

    if ($remaining->isEmpty()) {
      // No one left in the group — delete it (messages/participants cascade at the DB level).
      $conversation->delete();
      return;
    }

    if ($removedUser) {
      $actorName = $this->displayName($actor);
      $removedName = $this->displayName($removedUser);
      $this->createSystemMessage(
        $conversation,
        $isSelfLeave ? "{$removedName} đã rời khỏi nhóm" : "{$actorName} đã xóa {$removedName} khỏi nhóm",
        $isSelfLeave
          ? ['event' => 'member_left', 'member_name' => $removedName]
          : ['event' => 'member_removed', 'actor_name' => $actorName, 'member_name' => $removedName]
      );

      if (!$isSelfLeave) {
        NotificationService::createRemovedFromGroupNotification($removedUser->id, $conversation, $actor->id);
      }
    }

    if ($wasOwner) {
      // The group must always have an owner — randomly draw one from whoever's left
      // (deputies included; being a deputy doesn't give priority here).
      $newOwner = $remaining->random();
      $conversation->participants()->updateExistingPivot($newOwner->id, ['role' => 'owner']);
      $this->createSystemMessage(
        $conversation,
        sprintf('%s đã trở thành trưởng nhóm mới', $this->displayName($newOwner)),
        ['event' => 'owner_randomly_assigned', 'new_owner_name' => $this->displayName($newOwner)]
      );
    }
  }

  /**
   * Format a participant (AuthAccount with loaded profile) for API responses.
   *
   * @param  \App\Models\AuthAccount  $participant
   * @param  bool  $includeRole  Include the participant's group role (owner/member) from the pivot
   * @return array
   */
  private function formatParticipant($participant, bool $includeRole = false): array
  {
    $data = [
      'id' => $participant->id,
      'username' => $participant->username,
      'profile_name' => $participant->profile->profile_name ?? $participant->username,
      'avatar_url' => config('app.url') . "/v1.0/users/{$participant->username}/avatar",
    ];

    if ($includeRole && isset($participant->pivot)) {
      $data['role'] = $participant->pivot->role;
    }

    return $data;
  }

  /**
   * Get the display name (profile name, falling back to username) for a user.
   *
   * @param  \App\Models\AuthAccount  $user
   * @return string
   */
  private function displayName(AuthAccount $user): string
  {
    return $user->profile->profile_name ?? $user->username;
  }

  /**
   * Build an absolute URL for a file on the 'public' storage disk.
   *
   * Storage::url() only returns an absolute URL when the 'public' disk's own
   * 'url' config (filesystems.php, derived from env('APP_URL')) is set — if
   * APP_URL isn't picked up there, it silently falls back to a host-relative
   * path like "/storage/...". Served from an API response, a relative path
   * gets resolved by the browser against whatever page it's rendered on
   * (e.g. the web frontend's own origin), not this API's — so always prefix
   * config('app.url') explicitly here, the same way avatar URLs already do
   * elsewhere in this controller, instead of trusting Storage::url() alone.
   *
   * @param  string|null  $path
   * @return string|null
   */
  private function absoluteStorageUrl(?string $path): ?string
  {
    if (!$path) {
      return null;
    }

    $url = Storage::url($path);

    // Already absolute (e.g. a cloud disk, or a server where APP_URL is
    // correctly picked up by the filesystem config) — don't double-prefix.
    if (Str::startsWith($url, ['http://', 'https://'])) {
      return $url;
    }

    return rtrim(config('app.url'), '/') . $url;
  }

  /**
   * Post a system-authored message into a conversation (e.g. "X added Y to the group")
   * and broadcast it like a regular message so all connected clients see it live.
   *
   * @param  \App\Models\Conversation  $conversation
   * @param  string  $content
   * @param  array|null  $metadata  Extra structured data clients can key off of
   *                                (e.g. a background-changed event carrying the
   *                                new background_url) without a second request.
   * @return \App\Models\Message
   */
  private function createSystemMessage(Conversation $conversation, string $content, ?array $metadata = null): Message
  {
    $message = Message::create([
      'conversation_id' => $conversation->id,
      'user_id' => null,
      'content' => $content,
      'type' => 'system',
      'metadata' => $metadata,
    ]);

    $conversation->touch();

    $messageData = [
      'id' => $message->id,
      'content' => $message->content,
      'type' => 'system',
      'file_url' => null,
      'is_edited' => false,
      'is_forwarded' => false,
      'is_myself' => false,
      'sender' => [
        'id' => null,
        'username' => 'system',
        'profile_name' => 'Hệ thống',
        'avatar_url' => null,
      ],
      'created_at' => $message->created_at?->toISOString(),
      'created_at_human' => $message->created_at?->diffForHumans(),
      'read_at' => null,
      'metadata' => $metadata,
      'reply_to' => null,
      'reactions' => ['summary' => [], 'total' => 0, 'my_reactions' => []],
    ];

    // Broadcast to everyone (including the acting user's own other sessions) — there is
    // no optimistic client-side render for system messages, unlike user-sent messages.
    broadcast(new MessageSent($conversation->id, $messageData));

    return $message;
  }

  /**
   * Suggest users to mention (@) in a conversation.
   * For group/public conversations: search among participants.
   * For private conversations: return the other participant if query matches.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $conversationId
   * @return \Illuminate\Http\JsonResponse
   */
  public function mentionSuggestions(Request $request, $conversationId)
  {
    $request->validate(['q' => 'required|string|min:1|max:50']);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    $isPublicChat = $conversation->is_public;

    if (!$isPublicChat && !$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $query = $request->input('q');

    $users = $conversation->participants()
      ->where('cyo_auth_accounts.id', '!=', $user->id)
      ->where(function ($q) use ($query) {
        $q->whereRaw('LOWER(username) LIKE ?', ['%' . strtolower($query) . '%'])
          ->orWhereHas('profile', function ($q2) use ($query) {
            $q2->whereRaw('LOWER(profile_name) LIKE ?', ['%' . strtolower($query) . '%']);
          });
      })
      ->with('profile')
      ->limit(10)
      ->get()
      ->map(fn($u) => [
        'id' => $u->id,
        'username' => $u->username,
        'profile_name' => $u->profile->profile_name ?? $u->username,
        'avatar_url' => config('app.url') . "/v1.0/users/{$u->username}/avatar",
      ])->values()->all();

    // Prepend @all only in group/public chats, not in 1-on-1 private conversations
    if ($conversation->type !== 'private' && str_contains('all', strtolower($query))) {
      array_unshift($users, [
        'id' => null,
        'username' => 'all',
        'profile_name' => 'Mention everyone',
        'avatar_url' => config('app.url') . '/images/megaphone.avif',
      ]);
    }

    return response()->json(['suggestions' => $users]);
  }

  /**
   * Search for a user by username to start a new conversation.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function searchUserForChat(Request $request)
  {
    $request->validate([
      'username' => 'required|string|min:1'
    ]);

    $user = Auth::user();
    $searchTerm = $request->username;

    // Find user with exact username match (case-insensitive)
    $foundUser = AuthAccount::where('id', '!=', $user->id)
      ->whereRaw('LOWER(username) = ?', [strtolower($searchTerm)])
      ->with('profile')
      ->first();

    if (!$foundUser) {
      return response()->json(['message' => 'Không tìm thấy người dùng.'], 404);
    }

    // Check if there's already a conversation between these users
    $existingConversation = Conversation::whereHas('participants', function ($query) use ($user) {
      $query->where('user_id', $user->id);
    })->whereHas('participants', function ($query) use ($foundUser) {
      $query->where('user_id', $foundUser->id);
    })->where('type', 'private')->first();

    return response()->json([
      'user' => [
        'id' => $foundUser->id,
        'username' => $foundUser->username,
        'profile_name' => $foundUser->profile->profile_name ?? $foundUser->username,
        'avatar_url' => config('app.url') . "/v1.0/users/{$foundUser->username}/avatar",
      ],
      'existing_conversation_id' => $existingConversation?->id
    ]);
  }

  /**
   * Suggest users by partial username or display name match — used for pickers like
   * "create group" / "add participants", as opposed to mentionSuggestions() which only
   * searches within an existing conversation's participants.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function searchUserSuggestions(Request $request)
  {
    $request->validate([
      'q' => 'required|string|min:1|max:50',
      'exclude_conversation_id' => 'nullable|integer|exists:cyo_conversations,id',
    ]);

    $user = Auth::user();
    $query = $request->input('q');

    // Never suggest yourself, anyone already in the target conversation (if given),
    // or anyone with a mutual block against the current user.
    $excludeIds = array_merge(
      [$user->id],
      UserBlock::where('user_id', $user->id)->pluck('blocked_user_id')->toArray(),
      UserBlock::where('blocked_user_id', $user->id)->pluck('user_id')->toArray()
    );

    if ($request->exclude_conversation_id) {
      $conversation = Conversation::find($request->exclude_conversation_id);
      if ($conversation) {
        $excludeIds = array_merge($excludeIds, $conversation->participants()->pluck('cyo_auth_accounts.id')->toArray());
      }
    }

    $users = AuthAccount::whereNotIn('id', array_unique($excludeIds))
      ->where(function ($q) use ($query) {
        $q->whereRaw('LOWER(username) LIKE ?', ['%' . strtolower($query) . '%'])
          ->orWhereHas('profile', function ($q2) use ($query) {
            $q2->whereRaw('LOWER(profile_name) LIKE ?', ['%' . strtolower($query) . '%']);
          });
      })
      ->with('profile')
      ->limit(10)
      ->get()
      ->map(fn($u) => $this->formatParticipant($u))
      ->values();

    return response()->json(['suggestions' => $users]);
  }

  /**
   * Get messages from the public chat room.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPublicChatMessages(Request $request)
  {
    // Try to get authenticated user (optional) so we can flag their own reaction
    $currentUser = Auth::guard('sanctum')->user() ?? Auth::user();

    // Find the single app-wide public chat
    $conversation = Conversation::where('is_public', true)
      ->first();

    if (!$conversation) {
      return response()->json(['message' => 'Public chat not found'], 404);
    }

    $perPage = 50;
    $totalMessages = $conversation
      ->messages()
      ->where('conversation_id', $conversation->id)
      ->whereNull('deleted_at')
      ->count();
    $lastPage = max(1, ceil($totalMessages / $perPage));
    $page = (int) $request->get('page', 1);
    $page = max(1, min($page, $lastPage));  // Ensure page is within valid range

    // Standard pagination: page 1 = newest messages
    // Order by created_at DESC to get newest first
    $offset = ($page - 1) * $perPage;

    $rawPublicMessages = $conversation
      ->messages()
      ->where('conversation_id', $conversation->id)
      ->whereNull('deleted_at')
      ->with(['user.profile', 'reactions.user.profile', 'replyTo.user.profile'])
      ->orderBy('created_at', 'desc')  // Newest first
      ->skip($offset)
      ->take($perPage)
      ->get()
      ->reverse();  // Reverse to show oldest to newest within the page

    // Batch-resolve @mentions for all public messages in one query (no participant filter)
    $publicMentionUsernames = [];
    foreach ($rawPublicMessages as $msg) {
      if ($msg->content) {
        foreach (NotificationService::parseMentions($msg->content) as $un) {
          $publicMentionUsernames[] = $un;
        }
      }
    }
    $publicMentionUsernames = array_unique($publicMentionUsernames);
    $resolvedPublicUsers = [];
    if (in_array('all', $publicMentionUsernames)) {
      $resolvedPublicUsers['all'] = ['username' => 'all', 'user_id' => null];
    }
    $regularPublicMentions = array_filter($publicMentionUsernames, fn($u) => $u !== 'all');
    if (!empty($regularPublicMentions)) {
      foreach (AuthAccount::whereIn('username', $regularPublicMentions)->select('id', 'username')->get() as $u) {
        $resolvedPublicUsers[strtolower($u->username)] = ['username' => $u->username, 'user_id' => $u->id];
      }
    }

    $messages = $rawPublicMessages->map(function ($message) use ($currentUser, $resolvedPublicUsers) {
        $isGuest = $message->user_id === null;

        $msgMentions = [];
        if ($message->content) {
          foreach (NotificationService::parseMentions($message->content) as $un) {
            $key = strtolower($un);
            if (isset($resolvedPublicUsers[$key])) {
              $msgMentions[] = $resolvedPublicUsers[$key];
            }
          }
        }

        return [
          'id' => $message->id,
          'content' => $message->content,
          'type' => $message->type,
          'file_url' => $message->file_url ? $this->absoluteStorageUrl($message->file_url) : null,
          'is_edited' => $message->is_edited,
          'is_guest' => $isGuest,
          'sender' => $isGuest ? [
            'id' => null,
            'username' => $message->guest_name ?? 'Ẩn danh',
            'profile_name' => $message->guest_name ?? 'Ẩn danh',
            'avatar_url' => null,
          ] : ($message->user ? [
            'id' => $message->user->id,
            'username' => $message->user->username ?? 'Ẩn danh',
            'profile_name' => ($message->user->profile->profile_name ?? null) ?? $message->user->username ?? 'Ẩn danh',
            'avatar_url' => config('app.url') . "/v1.0/users/{$message->user->username}/avatar",
          ] : [
            'id' => null,
            'username' => 'Ẩn danh',
            'profile_name' => 'Ẩn danh',
            'avatar_url' => null,
          ]),
          'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
          'created_at_human' => $message->created_at->diffForHumans(),
          'read_at' => $message->read_at?->toISOString(),
          'reply_to' => $this->formatReplyTo($message->replyTo),
          'reactions' => $this->formatReactions($message, $currentUser->id ?? 0),
          'mentions' => $msgMentions,
        ];
      });

    // Calculate pagination data
    // With DESC order: page 1 = newest, page N = older
    // next_page_url = older messages (page + 1)
    // prev_page_url = newer messages (page - 1)
    $hasMorePages = $page < $lastPage;
    $hasPrevPage = $page > 1;

    // Calculate from/to based on actual message count in this page
    $actualCount = $messages->count();
    $from = $actualCount > 0 ? $offset + 1 : 0;
    $to = $offset + $actualCount;

    $paginationData = [
      'current_page' => $page,
      'data' => $messages->values()->all(),
      'first_page_url' => url('/v1.0/chat/public/messages?page=1'),
      'from' => $from,
      'last_page' => $lastPage,
      'last_page_url' => url("/v1.0/chat/public/messages?page={$lastPage}"),
      'next_page_url' => $hasMorePages ? url('/v1.0/chat/public/messages?page=' . ($page + 1)) : null,
      'path' => url('/v1.0/chat/public/messages'),
      'per_page' => $perPage,
      'prev_page_url' => $hasPrevPage ? url('/v1.0/chat/public/messages?page=' . ($page - 1)) : null,
      'to' => $to,
      'total' => $totalMessages,
    ];

    return response()->json($paginationData);
  }

  /**
   * Send a message to the public chat room.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function sendPublicMessage(Request $request)
  {
    // Try to get authenticated user (optional)
    // Check both Sanctum guard and default guard
    $user = Auth::guard('sanctum')->user() ?? Auth::user();
    $isGuest = !$user;

    // Debug logging
    \Log::info('[PublicChat] sendPublicMessage auth check', [
      'has_bearer_token' => $request->bearerToken() !== null,
      'sanctum_user' => Auth::guard('sanctum')->user() ? Auth::guard('sanctum')->user()->id : null,
      'default_user' => Auth::user() ? Auth::user()->id : null,
      'final_user' => $user ? $user->id : null,
      'is_guest' => $isGuest,
    ]);

    $type = $request->input('type');

    $fileRules = ['nullable', 'file'];
    if ($type === 'video') {
      $fileRules[] = 'max:102400';  // 100MB max for video
      $fileRules[] = 'mimes:mp4,mov,avi,webm';
    } elseif ($type === 'image') {
      $fileRules[] = 'max:10240';  // 10MB max for image
      $fileRules[] = 'mimes:jpeg,png,jpg,gif,webp';
    } else {
      $fileRules[] = 'max:10240';  // 10MB max for other files
    }

    // Validation rules - guest_name is only required for guests
    $rules = [
      'content' => 'required_without:file|string|max:5000',
      'file' => $fileRules,
      'type' => 'required|in:text,image,video,file',
      'reply_to_message_id' => 'nullable|integer|exists:cyo_conversation_messages,id',
    ];

    // Only require guest_name if user is not authenticated (no valid token)
    if ($isGuest) {
      // User is not authenticated, require guest_name
      $rules['guest_name'] = 'required|string|min:1|max:50';
    } else {
      // User is authenticated, guest_name is optional (should be null/not provided)
      $rules['guest_name'] = 'nullable|string|max:50';  // Ignore if provided
    }

    $request->validate($rules);

    // Find the single app-wide public chat
    $conversation = Conversation::where('is_public', true)
      ->first();

    if (!$conversation) {
      return response()->json(['message' => 'Public chat not found'], 404);
    }

    // Validate guest_name format if provided (for guests)
    if ($isGuest) {
      $guestName = trim($request->guest_name);
      if (empty($guestName) || strlen($guestName) < 1 || strlen($guestName) > 50) {
        return response()->json(['message' => 'Tên hiển thị phải từ 1-50 ký tự'], 422);
      }
    }

    // Validate that reply_to message belongs to this public conversation
    if ($request->reply_to_message_id) {
      $replyTarget = Message::find($request->reply_to_message_id);
      if (!$replyTarget || (int) $replyTarget->conversation_id !== (int) $conversation->id) {
        return response()->json(['message' => 'Tin nhắn được trả lời không tồn tại trong cuộc trò chuyện này.'], 422);
      }
    }

    $messageData = [
      'conversation_id' => $conversation->id,
      'user_id' => $user ? $user->id : null,
      'guest_name' => $isGuest ? $request->guest_name : null,
      'content' => $request->content,
      'type' => $request->type,
      'reply_to_message_id' => $request->reply_to_message_id,
    ];

    // Handle file upload
    if ($request->hasFile('file')) {
      $file = $request->file('file');
      $path = $file->store('chat_files', 'public');
      $messageData['file_url'] = $path;
    }

    $message = Message::create($messageData);

    // Update conversation's updated_at timestamp
    $conversation->touch();

    // Add participant to conversation if user is authenticated
    if (!$isGuest && $user) {
      // Check if user is already a participant
      if (!$conversation->hasParticipant($user->id)) {
        // Add user as participant
        $conversation->participants()->attach($user->id);
      }
    }

    // Load relationships for the response
    if (!$isGuest && $message->user_id) {
      // Reload with relationships
      $message = Message::with('user.profile', 'replyTo.user.profile')->find($message->id);
      if (!$message->user) {
        \Log::warning('[PublicChat] sendPublicMessage: User not found after reload', [
          'message_id' => $message->id,
          'user_id' => $message->user_id,
        ]);
      }
    } else {
      // Guest: still load replyTo for the response
      $message->load('replyTo.user.profile');
    }

    // Prepare message data for broadcasting
    $messageData = [
      'id' => $message->id,
      'content' => $message->content,
      'type' => $message->type,
      'file_url' => $message->file_url ? $this->absoluteStorageUrl($message->file_url) : null,
      'is_edited' => $message->is_edited,
      'is_guest' => $isGuest,
      'sender' => $isGuest ? [
        'id' => null,
        'username' => $message->guest_name ?? 'Ẩn danh',
        'profile_name' => $message->guest_name ?? 'Ẩn danh',
        'avatar_url' => null,
      ] : ($message->user ? [
        'id' => $message->user->id,
        'username' => $message->user->username ?? 'Ẩn danh',
        'profile_name' => ($message->user->profile->profile_name ?? null) ?? $message->user->username ?? 'Ẩn danh',
        'avatar_url' => config('app.url') . "/v1.0/users/{$message->user->username}/avatar",
      ] : [
        'id' => null,
        'username' => 'Ẩn danh',
        'profile_name' => 'Ẩn danh',
        'avatar_url' => null,
      ]),
      'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
      'created_at_human' => $message->created_at->diffForHumans(),
      'read_at' => $message->read_at?->toISOString(),
      'reply_to' => $this->formatReplyTo($message->replyTo),
      'reactions' => $this->formatReactions($message, $user->id ?? 0),
    ];

    // Broadcast the message to other participants
    broadcast(new MessageSent($conversation->id, $messageData))->toOthers();

    // Send push notifications to other participants (only for authenticated users)
    if (!$isGuest && $user) {
      $this->sendChatPushNotifications($conversation, $messageData, $user->id);
    }

    // Notify the author of the original message if this is a reply (only authenticated users)
    if (!$isGuest && $user && $message->reply_to_message_id && $message->replyTo) {
      NotificationService::createMessageReplyNotification($message->replyTo, $message, $user->id);
    }

    // Handle @mentions in public message content (only authenticated users)
    $resolvedPublicMentions = [];
    if (!$isGuest && $user && $message->content) {
      // Public chat: any valid user can be mentioned (no participant restriction)
      $resolvedPublicMentions = NotificationService::resolveMentions($message->content);
      foreach ($resolvedPublicMentions as $m) {
        if ($m['user_id'] === null) {
          continue; // @all in public chat — skip individual notifications
        }
        NotificationService::createMentionedInMessageNotification($m['user_id'], $message, $user->id);
      }
    }

    $messageData['mentions'] = $resolvedPublicMentions;

    return response()->json($messageData, 201);
  }

  /**
   * Get participants list for the public chat room.
   * Returns users from cyo_conversation_participants table and guests from messages.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPublicChatParticipants()
  {
    // Find the single app-wide public chat
    $conversation = Conversation::where('is_public', true)
      ->first();

    if (!$conversation) {
      return response()->json(['participants' => [], 'total' => 0]);
    }

    // Get participants from cyo_conversation_participants table
    $now = Carbon::now();
    $users = $conversation
      ->participants()
      ->with('profile')
      ->get()
      ->map(function ($user) use ($now) {
        // Check if user is online (last_activity within 5 minutes)
        $isOnline = false;
        $lastActivityString = null;
        if ($user->last_activity) {
          // last_activity might be a string or Carbon instance
          $lastActivity = $user->last_activity instanceof Carbon
            ? $user->last_activity
            : Carbon::parse($user->last_activity);
          $isOnline = $lastActivity->diffInMinutes($now) <= 5;
          $lastActivityString = $lastActivity->toISOString();
        }

        return [
          'id' => $user->id,
          'username' => $user->username,
          'profile_name' => $user->profile->profile_name ?? $user->username,
          'avatar_url' => config('app.url') . "/v1.0/users/{$user->username}/avatar",
          'is_guest' => false,
          'last_activity' => $lastActivityString,
          'is_online' => $isOnline,
        ];
      });

    // Get unique guest names from messages (guests are not stored in participants table)
    $guestNames = $conversation
      ->messages()
      ->where('conversation_id', $conversation->id)
      ->whereNotNull('guest_name')
      ->distinct()
      ->pluck('guest_name')
      ->unique();

    // Add guest users
    $guests = $guestNames->map(function ($guestName) {
      return [
        'id' => null,
        'username' => $guestName,
        'profile_name' => $guestName,
        'avatar_url' => null,
        'is_guest' => true,
      ];
    });

    // Combine and return
    $participants = $users->concat($guests)->values();

    return response()->json([
      'participants' => $participants,
      'total' => $participants->count(),
    ]);
  }
}
