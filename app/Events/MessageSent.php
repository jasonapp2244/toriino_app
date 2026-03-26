<?php

namespace App\Events;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ConversationMessage $message,
    ) {}

    /**
     * The private channel both participants listen to.
     * Channel name: private-conversation.{conversation_id}
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    /**
     * The event name the client receives.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Payload delivered to connected clients.
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'message'         => $this->message->message,
            'type'            => $this->message->type,
            'attachment_url'  => $this->message->attachment_url,
            'is_read'         => $this->message->is_read,
            'sent_at'         => $this->message->created_at->toIso8601String(),
            'sender' => [
                'id'    => $sender->id,
                'name'  => $sender->full_name ?? $sender->name,
                'photo' => $sender->photo_url,
                'role'  => $sender->role,
            ],
        ];
    }
}
