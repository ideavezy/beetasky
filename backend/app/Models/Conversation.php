<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'type',
        'status',
        'channel',
        'contact_id',
        'name',
        'last_message_at',
        'last_message_preview',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the conversation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with this conversation.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get all participants in this conversation.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get all messages in this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get user participants only.
     */
    public function userParticipants(): HasMany
    {
        return $this->participants()->where('participant_type', 'user');
    }

    /**
     * Get AI agent participants only.
     */
    public function aiAgentParticipants(): HasMany
    {
        return $this->participants()->where('participant_type', 'ai_agent');
    }

    /**
     * Check if conversation has an AI agent participant.
     */
    public function hasAiAgent(): bool
    {
        return $this->participants()
            ->where('participant_type', 'ai_agent')
            ->exists();
    }

    /**
     * Check if a user is a participant in this conversation.
     */
    public function hasParticipant(string $type, string $id): bool
    {
        return $this->participants()
            ->where('participant_type', $type)
            ->where('participant_id', $id)
            ->exists();
    }

    /**
     * Add a participant to this conversation.
     */
    public function addParticipant(string $type, string $id): ConversationParticipant
    {
        return $this->participants()->firstOrCreate([
            'participant_type' => $type,
            'participant_id' => $id,
        ], [
            'joined_at' => now(),
        ]);
    }

    /**
     * Update the last message info.
     */
    public function updateLastMessage(Message $message): void
    {
        $this->update([
            'last_message_at' => $message->created_at,
            'last_message_preview' => mb_substr($message->content, 0, 100),
        ]);
    }

    /**
     * Get conversation history for AI context (last N messages).
     */
    public function getHistoryForAI(int $limit = 10): array
    {
        return $this->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (Message $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get AI chat conversations.
     */
    public function scopeAiChats($query)
    {
        return $query->where('type', 'ai_chat');
    }

    /**
     * Scope to get internal team conversations.
     */
    public function scopeInternal($query)
    {
        return $query->where('type', 'internal');
    }

    /**
     * Scope to get open conversations.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}

