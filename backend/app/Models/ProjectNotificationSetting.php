<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNotificationSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'user_id',
        'company_id',
        'notify_new_tasks',
        'notify_task_assignments',
        'notify_task_status_changes',
        'notify_comments',
        'notify_mentions',
        'email_digest',
        'digest_frequency',
        'additional_settings',
    ];

    protected function casts(): array
    {
        return [
            'notify_new_tasks' => 'boolean',
            'notify_task_assignments' => 'boolean',
            'notify_task_status_changes' => 'boolean',
            'notify_comments' => 'boolean',
            'notify_mentions' => 'boolean',
            'email_digest' => 'boolean',
            'additional_settings' => 'array',
        ];
    }

    /**
     * Digest frequency options.
     */
    public const DIGEST_FREQUENCIES = [
        'instant' => 'Instant',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
    ];

    /**
     * Get the project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get or create notification settings for a user in a project.
     */
    public static function getOrCreate(string $projectId, string $userId, string $companyId): self
    {
        return static::firstOrCreate(
            [
                'project_id' => $projectId,
                'user_id' => $userId,
            ],
            [
                'company_id' => $companyId,
                'notify_new_tasks' => true,
                'notify_task_assignments' => true,
                'notify_task_status_changes' => true,
                'notify_comments' => true,
                'notify_mentions' => true,
                'email_digest' => false,
                'digest_frequency' => 'daily',
            ]
        );
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

