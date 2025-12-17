<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TaskActivityLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'loggable_type',
        'loggable_id',
        'old_values',
        'new_values',
        'description',
        'ai_run_id',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Action types for activity logs.
     */
    public const ACTIONS = [
        'create' => 'Created',
        'update' => 'Updated',
        'delete' => 'Deleted',
        'complete' => 'Completed',
        'uncomplete' => 'Uncompleted',
        'assign' => 'Assigned',
        'unassign' => 'Unassigned',
        'comment' => 'Commented',
        'status_change' => 'Status Changed',
        'move' => 'Moved',
        'reorder' => 'Reordered',
    ];

    /**
     * Get the user who performed the action.
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
     * Get the loggable model (polymorphic relationship).
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter for a specific loggable.
     */
    public function scopeForLoggable($query, string $type, string $id)
    {
        return $query->where('loggable_type', $type)
            ->where('loggable_id', $id);
    }

    /**
     * Scope to filter for a specific action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Create a new activity log entry.
     */
    public static function log(
        string $action,
        string $description,
        Model $loggable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userId = null,
        ?string $companyId = null
    ): self {
        // If no userId provided but we have an authenticated user, use that
        if ($userId === null && auth()->check()) {
            $userId = auth()->id();
        }

        // Try to get company_id from the loggable model
        if ($companyId === null && method_exists($loggable, 'company_id')) {
            $companyId = $loggable->company_id;
        }

        return static::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'action' => $action,
            'loggable_type' => get_class($loggable),
            'loggable_id' => $loggable->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
        ]);
    }
}

