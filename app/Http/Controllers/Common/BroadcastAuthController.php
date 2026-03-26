<?php

namespace App\Http\Controllers\Common;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Authorizes private Reverb WebSocket channels for mobile clients.
 *
 * Mobile usage (Flutter / React Native / etc.):
 *   POST /api/v1/broadcasting/auth
 *   Headers:
 *     Authorization: Bearer {sanctum_token}
 *     Content-Type: application/json
 *   Body:
 *     { "socket_id": "...", "channel_name": "private-conversation.42" }
 *
 * Returns a signed auth payload that the client passes to the Reverb server.
 */
class BroadcastAuthController
{
    public function __invoke(Request $request)
    {
        return Broadcast::auth($request);
    }
}
