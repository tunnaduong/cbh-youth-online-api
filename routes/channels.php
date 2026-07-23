<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Authorize a user to listen to their own private channel.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Authorize a user to listen to a chat conversation presence channel if they are a participant.
// Returning an array (rather than a bool) is required for presence channels - it becomes the
// member's data available to other subscribers via Echo.join(...).here()/joining()/leaving().
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (!$conversation || !$conversation->hasParticipant($user->id)) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'profile_name' => $user->profile->profile_name ?? $user->username,
        'avatar_url' => config('app.url') . "/v1.0/users/{$user->username}/avatar",
    ];
});
