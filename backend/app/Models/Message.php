<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'role',
        'message_type',
        'content',
        'metadata',
        'attachments',
        'read_by',
        'reply_to_message_id',
        'ai_run_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'attachments' => 'array',
            'read_by' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the AI run associated with this message (for AI responses).
     */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }

    /**
     * Get the message this is a reply to.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    /**
     * Get the sender as a User model (only for user sender_type).
     */
    public function getSenderAttribute(): ?User
    {
        if ($this->sender_type === 'user' && $this->sender_id) {
            return User::find($this->sender_id);
        }
        return null;
    }

    /**
     * Get sender info for API responses.
     */
    public function getSenderInfoAttribute(): array
    {
        if ($this->sender_type === 'user' && $this->sender_id) {
            $user = User::find($this->sender_id);
            if ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . ($user->last_name ?? ''),
                    'avatar' => $user->avatar_url,
                    'type' => 'user',
                ];
            }
        }

        if ($this->sender_type === 'ai_agent') {
            return [
                'id' => $this->sender_id,
                'name' => 'AI Assistant',
                'avatar' => null,
                'type' => 'ai_agent',
            ];
        }

        if ($this->sender_type === 'system') {
            return [
                'id' => null,
                'name' => 'System',
                'avatar' => null,
                'type' => 'system',
            ];
        }

        return [
            'id' => $this->sender_id,
            'name' => 'Unknown',
            'avatar' => null,
            'type' => $this->sender_type,
        ];
    }

    /**
     * Check if this message is from an AI agent.
     */
    public function isFromAi(): bool
    {
        return $this->sender_type === 'ai_agent';
    }

    /**
     * Check if this message is from a user.
     */
    public function isFromUser(): bool
    {
        return $this->sender_type === 'user';
    }

    /**
     * Check if this message is from the system.
     */
    public function isFromSystem(): bool
    {
        return $this->sender_type === 'system';
    }

    /**
     * Mark the message as read by a user.
     */
    public function markAsReadBy(string $userId): void
    {
        $readBy = $this->read_by ?? [];
        if (!in_array($userId, $readBy)) {
            $readBy[] = $userId;
            $this->update(['read_by' => $readBy]);
        }
    }

    /**
     * Check if the message has been read by a user.
     */
    public function isReadBy(string $userId): bool
    {
        return in_array($userId, $this->read_by ?? []);
    }

    /**
     * Scope to get messages for a conversation.
     */
    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Scope to get AI messages only.
     */
    public function scopeFromAi($query)
    {
        return $query->where('sender_type', 'ai_agent');
    }

    /**
     * Scope to get user messages only.
     */
    public function scopeFromUsers($query)
    {
        return $query->where('sender_type', 'user');
    }

    /**
     * Convert to array format suitable for API responses.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_type' => $this->sender_type,
            'sender_id' => $this->sender_id,
            'sender' => $this->sender_info,
            'role' => $this->role,
            'message_type' => $this->message_type,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'attachments' => $this->attachments,
            'reply_to_message_id' => $this->reply_to_message_id,
            'ai_run_id' => $this->ai_run_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

