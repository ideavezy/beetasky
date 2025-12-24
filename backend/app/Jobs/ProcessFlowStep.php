<?php

namespace App\Jobs;

use App\Models\AiFlowQueue;
use App\Services\AiFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFlowStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $flowId;
    public int $maxAttempts = 3;
    public int $timeout = 120;
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(string $flowId)
    {
        $this->flowId = $flowId;
    }

    /**
     * Execute the job.
     */
    public function handle(AiFlowService $flowService): void
    {
        $flow = AiFlowQueue::find($this->flowId);

        if (!$flow) {
            Log::warning('ProcessFlowStep: Flow not found', ['flow_id' => $this->flowId]);
            return;
        }

        // Check if flow is in a state that allows execution
        if (!in_array($flow->status, [
            AiFlowQueue::STATUS_PENDING,
            AiFlowQueue::STATUS_RUNNING,
        ])) {
            Log::info('ProcessFlowStep: Flow not in executable state', [
                'flow_id' => $this->flowId,
                'status' => $flow->status,
            ]);
            return;
        }

        Log::info('ProcessFlowStep: Executing next step', [
            'flow_id' => $this->flowId,
            'completed' => $flow->completed_steps,
            'total' => $flow->total_steps,
        ]);

        // Execute the next step
        $step = $flowService->executeNextStep($flow);

        if (!$step) {
            // No more steps or flow completed
            Log::info('ProcessFlowStep: Flow execution finished', [
                'flow_id' => $this->flowId,
                'status' => $flow->fresh()->status,
            ]);
            return;
        }

        // Refresh flow status
        $flow->refresh();

        // If flow is still running (step completed successfully), dispatch next step
        if ($flow->status === AiFlowQueue::STATUS_RUNNING) {
            // Check if there are more steps
            if ($flow->completed_steps < $flow->total_steps) {
                // Dispatch next step with a small delay
                self::dispatch($this->flowId)->delay(now()->addSeconds(1));
            } else {
                // All steps completed - dispatch one more time to trigger completeFlow
                Log::info('ProcessFlowStep: All steps done, dispatching final job to complete flow', [
                    'flow_id' => $this->flowId,
                ]);
                self::dispatch($this->flowId)->delay(now()->addMilliseconds(500));
            }
        }

        // If awaiting user input, stop processing (will be resumed by handleUserResponse)
        if ($flow->status === AiFlowQueue::STATUS_AWAITING_USER) {
            Log::info('ProcessFlowStep: Waiting for user input', [
                'flow_id' => $this->flowId,
                'step_id' => $step->id,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessFlowStep job failed', [
            'flow_id' => $this->flowId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update flow status to failed
        $flow = AiFlowQueue::find($this->flowId);
        if ($flow) {
            $flow->update([
                'status' => AiFlowQueue::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['flow', 'flow:' . $this->flowId];
    }
}

