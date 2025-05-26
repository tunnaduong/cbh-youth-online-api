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

class ChatController extends Controller
{
    /**
     * Get all conversations for the authenticated user
     */
    public function getConversations()
    {
        $user = Auth::user();

        $conversations = Conversation::whereHas('participants', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
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
                        'avatar_url' => "https://api.chuyenbienhoa.com/v1.0/users/{$participant->username}/avatar",
                    ];
                }),
                'latest_message' => $conversation->latestMessage ? [
                    'content' => $conversation->latestMessage->content,
                    'type' => $conversation->latestMessage->type,
                    'sender' => $conversation->latestMessage->user->username,
                    'created_at' => $conversation->latestMessage->created_at->toISOString(),
                    'created_at_human' => $conversation->latestMessage->created_at->diffForHumans(),
                ] : null,
                'unread_count' => $conversation->unreadMessagesCount($user->id)
            ];
        });

        return response()->json($conversations);
    }

    /**
     * Get messages for a specific conversation
     */
    public function getMessages($conversationId)
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (!$conversation->hasParticipant($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()
            ->with('user.profile')
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->through(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'type' => $message->type,
                    'file_url' => $message->file_url ? Storage::url($message->file_url) : null,
                    'is_edited' => $message->is_edited,
                    'sender' => [
                        'id' => $message->user->id,
                        'username' => $message->user->username,
                        'profile_name' => $message->user->profile->profile_name ?? $message->user->username,
                        'avatar_url' => "https://api.chuyenbienhoa.com/v1.0/users/{$message->user->username}/avatar",
                    ],
                    'created_at' => $message->created_at->toISOString(),
                    'created_at_human' => $message->created_at->diffForHumans(),
                    'read_at' => $message->read_at?->toISOString(),
                ];
            });

        // Mark messages as read
        $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Update last_read_at for the user
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        return response()->json($messages);
    }

    /**
     * Create a new private conversation
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
     * Send a message
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

        // Load relationships for the response
        $message->load('user.profile');

        // Prepare message data for broadcasting
        $messageData = [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type,
            'file_url' => $message->file_url ? Storage::url($message->file_url) : null,
            'sender' => [
                'id' => $message->user->id,
                'username' => $message->user->username,
                'profile_name' => $message->user->profile->profile_name ?? $message->user->username,
                'avatar_url' => "https://api.chuyenbienhoa.com/v1.0/users/{$message->user->username}/avatar",
            ],
            'created_at' => $message->created_at->toISOString(),
            'created_at_human' => $message->created_at->diffForHumans(),
        ];

        // Broadcast the message to other participants
        broadcast(new MessageSent($conversation->id, $messageData))->toOthers();

        return response()->json($messageData, 201);
    }

    /**
     * Mark messages as read
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
     * Delete a message
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
     * Edit a message
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
            'updated_at' => $message->updated_at->toISOString(),
            'updated_at_human' => $message->updated_at->diffForHumans(),
        ]);
    }
}
