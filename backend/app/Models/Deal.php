<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'contact_id',
        'title',
        'description',
        'value',
        'currency',
        'stage',
        'probability',
        'expected_close_date',
        'lost_reason',
        'created_by',
        'assigned_to',
        'closed_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'probability' => 'integer',
        'expected_close_date' => 'date',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Deal stages with probability defaults.
     */
    public const STAGES = [
        'qualification' => ['name' => 'Qualification', 'probability' => 10, 'order' => 1],
        'proposal' => ['name' => 'Proposal', 'probability' => 30, 'order' => 2],
        'negotiation' => ['name' => 'Negotiation', 'probability' => 60, 'order' => 3],
        'closed_won' => ['name' => 'Closed Won', 'probability' => 100, 'order' => 4],
        'closed_lost' => ['name' => 'Closed Lost', 'probability' => 0, 'order' => 5],
    ];

    public const STAGE_QUALIFICATION = 'qualification';
    public const STAGE_PROPOSAL = 'proposal';
    public const STAGE_NEGOTIATION = 'negotiation';
    public const STAGE_CLOSED_WON = 'closed_won';
    public const STAGE_CLOSED_LOST = 'closed_lost';

    /**
     * Get the company that owns the deal.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with the deal.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user who created the deal.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user assigned to the deal.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by stage.
     */
    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    /**
     * Scope to get open deals (not closed).
     */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('stage', [self::STAGE_CLOSED_WON, self::STAGE_CLOSED_LOST]);
    }

    /**
     * Scope to get deals closing soon (within days).
     */
    public function scopeClosingSoon($query, int $days = 7)
    {
        return $query->open()
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [now(), now()->addDays($days)]);
    }

    /**
     * Move the deal to a new stage.
     */
    public function moveToStage(string $newStage): bool
    {
        $validStages = array_keys(self::STAGES);

        if (!in_array($newStage, $validStages)) {
            return false;
        }

        $this->stage = $newStage;
        $this->probability = self::STAGES[$newStage]['probability'];

        // Set closed_at for closed stages
        if (in_array($newStage, [self::STAGE_CLOSED_WON, self::STAGE_CLOSED_LOST])) {
            $this->closed_at = now();
        } else {
            $this->closed_at = null;
        }

        return $this->save();
    }

    /**
     * Mark the deal as won.
     */
    public function markAsWon(): bool
    {
        return $this->moveToStage(self::STAGE_CLOSED_WON);
    }

    /**
     * Mark the deal as lost.
     */
    public function markAsLost(?string $reason = null): bool
    {
        if ($reason) {
            $this->lost_reason = $reason;
        }

        return $this->moveToStage(self::STAGE_CLOSED_LOST);
    }

    /**
     * Get weighted value (value * probability).
     */
    public function getWeightedValueAttribute(): float
    {
        if (!$this->value) {
            return 0;
        }

        return round($this->value * ($this->probability / 100), 2);
    }

    /**
     * Check if deal is closed.
     */
    public function isClosed(): bool
    {
        return in_array($this->stage, [self::STAGE_CLOSED_WON, self::STAGE_CLOSED_LOST]);
    }

    /**
     * Check if deal is won.
     */
    public function isWon(): bool
    {
        return $this->stage === self::STAGE_CLOSED_WON;
    }

    /**
     * Check if deal is lost.
     */
    public function isLost(): bool
    {
        return $this->stage === self::STAGE_CLOSED_LOST;
    }
}

