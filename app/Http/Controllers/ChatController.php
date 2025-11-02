<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\AuthAccount;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\MessageDeleted;
use Carbon\Carbon;

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

    $conversations = Conversation::whereHas('participants', function ($query) use ($user) {
      $query->where('user_id', $user->id);
    })
      ->orderBy('updated_at', 'desc')
      ->with(['participants.profile', 'latestMessage'])
      ->get()
      ->map(function ($conversation) use ($user) {
        $otherParticipants = $conversation->participants
          ->where('id', '!=', $user->id)
          ->values();

        return [
          'id' => $conversation->id,
          'type' => $conversation->type,
          'name' => $conversation->type === 'group' ? $conversation->name : null,
          'participants' => $otherParticipants->map(function ($participant) {
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
            'sender' => $conversation->latestMessage->user->username,
            'is_myself' => $conversation->latestMessage->user_id === $user->id,
            'created_at' => $conversation->latestMessage->created_at ? $conversation->latestMessage->created_at->toISOString() : null,
            'created_at_human' => $conversation->latestMessage->created_at->diffForHumans(),
          ] : null,
          'unread_count' => $conversation->unreadMessagesCount($user->id)
        ];
      });

    return response()->json($conversations);
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

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $perPage = 50;
    $totalMessages = $conversation->messages()->count();
    $lastPage = ceil($totalMessages / $perPage);
    $page = (int) request()->get('page', 1);

    // Calculate the offset from the end
    $offset = max(0, $totalMessages - ($page * $perPage));
    $limit = min($perPage, $totalMessages - (($page - 1) * $perPage));

    $messages = $conversation->messages()
      ->with('user.profile')
      ->orderBy('created_at', 'asc')
      ->skip($offset)
      ->take($limit)
      ->get()
      ->map(function ($message) use ($user) {
        return [
          'id' => $message->id,
          'content' => $message->content,
          'type' => $message->type,
          'file_url' => $message->file_url ? Storage::url($message->file_url) : null,
          'is_edited' => $message->is_edited,
          'is_myself' => $message->user_id === $user->id,
          'sender' => [
            'id' => $message->user->id,
            'username' => $message->user->username,
            'profile_name' => $message->user->profile->profile_name ?? $message->user->username,
            'avatar_url' => config('app.url') . "/v1.0/users/{$message->user->username}/avatar",
          ],
          'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
          'created_at_human' => $message->created_at->diffForHumans(),
          'read_at' => $message->read_at?->toISOString(),
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
    $conversation->messages()
      ->where('user_id', '!=', $user->id)
      ->whereNull('read_at')
      ->update(['read_at' => now()]);

    // Update last_read_at for the user
    $conversation->participants()
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
    $participantId = $request->participant_id;

    // Check if conversation already exists
    $existingConversation = Conversation::whereHas('participants', function ($query) use ($user) {
      $query->where('user_id', $user->id);
    })->whereHas('participants', function ($query) use ($participantId) {
      $query->where('user_id', $participantId);
    })->where('type', 'private')->first();

    if ($existingConversation) {
      return response()->json(['conversation_id' => $existingConversation->id]);
    }

    // Create new conversation
    $conversation = Conversation::create(['type' => 'private']);
    $conversation->participants()->attach([$user->id, $participantId]);

    return response()->json(['conversation_id' => $conversation->id], 201);
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
    $request->validate([
      'content' => 'required_without:file|string',
      'file' => 'nullable|file|max:10240', // 10MB max
      'type' => 'required|in:text,image,file'
    ]);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $messageData = [
      'conversation_id' => $conversationId,
      'user_id' => $user->id,
      'content' => $request->content,
      'type' => $request->type
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

    // Load relationships for the response
    $message->load('user.profile');

    // Prepare message data for broadcasting
    $messageData = [
      'id' => $message->id,
      'content' => $message->content,
      'type' => $message->type,
      'file_url' => $message->file_url ? Storage::url($message->file_url) : null,
      'is_edited' => $message->is_edited,
      'is_myself' => $message->user_id === $user->id,
      'sender' => [
        'id' => $message->user->id,
        'username' => $message->user->username,
        'profile_name' => $message->user->profile->profile_name ?? $message->user->username,
        'avatar_url' => config('app.url') . "/v1.0/users/{$message->user->username}/avatar",
      ],
      'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
      'created_at_human' => $message->created_at->diffForHumans(),
      'read_at' => $message->read_at?->toISOString(),
    ];

    // Broadcast the message to other participants
    broadcast(new MessageSent($conversation->id, $messageData))->toOthers();

    return response()->json($messageData, 201);
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

    // Mark messages as read
    $messages = $conversation->messages()
      ->where('user_id', '!=', $user->id)
      ->whereNull('read_at')
      ->update(['read_at' => now()]);

    // Update last_read_at for the user
    $conversation->participants()
      ->where('user_id', $user->id)
      ->update(['last_read_at' => now()]);

    // Broadcast message read event
    broadcast(new MessageRead($conversation->id, $user->id))->toOthers();

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

    $message->edit($request->content);

    return response()->json([
      'id' => $message->id,
      'content' => $message->content,
      'is_edited' => true,
      'updated_at' => $message->updated_at ? $message->updated_at->toISOString() : null,
      'updated_at_human' => $message->updated_at->diffForHumans(),
    ]);
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

    // Create new group conversation
    $conversation = Conversation::create([
      'type' => 'group',
      'name' => $request->name,
      'created_by' => $user->id
    ]);

    // Add all participants including the creator
    $participants = array_unique(array_merge([$user->id], $request->participants));
    $conversation->participants()->attach($participants);

    // Load the conversation with participants
    $conversation->load('participants.profile');

    return response()->json([
      'id' => $conversation->id,
      'name' => $conversation->name,
      'type' => 'group',
      'participants' => $conversation->participants->map(function ($participant) {
        return [
          'id' => $participant->id,
          'username' => $participant->username,
          'profile_name' => $participant->profile->profile_name ?? $participant->username,
          'avatar_url' => config('app.url') . "/v1.0/users/{$participant->username}/avatar",
        ];
      })
    ], 201);
  }

  /**
   * Update group conversation details.
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

    // Check if user is in the conversation
    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Check if conversation is a group
    if ($conversation->type !== 'group') {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    $conversation->update([
      'name' => $request->name
    ]);

    return response()->json([
      'id' => $conversation->id,
      'name' => $conversation->name
    ]);
  }

  /**
   * Add participants to a group conversation.
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

    // Check if user is in the conversation
    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Check if conversation is a group
    if ($conversation->type !== 'group') {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    // Add new participants
    $conversation->participants()->attach($request->participants);

    // Load updated participants
    $conversation->load('participants.profile');

    return response()->json([
      'participants' => $conversation->participants->map(function ($participant) {
        return [
          'id' => $participant->id,
          'username' => $participant->username,
          'profile_name' => $participant->profile->profile_name ?? $participant->username,
          'avatar_url' => config('app.url') . "/v1.0/users/{$participant->username}/avatar",
        ];
      })
    ]);
  }

  /**
   * Remove a participant from a group conversation.
   *
   * @param  int  $conversationId
   * @param  int  $userId
   * @return \Illuminate\Http\JsonResponse
   */
  public function removeGroupParticipant($conversationId, $userId)
  {
    $user = Auth::user();
    $conversation = Conversation::findOrFail($conversationId);

    // Check if user is in the conversation
    if (!$conversation->hasParticipant($user->id)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Check if conversation is a group
    if ($conversation->type !== 'group') {
      return response()->json(['message' => 'This is not a group conversation'], 400);
    }

    // Remove participant
    $conversation->participants()->detach($userId);

    return response()->json(['message' => 'Participant removed successfully']);
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
   * Get messages from the public chat room.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPublicChatMessages(Request $request)
  {
    // Find public chat by name and type instead of fixed ID
    $conversation = Conversation::where('name', 'Tán gẫu linh tinh')
      ->where('type', 'group')
      ->first();

    if (!$conversation) {
      return response()->json(['message' => 'Public chat not found'], 404);
    }

    $perPage = 50;
    $totalMessages = $conversation->messages()
      ->where('conversation_id', $conversation->id)
      ->whereNull('deleted_at')
      ->count();
    $lastPage = max(1, ceil($totalMessages / $perPage));
    $page = (int) $request->get('page', 1);
    $page = max(1, min($page, $lastPage)); // Ensure page is within valid range

    // Standard pagination: page 1 = newest messages
    // Order by created_at DESC to get newest first
    $offset = ($page - 1) * $perPage;

    $messages = $conversation->messages()
      ->where('conversation_id', $conversation->id)
      ->whereNull('deleted_at')
      ->with('user.profile')
      ->orderBy('created_at', 'desc') // Newest first
      ->skip($offset)
      ->take($perPage)
      ->get()
      ->reverse() // Reverse to show oldest to newest within the page
      ->map(function ($message) {
        $isGuest = $message->user_id === null;

        return [
          'id' => $message->id,
          'content' => $message->content,
          'type' => $message->type,
          'file_url' => $message->file_url ? Storage::url($message->file_url) : null,
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
      'first_page_url' => url("/v1.0/chat/public/messages?page=1"),
      'from' => $from,
      'last_page' => $lastPage,
      'last_page_url' => url("/v1.0/chat/public/messages?page={$lastPage}"),
      'next_page_url' => $hasMorePages ? url("/v1.0/chat/public/messages?page=" . ($page + 1)) : null,
      'path' => url("/v1.0/chat/public/messages"),
      'per_page' => $perPage,
      'prev_page_url' => $hasPrevPage ? url("/v1.0/chat/public/messages?page=" . ($page - 1)) : null,
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

    // Validation rules - guest_name is only required for guests
    $rules = [
      'content' => 'required_without:file|string|max:5000',
      'file' => 'nullable|file|max:10240', // 10MB max
      'type' => 'required|in:text,image,file',
    ];

    // Only require guest_name if user is not authenticated (no valid token)
    if ($isGuest) {
      // User is not authenticated, require guest_name
      $rules['guest_name'] = 'required|string|min:1|max:50';
    } else {
      // User is authenticated, guest_name is optional (should be null/not provided)
      $rules['guest_name'] = 'nullable|string|max:50'; // Ignore if provided
    }

    $request->validate($rules);

    // Find public chat by name and type instead of fixed ID
    $conversation = Conversation::where('name', 'Tán gẫu linh tinh')
      ->where('type', 'group')
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

    $messageData = [
      'conversation_id' => $conversation->id,
      'user_id' => $user ? $user->id : null,
      'guest_name' => $isGuest ? $request->guest_name : null,
      'content' => $request->content,
      'type' => $request->type
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

    // Load relationships for the response (only if not guest)
    if (!$isGuest && $message->user_id) {
      // Reload with relationships
      $message = Message::with('user.profile')->find($message->id);
      if (!$message->user) {
        \Log::warning('[PublicChat] sendPublicMessage: User not found after reload', [
          'message_id' => $message->id,
          'user_id' => $message->user_id,
        ]);
      }
    }

    // Prepare message data for broadcasting
    $messageData = [
      'id' => $message->id,
      'content' => $message->content,
      'type' => $message->type,
      'file_url' => $message->file_url ? Storage::url($message->file_url) : null,
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
    ];

    // Broadcast the message to other participants
    broadcast(new MessageSent($conversation->id, $messageData))->toOthers();

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
    // Find public chat by name and type instead of fixed ID
    $conversation = Conversation::where('name', 'Tán gẫu linh tinh')
      ->where('type', 'group')
      ->first();

    if (!$conversation) {
      return response()->json(['participants' => [], 'total' => 0]);
    }

    // Get participants from cyo_conversation_participants table
    $now = Carbon::now();
    $users = $conversation->participants()
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
    $guestNames = $conversation->messages()
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
