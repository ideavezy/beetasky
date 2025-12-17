<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Topic extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'project_id',
        'company_id',
        'name',
        'description',
        'position',
        'color',
        'is_locked',
        'locked_by',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the project that owns the topic.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the company that owns the topic.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who locked the topic.
     */
    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Get the tasks for the topic.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('order');
    }

    /**
     * Get users assigned to the topic.
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'topic_assignments')
            ->withPivot(['assigned_by'])
            ->withTimestamps();
    }

    /**
     * Get the topic assignments.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TopicAssignment::class);
    }

    /**
     * Get the AI suggestions for the topic.
     */
    public function aiSuggestions(): HasMany
    {
        return $this->hasMany(AiTaskSuggestion::class);
    }

    /**
     * Get the activity logs for this topic.
     */
    public function activityLogs()
    {
        return $this->morphMany(TaskActivityLog::class, 'loggable');
    }

    /**
     * Get the completion percentage of the topic.
     */
    public function getCompletionPercentageAttribute(): float
    {
        $totalTasks = $this->tasks()->count();
        
        if ($totalTasks === 0) {
            return 0;
        }
        
        $completedTasks = $this->tasks()->where('completed', true)->count();
        
        return round(($completedTasks / $totalTasks) * 100, 1);
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
     * Lock the topic for editing.
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
     * Unlock the topic.
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

