<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiFlowLog extends Model
{
    use HasUuids;

    protected $table = 'ai_flow_logs';

    public $timestamps = false;

    // Log types
    const TYPE_FLOW_CREATED = 'flow_created';
    const TYPE_FLOW_STARTED = 'flow_started';
    const TYPE_FLOW_PAUSED = 'flow_paused';
    const TYPE_FLOW_RESUMED = 'flow_resumed';
    const TYPE_FLOW_COMPLETED = 'flow_completed';
    const TYPE_FLOW_FAILED = 'flow_failed';
    const TYPE_FLOW_CANCELLED = 'flow_cancelled';
    const TYPE_STEP_STARTED = 'step_started';
    const TYPE_STEP_COMPLETED = 'step_completed';
    const TYPE_STEP_FAILED = 'step_failed';
    const TYPE_STEP_SKIPPED = 'step_skipped';
    const TYPE_STEP_INSERTED = 'step_inserted';
    const TYPE_STEP_DELETED = 'step_deleted';
    const TYPE_USER_INPUT_REQUESTED = 'user_input_requested';
    const TYPE_USER_INPUT_RECEIVED = 'user_input_received';
    const TYPE_AI_DECISION_MADE = 'ai_decision_made';
    const TYPE_CONTEXT_UPDATED = 'context_updated';

    // Actor types
    const ACTOR_SYSTEM = 'system';
    const ACTOR_AI = 'ai';
    const ACTOR_USER = 'user';

    protected $fillable = [
        'flow_id',
        'step_id',
        'log_type',
        'message',
        'data',
        'actor_type',
        'actor_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the flow this log belongs to.
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(AiFlowQueue::class, 'flow_id');
    }

    /**
     * Get the step this log belongs to.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(AiFlowStep::class, 'step_id');
    }

    /**
     * Get the user actor if applicable.
     */
    public function userActor(): ?User
    {
        if ($this->actor_type !== self::ACTOR_USER || !$this->actor_id) {
            return null;
        }

        return User::find($this->actor_id);
    }

    /**
     * Create a flow log entry.
     */
    public static function logFlowEvent(
        AiFlowQueue $flow,
        string $logType,
        ?string $message = null,
        ?AiFlowStep $step = null,
        array $data = [],
        string $actorType = self::ACTOR_SYSTEM,
        ?string $actorId = null
    ): self {
        return self::create([
            'flow_id' => $flow->id,
            'step_id' => $step?->id,
            'log_type' => $logType,
            'message' => $message,
            'data' => $data,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'created_at' => now(),
        ]);
    }

    /**
     * Create a log for flow creation.
     */
    public static function logFlowCreated(AiFlowQueue $flow, ?string $userId = null): self
    {
        return self::logFlowEvent(
            $flow,
            self::TYPE_FLOW_CREATED,
            "Flow created with {$flow->total_steps} steps",
            null,
            ['total_steps' => $flow->total_steps],
            $userId ? self::ACTOR_USER : self::ACTOR_SYSTEM,
            $userId
        );
    }

    /**
     * Create a log for step execution.
     */
    public static function logStepStarted(AiFlowQueue $flow, AiFlowStep $step): self
    {
        return self::logFlowEvent(
            $flow,
            self::TYPE_STEP_STARTED,
            "Started: {$step->title}",
            $step,
            ['skill_slug' => $step->skill_slug, 'step_type' => $step->step_type]
        );
    }

    /**
     * Create a log for step completion.
     */
    public static function logStepCompleted(AiFlowQueue $flow, AiFlowStep $step): self
    {
        return self::logFlowEvent(
            $flow,
            self::TYPE_STEP_COMPLETED,
            "Completed: {$step->title}",
            $step,
            ['duration_ms' => $step->getDurationMs()]
        );
    }

    /**
     * Create a log for step failure.
     */
    public static function logStepFailed(AiFlowQueue $flow, AiFlowStep $step): self
    {
        return self::logFlowEvent(
            $flow,
            self::TYPE_STEP_FAILED,
            "Failed: {$step->title} - {$step->error_message}",
            $step,
            ['error' => $step->error_message]
        );
    }

    /**
     * Create a log for user input request.
     */
    public static function logUserInputRequested(AiFlowQueue $flow, AiFlowStep $step): self
    {
        return self::logFlowEvent(
            $flow,
            self::TYPE_USER_INPUT_REQUESTED,
            "Waiting for user input: {$step->prompt_message}",
            $step,
            ['prompt_type' => $step->prompt_type, 'options_count' => count($step->prompt_options ?? [])]
        );
    }

    /**
     * Create a log for user input received.
     */
    public static function logUserInputReceived(AiFlowQueue $flow, AiFlowStep $step, string $userId): self
    {
        return self::logFlowEvent(
            $flow,
            self::TYPE_USER_INPUT_RECEIVED,
            "User provided input",
            $step,
            ['response' => $step->user_response],
            self::ACTOR_USER,
            $userId
        );
    }

    /**
     * Create a log for flow completion.
     */
    public static function logFlowCompleted(AiFlowQueue $flow): self
    {
        return self::logFlowEvent(
            $flow,
            self::TYPE_FLOW_COMPLETED,
            "Flow completed successfully",
            null,
            [
                'completed_steps' => $flow->completed_steps,
                'total_steps' => $flow->total_steps,
            ]
        );
    }

    /**
     * Create a log for flow failure.
     */
    public static function logFlowFailed(AiFlowQueue $flow): self
    {
        return self::logFlowEvent(
            $flow,
            self::TYPE_FLOW_FAILED,
            "Flow failed: {$flow->last_error}",
            null,
            ['error' => $flow->last_error, 'retry_count' => $flow->retry_count]
        );
    }
}

