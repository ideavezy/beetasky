<?php

namespace App\Services;

use App\Events\FlowCompleted;
use App\Events\FlowStepCompleted;
use App\Events\FlowUserInputRequired;
use App\Models\AiFlowLog;
use App\Models\AiFlowQueue;
use App\Models\AiFlowStep;
use App\Models\AiRun;
use App\Models\AiSkill;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiFlowService
{
    protected AIService $aiService;
    protected SkillService $skillService;
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct(AIService $aiService, SkillService $skillService)
    {
        $this->aiService = $aiService;
        $this->skillService = $skillService;
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
    }

    /**
     * Check if a user message should trigger flow planning.
     * Returns true for complex multi-step requests.
     */
    public function shouldCreateFlow(string $message): bool
    {
        // Keywords indicating multi-step operations
        $multiStepIndicators = [
            ' and then ',
            ' after that ',
            ' also ',
            ' additionally ',
            ', then ',
            ' followed by ',
            ' next ',
            ' as well as ',
        ];

        $messageLower = strtolower($message);

        foreach ($multiStepIndicators as $indicator) {
            if (str_contains($messageLower, $indicator)) {
                return true;
            }
        }

        // Count action verbs to detect multiple operations
        $actionVerbs = ['create', 'add', 'update', 'delete', 'convert', 'assign', 'move', 'set', 'send', 'generate'];
        $actionCount = 0;

        foreach ($actionVerbs as $verb) {
            if (preg_match('/\b' . $verb . '\b/i', $message)) {
                $actionCount++;
            }
        }

        return $actionCount >= 2;
    }

    /**
     * Plan a new flow from user request.
     * AI analyzes the request and generates step-by-step execution plan.
     */
    public function planFlow(
        string $userRequest,
        string $userId,
        string $companyId,
        ?string $conversationId = null
    ): AiFlowQueue {
        // Get available skills for this user/company WITH full schemas
        $skills = AiSkill::forUser($userId, $companyId)->get();
        
        // Build skill definitions with complete input schemas
        $skillDefinitions = $skills->map(fn($s) => [
            'slug' => $s->slug,
            'name' => $s->name,
            'description' => $s->description,
            'category' => $s->category,
            'input_schema' => $s->input_schema, // Include full schema for accurate param generation
        ])->toArray();
        
        // Create a skill lookup map for validation
        $skillSchemas = $skills->keyBy('slug')->map(fn($s) => $s->input_schema)->toArray();

        // Build planning prompt
        $planningPrompt = $this->buildPlanningPrompt($userRequest, $skillDefinitions);

        $startTime = microtime(true);

        try {
            // Call AI to generate flow plan
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $planningPrompt],
                    ['role' => 'user', 'content' => $userRequest],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object'],
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API error: ' . $response->body());
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $flowPlan = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON in AI response');
            }

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log the AI run
            $aiRun = AiRun::create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'run_type' => 'flow_planning',
                'input_tokens' => $data['usage']['prompt_tokens'] ?? null,
                'output_tokens' => $data['usage']['completion_tokens'] ?? null,
                'latency_ms' => $latencyMs,
                'request_payload' => ['prompt' => substr($planningPrompt, 0, 500), 'user_request' => $userRequest],
                'response_payload' => $flowPlan,
                'status' => 'success',
            ]);

            // Create flow queue
            $flow = AiFlowQueue::create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'title' => $flowPlan['title'] ?? 'AI Flow',
                'original_request' => $userRequest,
                'status' => AiFlowQueue::STATUS_PENDING,
                'flow_context' => [
                    'original_intent' => $userRequest,
                    'available_skills' => array_column($skillDefinitions, 'slug'),
                    'resolved_entities' => [],
                    'created_entities' => [],
                    'step_results' => [],
                ],
                'total_steps' => count($flowPlan['steps'] ?? []),
                'ai_run_id' => $aiRun->id,
                'planning_prompt' => $planningPrompt,
            ]);

            // Create steps with parameter validation
            foreach ($flowPlan['steps'] ?? [] as $index => $stepData) {
                $skillSlug = $stepData['skill'] ?? null;
                $rawParams = $stepData['params'] ?? [];
                $paramMappings = $stepData['mappings'] ?? [];
                
                // Validate and normalize parameters against skill schema
                if ($skillSlug && isset($skillSchemas[$skillSlug])) {
                    $validationResult = $this->validateAndNormalizeParams(
                        $rawParams,
                        $paramMappings,
                        $skillSchemas[$skillSlug]
                    );
                    $rawParams = $validationResult['params'];
                    $paramMappings = $validationResult['mappings'];
                    
                    Log::debug('Step params validated', [
                        'step' => $index,
                        'skill' => $skillSlug,
                        'original_params' => $stepData['params'] ?? [],
                        'normalized_params' => $rawParams,
                        'corrections' => $validationResult['corrections'] ?? [],
                    ]);
                }
                
                AiFlowStep::create([
                    'flow_id' => $flow->id,
                    'position' => $index,
                    'step_type' => $stepData['type'] ?? AiFlowStep::TYPE_TOOL_CALL,
                    'skill_slug' => $skillSlug,
                    'title' => $stepData['title'] ?? "Step " . ($index + 1),
                    'description' => $stepData['description'] ?? null,
                    'input_params' => $rawParams,
                    'param_mappings' => $paramMappings,
                    'condition' => $stepData['condition'] ?? null,
                ]);
            }

            AiFlowLog::logFlowCreated($flow, $userId);

            return $flow->load('steps');
        } catch (\Exception $e) {
            Log::error('Flow planning failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'company_id' => $companyId,
            ]);
            throw $e;
        }
    }

    /**
     * Build the planning prompt for AI.
     */
    protected function buildPlanningPrompt(string $userRequest, array $skills): string
    {
        // Build detailed skill documentation with EXACT parameter names
        $skillsDocs = collect($skills)->map(function($s) {
            $schema = $s['input_schema'] ?? [];
            $properties = $schema['properties'] ?? [];
            $required = $schema['required'] ?? [];
            
            $paramsDoc = '';
            if (!empty($properties)) {
                $paramsList = [];
                foreach ($properties as $paramName => $paramDef) {
                    $isRequired = in_array($paramName, $required) ? '(REQUIRED)' : '(optional)';
                    $type = $paramDef['type'] ?? 'string';
                    $desc = $paramDef['description'] ?? '';
                    $enumVals = isset($paramDef['enum']) ? ' [' . implode(', ', $paramDef['enum']) . ']' : '';
                    $paramsList[] = "    - {$paramName} ({$type}){$enumVals}: {$desc} {$isRequired}";
                }
                $paramsDoc = "\n  Parameters:\n" . implode("\n", $paramsList);
            }
            
            return "- **{$s['slug']}**: {$s['description']}{$paramsDoc}";
        })->join("\n\n");

        return <<<PROMPT
You are a workflow planner for BeetaSky CRM. Your job is to break down user requests into step-by-step executable flows.

## AVAILABLE SKILLS/TOOLS WITH EXACT PARAMETER NAMES

⚠️ CRITICAL: You MUST use the EXACT parameter names listed below. Using incorrect parameter names will cause the flow to fail.

{$skillsDocs}

## STEP TYPES
- tool_call: Execute a skill/tool (most common)
- user_prompt: Pause and ask user for input (when clarification needed)  
- ai_decision: Let AI decide next action based on results
- conditional: Branch based on previous step results

## RESPONSE FORMAT (JSON)
{
  "title": "Short descriptive title for the flow",
  "steps": [
    {
      "type": "tool_call",
      "skill": "skill_slug",
      "title": "Human-readable step title",
      "description": "What this step does",
      "params": {"exact_param_name": "value"},
      "mappings": {"param_from_previous": "{{steps.N.result.data.0.id}}"}
    }
  ]
}

## PARAMETER MAPPING SYNTAX
- Use `{{steps.N.result.data.0.id}}` to get the ID from step N's first result
- Use `{{steps.N.result.data.0.field}}` for other fields from results
- Use `{{context.resolved_entities.task_id}}` for entities resolved by ai_decision steps
- After ai_decision steps, resolved entity IDs are in context.resolved_entities

## HOW TO SEARCH FOR ENTITIES

⚠️ IMPORTANT: Use ONLY the skills listed above. There is NO search_projects or search_contacts skill!

| Entity   | Skill to Use    | Search Parameter |
|----------|-----------------|------------------|
| Tasks    | `search_tasks`  | `search`         |
| Projects | `list_projects` | `search`         |
| Contacts | `list_contacts` | `search`         |
| Deals    | `list_deals`    | No search (use contact_id filter) |

## PLANNING RULES

1. **Search First**: When user mentions an entity by name, use the appropriate skill with `search` parameter
2. **Verify Results**: After search steps, include an `ai_decision` step to handle single/multiple/no results
3. **Use Exact Param Names**: Copy parameter names EXACTLY from the schema above
4. **Chain Dependencies**: Use mappings to pass IDs between steps
5. **Status Values**: Use lowercase for status values (e.g., "done" not "Done", "working" not "Working")
6. **Multiple Items**: Create separate steps for each item (each task, each contact, etc.)

## COMMON PARAMETER MAPPINGS

After search steps, the result structure is:
- `{{steps.N.result.data}}` - array of matches
- `{{steps.N.result.data.0.id}}` - ID of first match
- `{{steps.N.result.data.0.title}}` - title of first match (for tasks)
- `{{steps.N.result.data.0.full_name}}` - name (for contacts)

After ai_decision steps, resolved entities are in:
- `{{context.resolved_entities.task_id}}` - resolved task ID
- `{{context.resolved_entities.contact_id}}` - resolved contact ID  
- `{{context.resolved_entities.project_id}}` - resolved project ID

## EXAMPLE 1 - "Rename project Beemud to New Name and create 2 tasks"

{
  "title": "Rename Project and Create Tasks",
  "steps": [
    {
      "type": "tool_call",
      "skill": "list_projects",
      "title": "Search for Beemud project",
      "params": {"search": "Beemud"}
    },
    {
      "type": "ai_decision", 
      "title": "Verify project found",
      "description": "Check if single match found and resolve project_id"
    },
    {
      "type": "tool_call",
      "skill": "update_project",
      "title": "Rename project to New Name",
      "params": {"name": "New Name"},
      "mappings": {"project_id": "{{context.resolved_entities.project_id}}"}
    },
    {
      "type": "tool_call",
      "skill": "create_task",
      "title": "Create first task",
      "params": {"title": "Task 1"}
    },
    {
      "type": "tool_call",
      "skill": "create_task",
      "title": "Create second task",
      "params": {"title": "Task 2"}
    }
  ]
}

## EXAMPLE 2 - "Find task 'Landing page' and mark it as done"

{
  "title": "Update Task Status",
  "steps": [
    {
      "type": "tool_call",
      "skill": "search_tasks",
      "title": "Search for Landing page task",
      "params": {"search": "Landing page"}
    },
    {
      "type": "ai_decision", 
      "title": "Verify task found",
      "description": "Check if single match found and resolve task_id"
    },
    {
      "type": "tool_call",
      "skill": "update_task",
      "title": "Mark task as done",
      "params": {"status": "done"},
      "mappings": {"task_id": "{{context.resolved_entities.task_id}}"}
    }
  ]
}

Respond with valid JSON only. Use EXACT skill slugs from the list above - no other skills exist!
PROMPT;
    }

    /**
     * Execute the next pending step in a flow.
     */
    public function executeNextStep(AiFlowQueue $flow): ?AiFlowStep
    {
        if (!in_array($flow->status, [AiFlowQueue::STATUS_PENDING, AiFlowQueue::STATUS_RUNNING])) {
            return null;
        }

        // Find next pending step
        $step = $flow->getNextPendingStep();

        if (!$step) {
            $this->completeFlow($flow);
            return null;
        }

        // Update flow status
        $flow->update([
            'status' => AiFlowQueue::STATUS_RUNNING,
            'current_step_id' => $step->id,
            'started_at' => $flow->started_at ?? now(),
        ]);

        // Execute based on step type
        return match ($step->step_type) {
            AiFlowStep::TYPE_TOOL_CALL => $this->executeToolStep($flow, $step),
            AiFlowStep::TYPE_USER_PROMPT => $this->executeUserPromptStep($flow, $step),
            AiFlowStep::TYPE_AI_DECISION => $this->executeAiDecisionStep($flow, $step),
            AiFlowStep::TYPE_CONDITIONAL => $this->executeConditionalStep($flow, $step),
            default => $this->failStep($flow, $step, "Unknown step type: {$step->step_type}"),
        };
    }

    /**
     * Execute a tool call step.
     */
    protected function executeToolStep(AiFlowQueue $flow, AiFlowStep $step): AiFlowStep
    {
        $step->markAsRunning();
        AiFlowLog::logStepStarted($flow, $step);

        try {
            // Resolve parameter mappings from flow context
            $params = $this->resolveParams(
                $step->input_params ?? [],
                $step->param_mappings ?? [],
                $flow->flow_context ?? []
            );

            Log::info('Executing flow step', [
                'flow_id' => $flow->id,
                'step_id' => $step->id,
                'skill' => $step->skill_slug,
                'params' => $params,
            ]);

            // Execute the skill
            $result = $this->skillService->executeSkill(
                $step->skill_slug,
                $params,
                $flow->user_id,
                $flow->company_id,
                $flow->conversation_id
            );

            Log::info('Step execution result', [
                'step_id' => $step->id,
                'success' => $result['success'] ?? false,
                'status' => $result['status'] ?? null,
            ]);

            // Handle result
            if ($result['success'] ?? false) {
                $this->completeStep($flow, $step, $result);
            } else {
                // Check if we need user input (multiple matches case)
                $status = $result['status'] ?? '';

                if ($status === 'multiple_matches') {
                    $this->requestUserSelection($flow, $step, $result);
                } elseif ($status === 'not_found') {
                    $this->requestUserClarification($flow, $step, $result);
                } else {
                    $this->failStep($flow, $step, $result['error'] ?? 'Unknown error');
                }
            }
        } catch (\Exception $e) {
            Log::error('Step execution exception', [
                'step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);
            $this->failStep($flow, $step, $e->getMessage());
        }

        return $step->fresh();
    }

    /**
     * Execute a user prompt step.
     */
    protected function executeUserPromptStep(AiFlowQueue $flow, AiFlowStep $step): AiFlowStep
    {
        $step->markAsRunning();

        // The step already has prompt configuration, just pause for user
        $step->update(['status' => AiFlowStep::STATUS_AWAITING_USER]);

        $flow->update([
            'status' => AiFlowQueue::STATUS_AWAITING_USER,
            'paused_at' => now(),
        ]);

        AiFlowLog::logUserInputRequested($flow, $step);

        // Broadcast to frontend
        broadcast(new FlowUserInputRequired($flow, $step))->toOthers();

        return $step;
    }

    /**
     * Execute an AI decision step.
     */
    protected function executeAiDecisionStep(AiFlowQueue $flow, AiFlowStep $step): AiFlowStep
    {
        $step->markAsRunning();
        AiFlowLog::logStepStarted($flow, $step);

        try {
            $context = $flow->flow_context ?? [];
            $stepResults = $context['step_results'] ?? [];

            // Get the previous step result
            $previousPosition = $step->position - 1;
            $previousResult = $stepResults[$previousPosition] ?? null;

            if (!$previousResult) {
                // No previous result to analyze, just complete
                $step->markAsCompleted(['decision' => 'continue', 'reason' => 'No previous result to analyze']);
                $flow->increment('completed_steps');
                return $step;
            }

            // Check if we have multiple matches
            $matches = $previousResult['data'] ?? $previousResult['matches'] ?? [];
            $matchCount = is_array($matches) ? count($matches) : 0;

            if ($matchCount === 0) {
                // Not found - need user to provide more info
                $this->requestUserClarification($flow, $step, [
                    'message' => 'No results found. Please provide more specific information.',
                    'status' => 'not_found',
                ]);
            } elseif ($matchCount === 1) {
                // Single match - save to context and continue
                $match = $matches[0];
                
                // Extract entity info based on what fields are present
                $resolvedEntities = $this->extractEntityInfo($match);
                
                $this->updateFlowContext($flow, [
                    'resolved_entities' => $resolvedEntities,
                ]);

                $step->markAsCompleted([
                    'decision' => 'single_match',
                    'resolved' => $match,
                    'resolved_entities' => $resolvedEntities,
                ]);
                $flow->increment('completed_steps');

                AiFlowLog::logStepCompleted($flow, $step);
                broadcast(new FlowStepCompleted($flow, $step))->toOthers();
            } else {
                // Multiple matches - need user selection
                $this->requestUserSelection($flow, $step, [
                    'message' => "Found {$matchCount} matches. Please select one:",
                    'matches' => $matches,
                    'status' => 'multiple_matches',
                ]);
            }
        } catch (\Exception $e) {
            $this->failStep($flow, $step, $e->getMessage());
        }

        return $step->fresh();
    }

    /**
     * Extract entity info from a search result match.
     * Handles different entity types (contacts, tasks, projects, deals).
     */
    protected function extractEntityInfo(array $match): array
    {
        $entities = [];
        
        // Task detection
        if (isset($match['task_id']) || (isset($match['title']) && isset($match['status']) && isset($match['topic_id']))) {
            $entities['task_id'] = $match['id'] ?? $match['task_id'] ?? null;
            $entities['task_title'] = $match['title'] ?? null;
            $entities['task_status'] = $match['status'] ?? null;
            $entities['project_id'] = $match['project_id'] ?? null;
            $entities['topic_id'] = $match['topic_id'] ?? null;
        }
        
        // Contact detection
        if (isset($match['contact_id']) || isset($match['full_name']) || isset($match['email'])) {
            $entities['contact_id'] = $match['id'] ?? $match['contact_id'] ?? null;
            $entities['contact_name'] = $match['full_name'] ?? $match['name'] ?? null;
            $entities['contact_email'] = $match['email'] ?? null;
            $entities['contact_type'] = $match['type'] ?? null;
        }
        
        // Project detection
        if (isset($match['project_id']) || (isset($match['name']) && isset($match['members_count']))) {
            $entities['project_id'] = $match['id'] ?? $match['project_id'] ?? null;
            $entities['project_name'] = $match['name'] ?? $match['title'] ?? null;
        }
        
        // Deal detection
        if (isset($match['deal_id']) || isset($match['deal_value'])) {
            $entities['deal_id'] = $match['id'] ?? $match['deal_id'] ?? null;
            $entities['deal_title'] = $match['title'] ?? $match['name'] ?? null;
            $entities['deal_value'] = $match['value'] ?? $match['deal_value'] ?? null;
        }
        
        // Generic ID fallback
        if (empty($entities) && isset($match['id'])) {
            $entities['entity_id'] = $match['id'];
        }
        
        return $entities;
    }

    /**
     * Execute a conditional step.
     */
    protected function executeConditionalStep(AiFlowQueue $flow, AiFlowStep $step): AiFlowStep
    {
        $step->markAsRunning();

        $condition = $step->condition ?? [];
        $context = $flow->flow_context ?? [];

        // Evaluate the condition
        $conditionMet = $this->evaluateCondition($condition, $context);

        if ($conditionMet && $step->on_success_goto !== null) {
            // Skip to specified step
            $this->skipToStep($flow, $step->on_success_goto);
        } elseif (!$conditionMet && $step->on_fail_goto !== null) {
            // Skip to failure step
            $this->skipToStep($flow, $step->on_fail_goto);
        }

        $step->markAsCompleted([
            'condition_met' => $conditionMet,
            'goto' => $conditionMet ? $step->on_success_goto : $step->on_fail_goto,
        ]);

        $flow->increment('completed_steps');

        return $step;
    }

    /**
     * Handle multiple matches - pause for user selection.
     */
    protected function requestUserSelection(AiFlowQueue $flow, AiFlowStep $step, array $result): void
    {
        $matches = $result['matches'] ?? $result['data'] ?? [];
        $options = [];

        foreach ($matches as $index => $match) {
            $label = $match['full_name'] ?? $match['title'] ?? $match['name'] ?? "Option " . ($index + 1);
            $extra = $match['email'] ?? $match['organization'] ?? $match['status'] ?? '';

            $options[] = [
                'value' => (string) ($index + 1),
                'label' => $label . ($extra ? " ({$extra})" : ''),
                'data' => $match,
            ];
        }

        // Add "refine search" option if many matches
        if (count($matches) >= 5) {
            $options[] = [
                'value' => 'refine',
                'label' => 'None of these - let me search differently',
                'data' => ['action' => 'refine_search'],
            ];
        }

        $step->markAsAwaitingUser(
            AiFlowStep::PROMPT_CHOICE,
            $result['message'] ?? "Multiple matches found. Please select one:",
            $options
        );

        $flow->update([
            'status' => AiFlowQueue::STATUS_AWAITING_USER,
            'paused_at' => now(),
        ]);

        AiFlowLog::logUserInputRequested($flow, $step);

        // Broadcast to frontend
        broadcast(new FlowUserInputRequired($flow, $step))->toOthers();
    }

    /**
     * Request user clarification when not found.
     */
    protected function requestUserClarification(AiFlowQueue $flow, AiFlowStep $step, array $result): void
    {
        $step->markAsAwaitingUser(
            AiFlowStep::PROMPT_TEXT,
            $result['message'] ?? "I couldn't find what you're looking for. Please provide more details:",
            null
        );

        $flow->update([
            'status' => AiFlowQueue::STATUS_AWAITING_USER,
            'paused_at' => now(),
        ]);

        AiFlowLog::logUserInputRequested($flow, $step);

        // Broadcast to frontend
        broadcast(new FlowUserInputRequired($flow, $step))->toOthers();
    }

    /**
     * Handle user response to a prompt.
     */
    public function handleUserResponse(AiFlowQueue $flow, AiFlowStep $step, mixed $response): void
    {
        $step->recordUserResponse($response);

        AiFlowLog::logUserInputReceived($flow, $step, $flow->user_id);

        // Handle based on prompt type
        if ($step->prompt_type === AiFlowStep::PROMPT_CHOICE) {
            $selectedValue = $response['value'] ?? $response;

            if ($selectedValue === 'refine') {
                // Insert a new search refinement step
                $this->insertRefinementStep($flow, $step);
            } else {
                // Find the selected option
                $selectedOption = collect($step->prompt_options)
                    ->firstWhere('value', $selectedValue);

                if ($selectedOption) {
                    $selectedData = $selectedOption['data'] ?? [];

                    // Extract entity info using our smart extraction method
                    $resolvedEntities = $this->extractEntityInfo($selectedData);
                    
                    // Update flow context with selected entity
                    $this->updateFlowContext($flow, [
                        'resolved_entities' => $resolvedEntities,
                    ]);
                }

                // Complete this step
                $step->markAsCompleted([
                    'user_selection' => $selectedValue,
                    'selected_data' => $selectedOption['data'] ?? null,
                ]);

                $flow->increment('completed_steps');

                AiFlowLog::logStepCompleted($flow, $step);
                broadcast(new FlowStepCompleted($flow, $step))->toOthers();
            }
        } elseif ($step->prompt_type === AiFlowStep::PROMPT_TEXT) {
            // User provided text input - might need to re-search
            $userText = $response['value'] ?? $response;

            // Update the original step's params with refined search
            $step->update([
                'input_params' => array_merge($step->input_params ?? [], ['search' => $userText]),
                'status' => AiFlowStep::STATUS_PENDING,
            ]);
        } elseif ($step->prompt_type === AiFlowStep::PROMPT_CONFIRM) {
            $confirmed = $response['value'] ?? $response;

            $step->markAsCompleted(['confirmed' => (bool) $confirmed]);
            $flow->increment('completed_steps');

            if (!$confirmed) {
                // User declined - might need to skip remaining steps or cancel
                $flow->update([
                    'status' => AiFlowQueue::STATUS_CANCELLED,
                    'completed_at' => now(),
                ]);
                return;
            }
        }

        // Resume flow execution
        $flow->update([
            'status' => AiFlowQueue::STATUS_RUNNING,
            'paused_at' => null,
        ]);

        $this->executeNextStep($flow);
    }

    /**
     * Insert a refinement step after the current step.
     */
    protected function insertRefinementStep(AiFlowQueue $flow, AiFlowStep $step): void
    {
        $this->insertStep($flow, $step->position, [
            'type' => AiFlowStep::TYPE_USER_PROMPT,
            'title' => 'Refine search',
            'description' => 'Please provide more specific search criteria',
        ]);

        $step->update([
            'status' => AiFlowStep::STATUS_PENDING,
        ]);

        // Resume execution
        $flow->update([
            'status' => AiFlowQueue::STATUS_RUNNING,
            'paused_at' => null,
        ]);

        $this->executeNextStep($flow);
    }

    /**
     * Insert a new step into the flow.
     */
    public function insertStep(AiFlowQueue $flow, int $afterPosition, array $stepData): AiFlowStep
    {
        // Shift all steps after this position
        $flow->steps()
            ->where('position', '>', $afterPosition)
            ->increment('position');

        $step = AiFlowStep::create([
            'flow_id' => $flow->id,
            'position' => $afterPosition + 1,
            'step_type' => $stepData['type'] ?? AiFlowStep::TYPE_TOOL_CALL,
            'skill_slug' => $stepData['skill'] ?? null,
            'title' => $stepData['title'] ?? 'Inserted Step',
            'description' => $stepData['description'] ?? null,
            'input_params' => $stepData['params'] ?? [],
            'param_mappings' => $stepData['mappings'] ?? [],
            'prompt_type' => $stepData['prompt_type'] ?? null,
            'prompt_message' => $stepData['prompt_message'] ?? null,
        ]);

        $flow->increment('total_steps');

        AiFlowLog::logFlowEvent(
            $flow,
            AiFlowLog::TYPE_STEP_INSERTED,
            "Inserted step: {$step->title}",
            $step
        );

        return $step;
    }

    /**
     * Delete a pending step from the flow.
     */
    public function deleteStep(AiFlowQueue $flow, AiFlowStep $step): bool
    {
        if (!$step->isPending()) {
            return false;
        }

        $position = $step->position;
        $step->delete();

        // Shift remaining steps
        $flow->steps()
            ->where('position', '>', $position)
            ->decrement('position');

        $flow->decrement('total_steps');

        AiFlowLog::logFlowEvent(
            $flow,
            AiFlowLog::TYPE_STEP_DELETED,
            "Deleted step at position {$position}"
        );

        return true;
    }

    /**
     * Complete a step and update flow context.
     */
    protected function completeStep(AiFlowQueue $flow, AiFlowStep $step, array $result): void
    {
        $step->markAsCompleted($result);

        // Update flow context with step result
        $stepResults = $flow->flow_context['step_results'] ?? [];
        $stepResults[$step->position] = $result;

        // Extract any created entities
        $createdEntities = $flow->flow_context['created_entities'] ?? [];

        if (isset($result['data']['id'])) {
            $entityType = $this->detectEntityType($step->skill_slug);
            if ($entityType) {
                $createdEntities[$entityType] = $result['data']['id'];
            }
        }

        if (isset($result['data']['project_id'])) {
            $createdEntities['project_id'] = $result['data']['project_id'];
        }

        if (isset($result['data']['task_id'])) {
            $taskIds = $createdEntities['task_ids'] ?? [];
            $taskIds[] = $result['data']['task_id'];
            $createdEntities['task_ids'] = $taskIds;
        }

        $this->updateFlowContext($flow, [
            'step_results' => $stepResults,
            'created_entities' => $createdEntities,
        ]);

        $flow->increment('completed_steps');

        AiFlowLog::logStepCompleted($flow, $step);
        broadcast(new FlowStepCompleted($flow, $step))->toOthers();
    }

    /**
     * Detect entity type from skill slug.
     */
    protected function detectEntityType(string $skillSlug): ?string
    {
        return match (true) {
            str_contains($skillSlug, 'contact') => 'contact_id',
            str_contains($skillSlug, 'project') => 'project_id',
            str_contains($skillSlug, 'task') => 'task_id',
            str_contains($skillSlug, 'deal') => 'deal_id',
            default => null,
        };
    }

    /**
     * Fail a step.
     */
    protected function failStep(AiFlowQueue $flow, AiFlowStep $step, string $errorMessage): AiFlowStep
    {
        $step->markAsFailed($errorMessage);

        AiFlowLog::logStepFailed($flow, $step);

        // Check if we should retry or fail the whole flow
        if ($flow->canRetry()) {
            $flow->update([
                'retry_count' => $flow->retry_count + 1,
                'last_error' => $errorMessage,
            ]);

            // Reset step to pending for retry
            $step->update([
                'status' => AiFlowStep::STATUS_PENDING,
                'error_message' => null,
            ]);
        } else {
            $flow->update([
                'status' => AiFlowQueue::STATUS_FAILED,
                'last_error' => $errorMessage,
                'completed_at' => now(),
            ]);

            AiFlowLog::logFlowFailed($flow);
        }

        return $step;
    }

    /**
     * Complete the entire flow.
     */
    protected function completeFlow(AiFlowQueue $flow): void
    {
        $flow->update([
            'status' => AiFlowQueue::STATUS_COMPLETED,
            'completed_at' => now(),
            'current_step_id' => null,
        ]);

        // Generate suggestions for next actions
        $suggestions = $this->generateCompletionSuggestions($flow);
        $this->updateFlowContext($flow, ['suggestions' => $suggestions]);

        AiFlowLog::logFlowCompleted($flow);

        // Broadcast flow completion
        broadcast(new FlowCompleted($flow))->toOthers();
    }

    /**
     * Cancel a flow.
     */
    public function cancelFlow(AiFlowQueue $flow): void
    {
        $flow->update([
            'status' => AiFlowQueue::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        // Cancel all pending steps
        $flow->steps()->where('status', AiFlowStep::STATUS_PENDING)->update([
            'status' => AiFlowStep::STATUS_CANCELLED,
        ]);

        AiFlowLog::logFlowEvent($flow, AiFlowLog::TYPE_FLOW_CANCELLED, 'Flow cancelled by user');
    }

    /**
     * Generate AI suggestions after flow completion.
     */
    protected function generateCompletionSuggestions(AiFlowQueue $flow): array
    {
        $context = $flow->flow_context ?? [];
        $suggestions = [];

        // Check what was created and suggest follow-up actions
        $createdEntities = $context['created_entities'] ?? [];
        $resolvedEntities = $context['resolved_entities'] ?? [];

        // After converting a lead, suggest creating a deal
        if (isset($resolvedEntities['contact_id'])) {
            $originalRequest = strtolower($flow->original_request);

            if (str_contains($originalRequest, 'convert')) {
                $suggestions[] = [
                    'type' => 'suggestion',
                    'message' => 'Would you like to create a deal for this new customer?',
                    'action' => 'create_deal',
                    'params' => [
                        'contact_id' => $resolvedEntities['contact_id'],
                    ],
                ];
            }
        }

        // After creating a project, suggest inviting team members
        if (isset($createdEntities['project_id'])) {
            $suggestions[] = [
                'type' => 'suggestion',
                'message' => 'Would you like to invite team members to this project?',
                'action' => 'invite_member',
                'params' => [
                    'project_id' => $createdEntities['project_id'],
                ],
            ];
        }

        return $suggestions;
    }

    /**
     * Resolve parameter mappings using flow context.
     */
    protected function resolveParams(array $params, array $mappings, array $context): array
    {
        $resolved = $params;

        foreach ($mappings as $key => $mapping) {
            $value = $this->resolveMapping($mapping, $context);
            if ($value !== null) {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Resolve a single mapping expression.
     */
    protected function resolveMapping(string $mapping, array $context): mixed
    {
        // Pattern: {{path.to.value}}
        if (!preg_match('/^\{\{(.+)\}\}$/', $mapping, $matches)) {
            return $mapping;
        }

        $path = $matches[1];

        // Handle step results: steps.N.result.field
        if (str_starts_with($path, 'steps.')) {
            $parts = explode('.', $path);
            $stepIndex = (int) ($parts[1] ?? 0);
            $resultPath = implode('.', array_slice($parts, 2));

            $stepResults = $context['step_results'] ?? [];
            $stepResult = $stepResults[$stepIndex] ?? [];

            return data_get($stepResult, $resultPath);
        }

        // Handle context values: context.field
        if (str_starts_with($path, 'context.')) {
            $contextPath = substr($path, 8);
            return data_get($context, $contextPath);
        }

        return data_get($context, $path);
    }

    /**
     * Update flow context with new data.
     */
    protected function updateFlowContext(AiFlowQueue $flow, array $data): void
    {
        $context = $flow->flow_context ?? [];

        foreach ($data as $key => $value) {
            if (is_array($value) && isset($context[$key]) && is_array($context[$key])) {
                // Merge arrays
                $context[$key] = array_merge($context[$key], $value);
            } else {
                $context[$key] = $value;
            }
        }

        $flow->update(['flow_context' => $context]);
    }

    /**
     * Evaluate a condition expression.
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        if (empty($condition)) {
            return true;
        }

        $field = $condition['if'] ?? null;
        if (!$field) {
            return true;
        }

        $value = $this->resolveMapping($field, $context);

        if (isset($condition['eq'])) {
            return $value == $condition['eq'];
        }

        if (isset($condition['neq'])) {
            return $value != $condition['neq'];
        }

        if (isset($condition['gt'])) {
            return $value > $condition['gt'];
        }

        if (isset($condition['lt'])) {
            return $value < $condition['lt'];
        }

        if (isset($condition['gte'])) {
            return $value >= $condition['gte'];
        }

        if (isset($condition['lte'])) {
            return $value <= $condition['lte'];
        }

        if (isset($condition['exists'])) {
            return $condition['exists'] ? $value !== null : $value === null;
        }

        return (bool) $value;
    }

    /**
     * Skip to a specific step position.
     */
    protected function skipToStep(AiFlowQueue $flow, int $targetPosition): void
    {
        // Mark all steps between current and target as skipped
        $currentPosition = $flow->currentStep?->position ?? -1;

        $flow->steps()
            ->where('position', '>', $currentPosition)
            ->where('position', '<', $targetPosition)
            ->where('status', AiFlowStep::STATUS_PENDING)
            ->update([
                'status' => AiFlowStep::STATUS_SKIPPED,
                'completed_at' => now(),
            ]);
    }

    /**
     * Get active flows for a user.
     */
    public function getActiveFlows(string $userId, ?string $companyId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = AiFlowQueue::forUser($userId)->active()->with('steps', 'currentStep');

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get a flow with all its data.
     */
    public function getFlow(string $flowId): ?AiFlowQueue
    {
        return AiFlowQueue::with(['steps', 'logs', 'currentStep'])->find($flowId);
    }

    /**
     * Validate and normalize step parameters against skill schema.
     * Auto-corrects common AI mistakes like wrong parameter names.
     */
    protected function validateAndNormalizeParams(array $params, array $mappings, ?array $schema): array
    {
        if (empty($schema) || !isset($schema['properties'])) {
            return ['params' => $params, 'mappings' => $mappings, 'corrections' => []];
        }

        $schemaProps = $schema['properties'] ?? [];
        $requiredParams = $schema['required'] ?? [];
        $corrections = [];
        $normalizedParams = [];
        $normalizedMappings = $mappings;

        // Common parameter name aliases (AI often uses these wrong)
        $paramAliases = [
            // search_tasks, list_contacts
            'title' => 'search',
            'name' => 'search', 
            'query' => 'search',
            'term' => 'search',
            'keyword' => 'search',
            
            // create_comment
            'comment' => 'content',
            'text' => 'content',
            'message' => 'content',
            'body' => 'content',
            'note' => 'content',
            
            // Status normalization
            'Done' => 'done',
            'Working' => 'working',
            'New' => 'new',
            'On Hold' => 'on_hold',
            'In Review' => 'in_review',
            'Question' => 'question',
            'Canceled' => 'canceled',
            
            // Priority normalization
            'Low' => 'low',
            'Medium' => 'medium',
            'High' => 'high',
            'Urgent' => 'urgent',
        ];

        // First pass: check for misnamed parameters and try to correct them
        foreach ($params as $key => $value) {
            // Check if key exists in schema
            if (isset($schemaProps[$key])) {
                // Key is correct, normalize value if it's a string
                $normalizedParams[$key] = $this->normalizeValue($value, $schemaProps[$key], $paramAliases);
                continue;
            }

            // Key doesn't exist - try to find the correct one
            $correctedKey = null;
            
            // Check if it's a known alias
            if (isset($paramAliases[$key]) && isset($schemaProps[$paramAliases[$key]])) {
                $correctedKey = $paramAliases[$key];
            }
            
            // Try case-insensitive match
            if (!$correctedKey) {
                foreach ($schemaProps as $schemaProp => $propDef) {
                    if (strtolower($key) === strtolower($schemaProp)) {
                        $correctedKey = $schemaProp;
                        break;
                    }
                }
            }
            
            // Try partial match (e.g., "task_title" -> "title")
            if (!$correctedKey) {
                foreach ($schemaProps as $schemaProp => $propDef) {
                    if (str_ends_with(strtolower($key), strtolower($schemaProp)) ||
                        str_starts_with(strtolower($key), strtolower($schemaProp))) {
                        $correctedKey = $schemaProp;
                        break;
                    }
                }
            }

            if ($correctedKey) {
                $corrections[] = "Corrected param '{$key}' to '{$correctedKey}'";
                $normalizedParams[$correctedKey] = $this->normalizeValue($value, $schemaProps[$correctedKey], $paramAliases);
            } else {
                // Keep the original if we can't map it
                $normalizedParams[$key] = $value;
                Log::warning("Unknown param '{$key}' not found in schema", [
                    'available_params' => array_keys($schemaProps)
                ]);
            }
        }

        // Also normalize mappings keys
        foreach ($mappings as $key => $value) {
            if (!isset($schemaProps[$key])) {
                // Try to correct the mapping key too
                if (isset($paramAliases[$key]) && isset($schemaProps[$paramAliases[$key]])) {
                    $corrections[] = "Corrected mapping key '{$key}' to '{$paramAliases[$key]}'";
                    $normalizedMappings[$paramAliases[$key]] = $value;
                    unset($normalizedMappings[$key]);
                }
            }
        }

        // Log if we made corrections
        if (!empty($corrections)) {
            Log::info('Parameter corrections applied', [
                'corrections' => $corrections,
                'original_params' => $params,
                'normalized_params' => $normalizedParams,
            ]);
        }

        return [
            'params' => $normalizedParams,
            'mappings' => $normalizedMappings,
            'corrections' => $corrections,
        ];
    }

    /**
     * Normalize a parameter value based on schema definition.
     */
    protected function normalizeValue(mixed $value, array $propDef, array $aliases): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Check if this prop has enum values
        $enumValues = $propDef['enum'] ?? [];
        
        if (!empty($enumValues)) {
            // Try to find the correct enum value
            $lowerValue = strtolower($value);
            
            foreach ($enumValues as $enumVal) {
                if (strtolower($enumVal) === $lowerValue) {
                    return $enumVal;
                }
            }
            
            // Check aliases
            if (isset($aliases[$value])) {
                $aliasedValue = $aliases[$value];
                if (in_array($aliasedValue, $enumValues, true)) {
                    return $aliasedValue;
                }
            }
        }

        // Check general aliases
        if (isset($aliases[$value])) {
            return $aliases[$value];
        }

        return $value;
    }
}

