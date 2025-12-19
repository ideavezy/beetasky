<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'project_id',
        'topic_id',
        'company_id',
        'title',
        'description',
        'content',
        'status',
        'priority',
        'assigned_to',
        'due_date',
        'completed',
        'completed_at',
        'completed_by',
        'order',
        'tags',
        'is_locked',
        'locked_by',
        'locked_at',
        'ai_generated',
        'ai_run_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
            'due_date' => 'datetime',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'ai_generated' => 'boolean',
            'order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Status options for tasks.
     */
    public const STATUSES = [
        'new' => 'New',
        'working' => 'Working on',
        'question' => 'Question',
        'on_hold' => 'On Hold',
        'in_review' => 'In Review',
        'done' => 'Done',
        'canceled' => 'Canceled',
    ];

    /**
     * Priority options for tasks.
     */
    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    /**
     * Get the project that owns the task.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the topic that owns the task.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Get the company that owns the task.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user assigned to the task (single assignee - legacy).
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the users assigned to the task (multiple assignees).
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignments')
            ->withPivot(['assigned_by'])
            ->withTimestamps();
    }

    /**
     * Get the task assignments.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get the user who completed the task.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the user who locked the task.
     */
    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Get the comments for the task.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the attachments for the task.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Get the notification events for the task.
     */
    public function notificationEvents(): HasMany
    {
        return $this->hasMany(TaskNotificationEvent::class);
    }

    /**
     * Get the activity logs for this task.
     */
    public function activityLogs()
    {
        return $this->morphMany(TaskActivityLog::class, 'loggable');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by project.
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to filter by topic.
     */
    public function scopeForTopic($query, string $topicId)
    {
        return $query->where('topic_id', $topicId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get completed tasks.
     * Uses whereRaw for PostgreSQL boolean compatibility with emulated prepares.
     */
    public function scopeCompleted($query)
    {
        return $query->whereRaw('completed = true');
    }

    /**
     * Scope to get incomplete tasks.
     * Uses whereRaw for PostgreSQL boolean compatibility with emulated prepares.
     */
    public function scopeIncomplete($query)
    {
        return $query->whereRaw('completed = false');
    }

    /**
     * Scope to get tasks due soon (within 3 days).
     */
    public function scopeDueSoon($query)
    {
        return $query->whereDate('due_date', '>=', now())
            ->whereDate('due_date', '<=', now()->addDays(3))
            ->whereRaw('completed = false');
    }

    /**
     * Scope to get overdue tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->whereDate('due_date', '<', now())
            ->whereRaw('completed = false');
    }

    /**
     * Scope to get high priority tasks.
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    /**
     * Mark the task as completed.
     */
    public function markCompleted(User $user): void
    {
        $this->update([
            'completed' => true,
            'completed_at' => now(),
            'completed_by' => $user->id,
            'status' => 'done',
        ]);
    }

    /**
     * Mark the task as incomplete.
     */
    public function markIncomplete(): void
    {
        $this->update([
            'completed' => false,
            'completed_at' => null,
            'completed_by' => null,
            'status' => 'new',
        ]);
    }

    /**
     * Lock the task for editing.
     */
    public function lock(User $user): void
    {
        $this->update([
            'is_locked' => true,
            'locked_by' => $user->id,
            'locked_at' => now(),
        ]);
    }

    /**
     * Unlock the task.
     */
    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);
    }
}

