<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskComment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'task_id',
        'user_id',
        'company_id',
        'content',
        'ai_generated',
        'ai_run_id',
    ];

    protected function casts(): array
    {
        return [
            'ai_generated' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the task that owns the comment.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who created the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company that owns the comment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the attachments for the comment.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'comment_id');
    }

    /**
     * Get the activity logs for this comment.
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
     * Scope to filter by task.
     */
    public function scopeForTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }
}

