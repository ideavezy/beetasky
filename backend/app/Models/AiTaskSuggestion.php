<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTaskSuggestion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'topic_id',
        'company_id',
        'ai_run_id',
        'type',
        'content',
        'metadata',
        'applied',
        'applied_at',
        'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'applied' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * Suggestion types.
     */
    public const TYPES = [
        'task' => 'Task Suggestion',
        'summary' => 'Summary',
        'reorganization' => 'Reorganization',
        'priority' => 'Priority Change',
        'deadline' => 'Deadline Suggestion',
        'assignment' => 'Assignment Suggestion',
    ];

    /**
     * Get the project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the topic.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who applied the suggestion.
     */
    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Mark the suggestion as applied.
     */
    public function markAsApplied(User $user): void
    {
        $this->update([
            'applied' => true,
            'applied_at' => now(),
            'applied_by' => $user->id,
        ]);
    }

    /**
     * Scope to get unapplied suggestions.
     */
    public function scopeUnapplied($query)
    {
        return $query->where('applied', false);
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
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}

