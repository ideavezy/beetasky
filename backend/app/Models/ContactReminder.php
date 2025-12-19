<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactReminder extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'contact_id',
        'user_id',
        'type',
        'title',
        'description',
        'remind_at',
        'is_completed',
        'completed_at',
        'ai_generated',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
        'ai_generated' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Reminder types.
     */
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_CALL = 'call';
    public const TYPE_EMAIL = 'email';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_TASK = 'task';

    public const TYPES = [
        self::TYPE_FOLLOW_UP,
        self::TYPE_CALL,
        self::TYPE_EMAIL,
        self::TYPE_MEETING,
        self::TYPE_TASK,
    ];

    /**
     * Get the company that owns the reminder.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact this reminder is for.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user who created/owns the reminder.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get pending reminders.
     */
    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope to get overdue reminders.
     */
    public function scopeOverdue($query)
    {
        return $query->pending()->where('remind_at', '<', now());
    }

    /**
     * Scope to get upcoming reminders.
     */
    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->pending()
            ->whereBetween('remind_at', [now(), now()->addDays($days)]);
    }

    /**
     * Mark the reminder as complete.
     */
    public function markComplete(): bool
    {
        $this->is_completed = true;
        $this->completed_at = now();
        return $this->save();
    }

    /**
     * Check if the reminder is overdue.
     */
    public function isOverdue(): bool
    {
        return !$this->is_completed && $this->remind_at->isPast();
    }

    /**
     * Check if the reminder is due soon (within 24 hours).
     */
    public function isDueSoon(): bool
    {
        if ($this->is_completed) {
            return false;
        }
        
        return $this->remind_at->isBetween(now(), now()->addHours(24));
    }
}

