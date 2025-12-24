<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFlowStep;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AIService;
use App\Services\AiFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    protected AIService $aiService;
    protected AiFlowService $flowService;

    // Fixed UUID for the system AI assistant (used as participant_id)
    protected const AI_ASSISTANT_ID = '00000000-0000-0000-0000-000000000001';

    public function __construct(AIService $aiService, AiFlowService $flowService)
    {
        $this->aiService = $aiService;
        $this->flowService = $flowService;
    }

    /**
     * List user's AI chat conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        $conversations = Conversation::forCompany($companyId)
            ->aiChats()
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('participant_type', 'user')
                    ->where('participant_id', $user->id);
            })
            ->orderBy('last_message_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $conversations->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'last_message_preview' => $c->last_message_preview,
                'status' => $c->status,
            ]),
        ]);
    }

    /**
     * Create a new AI chat conversation.
     */
    public function createConversation(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $conversation = Conversation::create([
            'company_id' => $companyId,
            'type' => 'ai_chat',
            'status' => 'open',
            'channel' => 'widget',
            'name' => $validated['name'] ?? 'AI Chat - ' . now()->format('M j, Y'),
        ]);

        // Add user as participant
        $conversation->addParticipant('user', $user->id);

        // Add AI agent as participant (using a fixed UUID for the system AI)
        $conversation->addParticipant('ai_agent', self::AI_ASSISTANT_ID);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'type' => $conversation->type,
                'status' => $conversation->status,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get messages for a conversation.
     */
    public function messages(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $conversation = Conversation::forCompany($companyId)
            ->where('id', $conversationId)
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        }

        // Check if user is a participant
        if (!$conversation->hasParticipant('user', $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages->map(fn($m) => $m->toApiArray()),
        ]);
    }

    /**
     * Stream an AI response via SSE.
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        // Validate request
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
            'conversation_id' => 'nullable|uuid',
            'enable_flows' => 'nullable|boolean',
        ]);

        $userMessage = $validated['message'];
        $conversationId = $validated['conversation_id'] ?? null;
        $enableFlows = $validated['enable_flows'] ?? true;

        // Get or create conversation
        $conversation = null;
        if ($conversationId) {
            $conversation = Conversation::forCompany($companyId)
                ->where('id', $conversationId)
                ->first();
        }

        if (!$conversation) {
            $conversation = Conversation::create([
                'company_id' => $companyId,
                'type' => 'ai_chat',
                'status' => 'open',
                'channel' => 'widget',
                'name' => 'AI Chat - ' . now()->format('M j, Y H:i'),
            ]);
            $conversation->addParticipant('user', $user->id);
            $conversation->addParticipant('ai_agent', self::AI_ASSISTANT_ID);
        }

        // Save user message to database
        $userMessageModel = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'user',
            'sender_id' => $user->id,
            'role' => 'user',
            'message_type' => 'chat',
            'content' => $userMessage,
        ]);

        // Update conversation
        $conversation->updateLastMessage($userMessageModel);

        // Check if this should create a flow (multi-step operation)
        if ($enableFlows && $this->flowService->shouldCreateFlow($userMessage)) {
            return $this->streamWithFlow($request, $user, $companyId, $conversation, $userMessage);
        }

        // Build context
        $context = $this->aiService->buildChatContext($companyId);

        // Get conversation history
        $history = $conversation->getHistoryForAI(10);

        // Remove the just-added user message from history (we'll add it fresh)
        array_pop($history);

        return new StreamedResponse(function () use (
            $conversation,
            $userMessage,
            $history,
            $context,
            $user,
            $companyId
        ) {
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Send start event
            $this->sendSSE([
                'type' => 'start',
                'conversation_id' => $conversation->id,
                'message_id' => null, // Will be set after AI message is created
            ]);

            $aiContent = '';
            $usage = null;

            try {
                // Use streamChatWithSkills to enable function calling
                $usage = $this->aiService->streamChatWithSkills(
                    $userMessage,
                    $history,
                    $context,
                    function ($chunk) {
                        $this->sendSSE([
                            'type' => 'chunk',
                            'content' => $chunk,
                        ]);
                    },
                    $user->id,
                    $companyId,
                    $conversation->id
                );

                $aiContent = $usage['content'];

                // If there were tool calls, notify the frontend
                if (!empty($usage['tool_results'])) {
                    $this->sendSSE([
                        'type' => 'tool_calls',
                        'results' => $usage['tool_results'],
                    ]);
                }

                // Save AI message to database
                $aiMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => 'ai_agent',
                    'sender_id' => self::AI_ASSISTANT_ID,
                    'role' => 'assistant',
                    'message_type' => 'chat',
                    'content' => $aiContent,
                ]);

                // Log AI run
                $aiRun = AiRun::create([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'run_type' => 'chat_reply',
                    'input_tokens' => $usage['input_tokens'] ?? null,
                    'output_tokens' => $usage['output_tokens'] ?? null,
                    'latency_ms' => $usage['latency_ms'] ?? null,
                    'status' => 'success',
                ]);

                // Link AI run to message
                $aiMessage->update(['ai_run_id' => $aiRun->id]);

                // Update conversation
                $conversation->updateLastMessage($aiMessage);

                // Send done event
                $this->sendSSE([
                    'type' => 'done',
                    'message_id' => $aiMessage->id,
                    'conversation_id' => $conversation->id,
                    'usage' => [
                        'input_tokens' => $usage['input_tokens'] ?? 0,
                        'output_tokens' => $usage['output_tokens'] ?? 0,
                    ],
                ]);

            } catch (\Exception $e) {
                Log::error('AI Chat stream failed', [
                    'error' => $e->getMessage(),
                    'conversation_id' => $conversation->id,
                ]);

                // Log failed AI run
                AiRun::create([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'run_type' => 'chat_reply',
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                ]);

                $this->sendSSE([
                    'type' => 'error',
                    'message' => 'Failed to generate response. Please try again.',
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Send an SSE event.
     */
    protected function sendSSE(array $data): void
    {
        echo "data: " . json_encode($data) . "\n\n";
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Stream response with flow-based execution for multi-step operations.
     */
    protected function streamWithFlow(
        Request $request,
        $user,
        string $companyId,
        Conversation $conversation,
        string $userMessage
    ): StreamedResponse {
        return new StreamedResponse(function () use ($user, $companyId, $conversation, $userMessage) {
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Send start event
            $this->sendSSE([
                'type' => 'start',
                'conversation_id' => $conversation->id,
                'is_flow' => true,
            ]);

            try {
                // Plan the flow
                $this->sendSSE([
                    'type' => 'chunk',
                    'content' => "I'll help you with that. Let me break this down into steps...\n\n",
                ]);

                $flow = $this->flowService->planFlow(
                    $userMessage,
                    $user->id,
                    $companyId,
                    $conversation->id
                );

                // Send flow created notification
                $this->sendSSE([
                    'type' => 'flow_created',
                    'flow_id' => $flow->id,
                    'title' => $flow->title,
                    'total_steps' => $flow->total_steps,
                    'steps' => $flow->steps->map(fn($s) => [
                        'id' => $s->id,
                        'position' => $s->position,
                        'title' => $s->title,
                        'type' => $s->step_type,
                        'skill' => $s->skill_slug,
                        'status' => $s->status,
                    ])->toArray(),
                ]);

                // Build a nice message showing the plan
                $stepsList = $flow->steps->map(fn($s, $i) => ($i + 1) . ". " . $s->title)->join("\n");
                $planMessage = "**Flow Created: {$flow->title}**\n\n" .
                    "I've planned the following steps:\n\n{$stepsList}\n\n" .
                    "⏳ **Starting execution now.** This will run in the background. I'll notify you when it needs your input or when it's complete.";

                $this->sendSSE([
                    'type' => 'chunk',
                    'content' => $planMessage,
                ]);

                // Save AI message with the plan
                $aiMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => 'ai_agent',
                    'sender_id' => self::AI_ASSISTANT_ID,
                    'role' => 'assistant',
                    'message_type' => 'chat',
                    'content' => $planMessage,
                    'metadata' => [
                        'flow_id' => $flow->id,
                        'is_flow_plan' => true,
                    ],
                ]);

                $conversation->updateLastMessage($aiMessage);

                // Start processing the flow in background
                ProcessFlowStep::dispatch($flow->id);

                // Send done event
                $this->sendSSE([
                    'type' => 'done',
                    'message_id' => $aiMessage->id,
                    'conversation_id' => $conversation->id,
                    'flow_id' => $flow->id,
                    'is_flow' => true,
                ]);

            } catch (\Exception $e) {
                Log::error('Flow creation failed', [
                    'error' => $e->getMessage(),
                    'conversation_id' => $conversation->id,
                ]);

                $this->sendSSE([
                    'type' => 'error',
                    'message' => 'Failed to create execution flow. Falling back to regular processing.',
                ]);

                // Fall back to regular AI chat
                $this->sendSSE([
                    'type' => 'chunk',
                    'content' => "\n\nLet me try handling this differently...\n\n",
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $conversation = Conversation::forCompany($companyId)
            ->where('id', $conversationId)
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        }

        if (!$conversation->hasParticipant('user', $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted',
        ]);
    }
}

