<?php

namespace App\Http\Controllers\Common;

use App\Events\MessageSent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One-to-one chat between any two users regardless of role.
 *
 * Supported pairs:
 *   Teacher  ↔ Student
 *   Mentor   ↔ Student
 *   Student  ↔ Student
 *   Mentor   ↔ Teacher
 *   (any other combination)
 *
 * Real-time: subscribe to private-conversation.{id} via Reverb WebSocket.
 * Auth:      POST /api/v1/broadcasting/auth with Bearer token + socket_id.
 */
class ChatController extends Controller
{
    // ─── Conversations ────────────────────────────────────────────

    /**
     * GET /chat/conversations
     * List all my conversations with the last message preview and unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::where('participant_one_id', $user->id)
            ->orWhere('participant_two_id', $user->id)
            ->with([
                'participantOne:id,full_name,name,profile,role',
                'participantTwo:id,full_name,name,profile,role',
                'lastMessage',
            ])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn($c) => $this->formatConversation($c, $user->id));

        return ApiResponse::success($conversations);
    }

    /**
     * POST /chat/conversations
     * Open or retrieve an existing conversation with another user.
     * Body: { "user_id": 5 }
     *
     * Works for any role pairing — student, teacher, mentor.
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $otherId = (int) $request->user_id;
        $myId    = $request->user()->id;

        if ($otherId === $myId) {
            return ApiResponse::error('You cannot start a conversation with yourself.', 422);
        }

        $conversation = Conversation::between($myId, $otherId);

        $conversation->load([
            'participantOne:id,full_name,name,profile,role',
            'participantTwo:id,full_name,name,profile,role',
            'lastMessage',
        ]);

        return ApiResponse::success($this->formatConversation($conversation, $myId));
    }

    /**
     * GET /chat/conversations/{id}
     * Get conversation detail — useful to verify access before subscribing to the WS channel.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $conversation = $this->findConversationForUser($id, $request->user()->id);

        if (!$conversation) {
            return ApiResponse::notFound('Conversation not found.');
        }

        $conversation->load([
            'participantOne:id,full_name,name,profile,role',
            'participantTwo:id,full_name,name,profile,role',
            'lastMessage',
        ]);

        return ApiResponse::success($this->formatConversation($conversation, $request->user()->id));
    }

    // ─── Messages ─────────────────────────────────────────────────

    /**
     * GET /chat/conversations/{id}/messages
     * Paginated message history (50 per page, newest first).
     * Also marks all unread messages from the other party as read.
     */
    public function messages(int $id, Request $request): JsonResponse
    {
        $user         = $request->user();
        $conversation = $this->findConversationForUser($id, $user->id);

        if (!$conversation) {
            return ApiResponse::notFound('Conversation not found.');
        }

        // Mark messages from the other participant as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()
            ->with('sender:id,full_name,name,profile,role')
            ->orderByDesc('created_at')
            ->paginate(50);

        // Reverse the page so the oldest message is first in the response
        $messages->getCollection()->transform(fn($m) => $this->formatMessage($m, $user->id));

        return ApiResponse::success([
            'conversation_id' => $conversation->id,
            'messages'        => $messages,
        ]);
    }

    /**
     * POST /chat/conversations/{id}/messages
     * Send a message in a conversation.
     * Body: { "message": "...", "type": "text|image|file", "attachment_url": "..." }
     *
     * Fires MessageSent event — received by both participants in real time via Reverb.
     */
    public function send(int $id, Request $request): JsonResponse
    {
        $user         = $request->user();
        $conversation = $this->findConversationForUser($id, $user->id);

        if (!$conversation) {
            return ApiResponse::notFound('Conversation not found.');
        }

        $request->validate([
            'message'        => 'required|string|max:5000',
            'type'           => 'sometimes|in:text,image,file',
            'attachment_url' => 'nullable|string|max:2048',
        ]);

        $msg = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'message'         => $request->message,
            'type'            => $request->type ?? 'text',
            'attachment_url'  => $request->attachment_url,
        ]);

        // Update conversation's last_message_at for sorting
        $conversation->update(['last_message_at' => now()]);

        $msg->load('sender');

        // Broadcast to the private channel — real-time delivery via Reverb
        broadcast(new MessageSent($msg))->toOthers();

        return ApiResponse::created($this->formatMessage($msg, $user->id), 'Message sent.');
    }

    /**
     * POST /chat/conversations/{id}/read
     * Mark all unread messages in a conversation as read.
     */
    public function markRead(int $id, Request $request): JsonResponse
    {
        $user         = $request->user();
        $conversation = $this->findConversationForUser($id, $user->id);

        if (!$conversation) {
            return ApiResponse::notFound('Conversation not found.');
        }

        $updated = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return ApiResponse::success(['marked_read' => $updated], 'Messages marked as read.');
    }

    /**
     * DELETE /chat/conversations/{id}
     * Remove conversation from the caller's list (deletes messages too — both sides).
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $conversation = $this->findConversationForUser($id, $request->user()->id);

        if (!$conversation) {
            return ApiResponse::notFound('Conversation not found.');
        }

        $conversation->delete();

        return ApiResponse::success(null, 'Conversation deleted.');
    }

    /**
     * GET /chat/users/search?q=name&role=mentor|teacher|student
     * Find users to start a new chat with.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate([
            'q'    => 'required|string|min:1|max:100',
            'role' => 'nullable|in:mentor,teacher,student',
        ]);

        $query = User::where('id', '!=', $request->user()->id)
            ->where('status', 'active')
            ->where(function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->q . '%')
                  ->orWhere('name', 'like', '%' . $request->q . '%')
                  ->orWhere('email', 'like', '%' . $request->q . '%');
            });

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->with('profile:id,user_id,photo')
            ->select('id', 'full_name', 'name', 'profile', 'role')
            ->limit(20)
            ->get()
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->full_name ?? $u->name,
                'role'  => $u->role,
                'photo' => $u->photo_url,
            ]);

        return ApiResponse::success($users);
    }

    // ─── Private Helpers ──────────────────────────────────────────

    private function findConversationForUser(int $conversationId, int $userId): ?Conversation
    {
        return Conversation::where('id', $conversationId)
            ->where(function ($q) use ($userId) {
                $q->where('participant_one_id', $userId)
                  ->orWhere('participant_two_id', $userId);
            })
            ->first();
    }

    private function formatConversation(Conversation $c, int $myId): array
    {
        $other = $c->otherParticipant($myId);
        $last  = $c->lastMessage;

        return [
            'id'             => $c->id,
            'channel'        => 'private-conversation.' . $c->id,
            'other_user'     => $other ? [
                'id'    => $other->id,
                'name'  => $other->full_name ?? $other->name,
                'role'  => $other->role,
                'photo' => $other->photo_url,
            ] : null,
            'last_message'   => $last ? [
                'message'  => $last->message,
                'type'     => $last->type,
                'sent_at'  => $last->created_at->toIso8601String(),
                'is_mine'  => $last->sender_id === $myId,
            ] : null,
            'unread_count'   => $c->unreadCountFor($myId),
            'last_message_at'=> $c->last_message_at?->toIso8601String(),
        ];
    }

    private function formatMessage(ConversationMessage $m, int $myId): array
    {
        $sender = $m->sender;

        return [
            'id'             => $m->id,
            'conversation_id'=> $m->conversation_id,
            'message'        => $m->message,
            'type'           => $m->type,
            'attachment_url' => $m->attachment_url,
            'is_mine'        => $m->sender_id === $myId,
            'is_read'        => $m->is_read,
            'sent_at'        => $m->created_at->toIso8601String(),
            'sender'         => [
                'id'    => $sender->id,
                'name'  => $sender->full_name ?? $sender->name,
                'role'  => $sender->role,
                'photo' => $sender->photo_url,
            ],
        ];
    }
}
