<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEvent extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'event_type',
        'event_data',
        'actor_type',
        'actor_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the contract that owns the event.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
