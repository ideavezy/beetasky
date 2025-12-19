<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AiSkillExecution extends Model
{
    use HasUuids;

    protected $table = 'ai_skill_executions';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'company_id',
        'skill_id',
        'conversation_id',
        'ai_run_id',
        'input_params',
        'output_result',
        'status',
        'error_message',
        'latency_ms',
        'created_at',
    ];

    protected $casts = [
        'input_params' => 'array',
        'output_result' => 'array',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AiSkillExecution $execution) {
            if (empty($execution->created_at)) {
                $execution->created_at = now();
            }
        });
    }

    /**
     * Get the user that executed this skill.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company context.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the skill that was executed.
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(AiSkill::class, 'skill_id');
    }

    /**
     * Get the conversation context if any.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the AI run that triggered this execution.
     */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by skill.
     */
    public function scopeForSkill(Builder $query, string $skillId): Builder
    {
        return $query->where('skill_id', $skillId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get successful executions.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to get failed executions.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'error');
    }

    /**
     * Check if execution was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if execution failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Create a new execution log entry.
     */
    public static function log(
        string $skillId,
        array $inputParams,
        array $context,
        string $status = 'pending',
        ?array $outputResult = null,
        ?string $errorMessage = null,
        ?int $latencyMs = null
    ): self {
        return self::create([
            'user_id' => $context['user_id'] ?? null,
            'company_id' => $context['company_id'] ?? null,
            'skill_id' => $skillId,
            'conversation_id' => $context['conversation_id'] ?? null,
            'ai_run_id' => $context['ai_run_id'] ?? null,
            'input_params' => $inputParams,
            'output_result' => $outputResult,
            'status' => $status,
            'error_message' => $errorMessage,
            'latency_ms' => $latencyMs,
        ]);
    }
}

