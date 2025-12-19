<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFlowStep;
use App\Models\AiFlowQueue;
use App\Models\AiFlowStep;
use App\Services\AiFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiFlowController extends Controller
{
    protected AiFlowService $flowService;

    public function __construct(AiFlowService $flowService)
    {
        $this->flowService = $flowService;
    }

    /**
     * List active flows for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $flows = $this->flowService->getActiveFlows($user->id, $companyId);

        return response()->json([
            'success' => true,
            'data' => $flows->map(fn($flow) => $this->formatFlow($flow)),
        ]);
    }

    /**
     * Get a specific flow with all details.
     */
    public function show(Request $request, string $flowId): JsonResponse
    {
        $user = $request->user();

        $flow = $this->flowService->getFlow($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'error' => 'Flow not found',
            ], 404);
        }

        // Ensure user owns this flow
        if ($flow->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatFlow($flow, true),
        ]);
    }

    /**
     * Create a new flow from a user request.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|uuid',
        ]);

        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'error' => 'Company ID required',
            ], 400);
        }

        try {
            $flow = $this->flowService->planFlow(
                $request->input('message'),
                $user->id,
                $companyId,
                $request->input('conversation_id')
            );

            // Start processing the flow in background
            ProcessFlowStep::dispatch($flow->id);

            return response()->json([
                'success' => true,
                'data' => $this->formatFlow($flow, true),
                'message' => 'Flow created and started',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create flow', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create flow: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit user response for a flow step.
     */
    public function respond(Request $request, string $flowId, string $stepId): JsonResponse
    {
        $request->validate([
            'response' => 'required',
        ]);

        $user = $request->user();

        $flow = AiFlowQueue::find($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'error' => 'Flow not found',
            ], 404);
        }

        if ($flow->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $step = AiFlowStep::find($stepId);

        if (!$step || $step->flow_id !== $flow->id) {
            return response()->json([
                'success' => false,
                'error' => 'Step not found',
            ], 404);
        }

        if ($step->status !== AiFlowStep::STATUS_AWAITING_USER) {
            return response()->json([
                'success' => false,
                'error' => 'Step is not awaiting user input',
            ], 400);
        }

        try {
            $this->flowService->handleUserResponse(
                $flow,
                $step,
                $request->input('response')
            );

            // If flow resumed, dispatch next step processing
            $flow->refresh();
            if ($flow->status === AiFlowQueue::STATUS_RUNNING) {
                ProcessFlowStep::dispatch($flow->id)->delay(now()->addSeconds(1));
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatFlow($flow->fresh()->load('steps'), true),
                'message' => 'Response recorded',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle user response', [
                'error' => $e->getMessage(),
                'flow_id' => $flowId,
                'step_id' => $stepId,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process response: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a flow.
     */
    public function cancel(Request $request, string $flowId): JsonResponse
    {
        $user = $request->user();

        $flow = AiFlowQueue::find($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'error' => 'Flow not found',
            ], 404);
        }

        if ($flow->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        if ($flow->isCompleted() || $flow->hasFailed()) {
            return response()->json([
                'success' => false,
                'error' => 'Flow already finished',
            ], 400);
        }

        $this->flowService->cancelFlow($flow);

        return response()->json([
            'success' => true,
            'message' => 'Flow cancelled',
        ]);
    }

    /**
     * Retry a failed flow.
     */
    public function retry(Request $request, string $flowId): JsonResponse
    {
        $user = $request->user();

        $flow = AiFlowQueue::find($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'error' => 'Flow not found',
            ], 404);
        }

        if ($flow->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        if ($flow->status !== AiFlowQueue::STATUS_FAILED) {
            return response()->json([
                'success' => false,
                'error' => 'Only failed flows can be retried',
            ], 400);
        }

        if (!$flow->canRetry()) {
            return response()->json([
                'success' => false,
                'error' => 'Maximum retry attempts reached',
            ], 400);
        }

        // Reset flow to running state
        $flow->update([
            'status' => AiFlowQueue::STATUS_RUNNING,
            'last_error' => null,
            'completed_at' => null,
        ]);

        // Reset the failed step to pending
        $flow->steps()
            ->where('status', AiFlowStep::STATUS_FAILED)
            ->update([
                'status' => AiFlowStep::STATUS_PENDING,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

        // Start processing again
        ProcessFlowStep::dispatch($flow->id);

        return response()->json([
            'success' => true,
            'data' => $this->formatFlow($flow->fresh()->load('steps'), true),
            'message' => 'Flow restarted',
        ]);
    }

    /**
     * Insert a step into a flow.
     */
    public function insertStep(Request $request, string $flowId): JsonResponse
    {
        $request->validate([
            'after_position' => 'required|integer|min:0',
            'step' => 'required|array',
            'step.type' => 'required|string',
            'step.title' => 'required|string',
            'step.skill' => 'nullable|string',
            'step.params' => 'nullable|array',
        ]);

        $user = $request->user();

        $flow = AiFlowQueue::find($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'error' => 'Flow not found',
            ], 404);
        }

        if ($flow->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        if ($flow->isCompleted() || $flow->hasFailed()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot modify finished flow',
            ], 400);
        }

        $step = $this->flowService->insertStep(
            $flow,
            $request->input('after_position'),
            $request->input('step')
        );

        return response()->json([
            'success' => true,
            'data' => $this->formatStep($step),
            'message' => 'Step inserted',
        ]);
    }

    /**
     * Delete a pending step from a flow.
     */
    public function deleteStep(Request $request, string $flowId, string $stepId): JsonResponse
    {
        $user = $request->user();

        $flow = AiFlowQueue::find($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'error' => 'Flow not found',
            ], 404);
        }

        if ($flow->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $step = AiFlowStep::find($stepId);

        if (!$step || $step->flow_id !== $flow->id) {
            return response()->json([
                'success' => false,
                'error' => 'Step not found',
            ], 404);
        }

        $deleted = $this->flowService->deleteStep($flow, $step);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'error' => 'Only pending steps can be deleted',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Step deleted',
        ]);
    }

    /**
     * Get flow logs.
     */
    public function logs(Request $request, string $flowId): JsonResponse
    {
        $user = $request->user();

        $flow = AiFlowQueue::with('logs')->find($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'error' => 'Flow not found',
            ], 404);
        }

        if ($flow->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $flow->logs->map(fn($log) => [
                'id' => $log->id,
                'type' => $log->log_type,
                'message' => $log->message,
                'data' => $log->data,
                'step_id' => $log->step_id,
                'actor_type' => $log->actor_type,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Format a flow for API response.
     */
    protected function formatFlow(AiFlowQueue $flow, bool $includeDetails = false): array
    {
        $data = [
            'id' => $flow->id,
            'title' => $flow->title,
            'status' => $flow->status,
            'original_request' => $flow->original_request,
            'total_steps' => $flow->total_steps,
            'completed_steps' => $flow->completed_steps,
            'progress_percentage' => $flow->getProgressPercentage(),
            'created_at' => $flow->created_at->toIso8601String(),
            'started_at' => $flow->started_at?->toIso8601String(),
            'completed_at' => $flow->completed_at?->toIso8601String(),
        ];

        if ($includeDetails) {
            $data['steps'] = $flow->steps->map(fn($step) => $this->formatStep($step));
            $data['current_step_id'] = $flow->current_step_id;
            $data['flow_context'] = $flow->flow_context;
            $data['last_error'] = $flow->last_error;
        }

        return $data;
    }

    /**
     * Format a step for API response.
     */
    protected function formatStep(AiFlowStep $step): array
    {
        return [
            'id' => $step->id,
            'position' => $step->position,
            'type' => $step->step_type,
            'skill_slug' => $step->skill_slug,
            'title' => $step->title,
            'description' => $step->description,
            'status' => $step->status,
            'result' => $step->result,
            'error_message' => $step->error_message,
            'prompt_type' => $step->prompt_type,
            'prompt_message' => $step->prompt_message,
            'prompt_options' => $step->prompt_options,
            'user_response' => $step->user_response,
            'started_at' => $step->started_at?->toIso8601String(),
            'completed_at' => $step->completed_at?->toIso8601String(),
        ];
    }
}

