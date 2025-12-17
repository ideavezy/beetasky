<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveSession extends Model
{
    use HasUuids, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'entity_type',
        'entity_id',
        'session_type',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the entity this session is for (polymorphic)
     */
    public function entity(): ?Model
    {
        return match ($this->entity_type) {
            'project' => Project::find($this->entity_id),
            'conversation' => Conversation::find($this->entity_id),
            'contact' => Contact::find($this->entity_id),
            default => null,
        };
    }
}

