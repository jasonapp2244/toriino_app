<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| All private channels require Sanctum bearer token auth.
| Mobile clients must POST to /api/v1/broadcasting/auth with:
|   Authorization: Bearer {token}
|   Content-Type: application/json
|   Body: { "channel_name": "private-conversation.{id}", "socket_id": "..." }
|
*/

// Default user channel (Laravel Notifications)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Conversation Channel   →  private-conversation.{conversationId}
|--------------------------------------------------------------------------
| Only the two participants of the conversation may subscribe.
| Supports all role combinations:
|   teacher ↔ student | mentor ↔ student | student ↔ student | mentor ↔ teacher
*/
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    return $conversation && $conversation->hasParticipant($user->id);
});
