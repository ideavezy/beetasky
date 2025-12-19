<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class AiFlowQueue extends Model
{
    use HasUuids;

    protected $table = 'ai_flow_queues';

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';

    // Flow statuses
    const STATUS_PLANNING = 'planning';
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_AWAITING_USER = 'awaiting_user';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'user_id',
        'conversation_id',
        'title',
        'original_request',
        'status',
        'total_steps',
        'completed_steps',
        'current_step_id',
        'flow_context',
        'ai_run_id',
        'planning_prompt',
        'last_error',
        'retry_count',
        'max_retries',
        'started_at',
        'completed_at',
        'paused_at',
    ];

    protected function casts(): array
    {
        return [
            'flow_context' => 'array',
            'total_steps' => 'integer',
            'completed_steps' => 'integer',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns this flow.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created this flow.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversation associated with this flow.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the AI run that planned this flow.
     */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /**
     * Get the current step being executed.
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(AiFlowStep::class, 'current_step_id');
    }

    /**
     * Get all steps in this flow.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(AiFlowStep::class, 'flow_id')->orderBy('position');
    }

    /**
     * Get all logs for this flow.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AiFlowLog::class, 'flow_id')->orderBy('created_at');
    }

    /**
     * Scope to get flows for a specific company.
     */
    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get flows for a specific user.
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get active flows (running or awaiting user).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_RUNNING,
            self::STATUS_AWAITING_USER,
            self::STATUS_PAUSED,
        ]);
    }

    /**
     * Scope to get completed flows.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Check if the flow is currently running.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if the flow is waiting for user input.
     */
    public function isAwaitingUser(): bool
    {
        return $this->status === self::STATUS_AWAITING_USER;
    }

    /**
     * Check if the flow is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the flow has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the flow can be retried.
     */
    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries;
    }

    /**
     * Get the next pending step.
     */
    public function getNextPendingStep(): ?AiFlowStep
    {
        return $this->steps()
            ->where('status', AiFlowStep::STATUS_PENDING)
            ->orderBy('position')
            ->first();
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_steps === 0) {
            return 0;
        }

        return round(($this->completed_steps / $this->total_steps) * 100, 1);
    }

    /**
     * Update the flow context with new data.
     */
    public function mergeContext(array $data): void
    {
        $context = $this->flow_context ?? [];
        $this->update([
            'flow_context' => array_merge_recursive($context, $data),
        ]);
    }

    /**
     * Get a value from the flow context using dot notation.
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->flow_context, $key, $default);
    }
}

