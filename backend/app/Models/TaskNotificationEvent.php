<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskNotificationEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'task_id',
        'company_id',
        'recipient_id',
        'actor_id',
        'action',
        'payload',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Action types for notifications.
     */
    public const ACTIONS = [
        'new_task' => 'New Task',
        'assigned_task' => 'Task Assigned',
        'unassigned_task' => 'Task Unassigned',
        'task_status_change' => 'Task Status Changed',
        'task_completed' => 'Task Completed',
        'new_comment' => 'New Comment',
        'mention' => 'Mentioned',
        'due_date_reminder' => 'Due Date Reminder',
        'overdue' => 'Task Overdue',
    ];

    /**
     * Status options for notifications.
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ];

    /**
     * Get the project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the recipient user.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Get the actor user (who triggered the notification).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Mark the notification as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark the notification as failed.
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    /**
     * Scope to get pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by recipient.
     */
    public function scopeForRecipient($query, string $userId)
    {
        return $query->where('recipient_id', $userId);
    }
}

