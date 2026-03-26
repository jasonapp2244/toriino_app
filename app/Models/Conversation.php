<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'participant_one_id',
        'participant_two_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function participantOne()
    {
        return $this->belongsTo(User::class, 'participant_one_id');
    }

    public function participantTwo()
    {
        return $this->belongsTo(User::class, 'participant_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class, 'conversation_id')->latestOfMany();
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Find or create a conversation between two users.
     * Always stores with the lower ID as participant_one to ensure uniqueness.
     */
    public static function between(int $userA, int $userB): self
    {
        [$one, $two] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        return static::firstOrCreate([
            'participant_one_id' => $one,
            'participant_two_id' => $two,
        ]);
    }

    /**
     * Check if a given user is one of the two participants.
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->participant_one_id === $userId
            || $this->participant_two_id === $userId;
    }

    /**
     * Return the other participant relative to the given user.
     */
    public function otherParticipant(int $myId): ?User
    {
        return $this->participant_one_id === $myId
            ? $this->participantTwo
            : $this->participantOne;
    }

    /**
     * Unread message count for a given user.
     */
    public function unreadCountFor(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }
}
