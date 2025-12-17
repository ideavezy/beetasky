<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'contact_id',
        'created_by',
        'code',
        'name',
        'description',
        'status',
        'start_date',
        'due_date',
        'budget',
        'tags',
        'ai_enabled',
        'ai_settings',
        'settings',
        'import_count',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'ai_settings' => 'array',
            'settings' => 'array',
            'ai_enabled' => 'boolean',
            'start_date' => 'date',
            'due_date' => 'date',
            'budget' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Status options for projects.
     */
    public const STATUSES = [
        'planning' => 'Planning',
        'active' => 'Active',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Get the company that owns the project.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with the project.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user who created the project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the topics for the project.
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('position');
    }

    /**
     * Get the tasks for the project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get all tasks through topics.
     */
    public function tasksThrough(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Topic::class);
    }

    /**
     * Get the members of the project.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'status', 'invitation_token', 'invited_by', 'joined_at', 'is_customer', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Get only active members.
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    /**
     * Get only pending members.
     */
    public function pendingMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'pending');
    }

    /**
     * Get the project member records.
     */
    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Get the invitations for the project.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    /**
     * Get the AI suggestions for the project.
     */
    public function aiSuggestions(): HasMany
    {
        return $this->hasMany(AiTaskSuggestion::class);
    }

    /**
     * Get the smart import jobs for the project.
     */
    public function smartImportJobs(): HasMany
    {
        return $this->hasMany(SmartImportJob::class);
    }

    /**
     * Get the notification events for the project.
     */
    public function notificationEvents(): HasMany
    {
        return $this->hasMany(TaskNotificationEvent::class);
    }

    /**
     * Get the notification settings for the project.
     */
    public function notificationSettings(): HasMany
    {
        return $this->hasMany(ProjectNotificationSetting::class);
    }

    /**
     * Get the activity logs for this project.
     */
    public function activityLogs()
    {
        return $this->morphMany(TaskActivityLog::class, 'loggable');
    }

    /**
     * Get the completion percentage of the project.
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
     * Get completed tasks count.
     */
    public function getCompletedTasksCountAttribute(): int
    {
        return $this->tasks()->where('completed', true)->count();
    }

    /**
     * Get total tasks count.
     */
    public function getTasksCountAttribute(): int
    {
        return $this->tasks()->count();
    }

    /**
     * Get topics count.
     */
    public function getTopicsCountAttribute(): int
    {
        return $this->topics()->count();
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter active projects.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if user is a member of this project.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user is an owner or admin of this project.
     */
    public function hasAdminAccess(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }
}

