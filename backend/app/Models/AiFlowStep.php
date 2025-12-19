<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class AiFlowStep extends Model
{
    use HasUuids;

    protected $table = 'ai_flow_steps';

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';

    // Step types
    const TYPE_TOOL_CALL = 'tool_call';
    const TYPE_USER_PROMPT = 'user_prompt';
    const TYPE_AI_DECISION = 'ai_decision';
    const TYPE_CONDITIONAL = 'conditional';
    const TYPE_PARALLEL = 'parallel';
    const TYPE_WAIT = 'wait';

    // Step statuses
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_AWAITING_USER = 'awaiting_user';
    const STATUS_CANCELLED = 'cancelled';

    // Prompt types
    const PROMPT_CHOICE = 'choice';
    const PROMPT_TEXT = 'text';
    const PROMPT_CONFIRM = 'confirm';
    const PROMPT_SEARCH = 'search';

    protected $fillable = [
        'flow_id',
        'position',
        'parent_step_id',
        'step_type',
        'skill_slug',
        'title',
        'description',
        'input_params',
        'param_mappings',
        'status',
        'result',
        'error_message',
        'prompt_type',
        'prompt_message',
        'prompt_options',
        'user_response',
        'condition',
        'on_success_goto',
        'on_fail_goto',
        'ai_decision_prompt',
        'ai_decision_result',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'input_params' => 'array',
            'param_mappings' => 'array',
            'result' => 'array',
            'prompt_options' => 'array',
            'user_response' => 'array',
            'condition' => 'array',
            'on_success_goto' => 'integer',
            'on_fail_goto' => 'integer',
            'ai_decision_result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the flow this step belongs to.
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(AiFlowQueue::class, 'flow_id');
    }

    /**
     * Get the parent step (for sub-steps).
     */
    public function parentStep(): BelongsTo
    {
        return $this->belongsTo(AiFlowStep::class, 'parent_step_id');
    }

    /**
     * Get child steps (sub-steps).
     */
    public function childSteps(): HasMany
    {
        return $this->hasMany(AiFlowStep::class, 'parent_step_id')->orderBy('position');
    }

    /**
     * Get logs for this step.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AiFlowLog::class, 'step_id')->orderBy('created_at');
    }

    /**
     * Get the associated skill.
     */
    public function skill(): ?AiSkill
    {
        if (!$this->skill_slug) {
            return null;
        }

        return AiSkill::where('slug', $this->skill_slug)->first();
    }

    /**
     * Scope to get pending steps.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get completed steps.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to get steps awaiting user input.
     */
    public function scopeAwaitingUser(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AWAITING_USER);
    }

    /**
     * Check if this step is a tool call.
     */
    public function isToolCall(): bool
    {
        return $this->step_type === self::TYPE_TOOL_CALL;
    }

    /**
     * Check if this step is a user prompt.
     */
    public function isUserPrompt(): bool
    {
        return $this->step_type === self::TYPE_USER_PROMPT;
    }

    /**
     * Check if this step is an AI decision.
     */
    public function isAiDecision(): bool
    {
        return $this->step_type === self::TYPE_AI_DECISION;
    }

    /**
     * Check if this step is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this step is running.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if this step is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if this step has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if this step is awaiting user input.
     */
    public function isAwaitingUser(): bool
    {
        return $this->status === self::STATUS_AWAITING_USER;
    }

    /**
     * Get execution duration in milliseconds.
     */
    public function getDurationMs(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInMilliseconds($this->started_at);
    }

    /**
     * Mark the step as running.
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the step as completed.
     */
    public function markAsCompleted(array $result = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the step as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the step as awaiting user input.
     */
    public function markAsAwaitingUser(string $promptType, string $message, ?array $options = null): void
    {
        $this->update([
            'status' => self::STATUS_AWAITING_USER,
            'prompt_type' => $promptType,
            'prompt_message' => $message,
            'prompt_options' => $options,
        ]);
    }

    /**
     * Record user response.
     */
    public function recordUserResponse(mixed $response): void
    {
        $this->update([
            'user_response' => is_array($response) ? $response : ['value' => $response],
        ]);
    }
}

