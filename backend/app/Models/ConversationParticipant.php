<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id',
        'participant_type',
        'participant_id',
        'last_read_at',
        'joined_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public $timestamps = false;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the participant model (polymorphic)
     */
    public function participant(): ?Model
    {
        return match ($this->participant_type) {
            'user' => User::find($this->participant_id),
            'contact' => Contact::find($this->participant_id),
            'ai_agent' => AIAgent::find($this->participant_id),
            default => null,
        };
    }
}

