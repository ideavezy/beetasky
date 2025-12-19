<?php

namespace App\Services;

use App\Models\AiRun;
use App\Models\CompanyContact;
use App\Models\ContactReminder;
use App\Models\Deal;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://api.openai.com/v1';
    protected SkillService $skillService;

    public function __construct(SkillService $skillService)
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->skillService = $skillService;
    }

    /**
     * Stream a chat response from OpenAI.
     *
     * @param string $message The user's message
     * @param array $conversationHistory Previous messages in LLM format [['role' => 'user', 'content' => '...'], ...]
     * @param array $context Company context (tasks, projects, contacts)
     * @param callable $onChunk Callback called with each content chunk
     * @return array Token usage and full content ['input_tokens' => int, 'output_tokens' => int, 'content' => string]
     */
    public function streamChat(
        string $message,
        array $conversationHistory,
        array $context,
        callable $onChunk
    ): array {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        $systemPrompt = $this->buildChatSystemPrompt($context);
        
        // Build messages array
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];
        
        // Add conversation history
        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }
        
        // Add current message
        $messages[] = ['role' => 'user', 'content' => $message];

        $startTime = microtime(true);
        $fullContent = '';
        $inputTokens = 0;
        $outputTokens = 0;

        try {
            // Use cURL for streaming since Laravel HTTP doesn't support true streaming
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . '/chat/completions',
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => true,
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ]),
                CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullContent, &$outputTokens, $onChunk) {
                    // Parse SSE data from OpenAI
                    $lines = explode("\n", $data);
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        
                        if (empty($line) || $line === 'data: [DONE]') {
                            continue;
                        }
                        
                        if (str_starts_with($line, 'data: ')) {
                            $json = substr($line, 6);
                            $parsed = json_decode($json, true);
                            
                            if ($parsed && isset($parsed['choices'][0]['delta']['content'])) {
                                $chunk = $parsed['choices'][0]['delta']['content'];
                                $fullContent .= $chunk;
                                $outputTokens++; // Approximate token count
                                $onChunk($chunk);
                            }
                            
                            // Check for usage info (some models include it)
                            if ($parsed && isset($parsed['usage'])) {
                                $outputTokens = $parsed['usage']['completion_tokens'] ?? $outputTokens;
                            }
                        }
                    }
                    
                    return strlen($data);
                },
                CURLOPT_TIMEOUT => 120,
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || $error) {
                throw new \Exception("OpenAI API error: HTTP {$httpCode}, Error: {$error}");
            }

            // Estimate input tokens (rough approximation)
            $inputTokens = (int) (strlen(json_encode($messages)) / 4);

            return [
                'content' => $fullContent,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI streaming failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Stream a chat response with skills (function calling) support.
     *
     * @param string $message The user's message
     * @param array $conversationHistory Previous messages in LLM format
     * @param array $context Company context (tasks, projects, contacts)
     * @param callable $onChunk Callback called with each content chunk
     * @param string $userId User ID for skill lookup
     * @param string|null $companyId Company ID for skill lookup
     * @param string|null $conversationId Conversation ID for logging
     * @return array Token usage, content, and any tool call results
     */
    public function streamChatWithSkills(
        string $message,
        array $conversationHistory,
        array $context,
        callable $onChunk,
        string $userId,
        ?string $companyId = null,
        ?string $conversationId = null
    ): array {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        // Get enabled skills as function definitions
        $tools = $this->skillService->getFunctionDefinitions($userId, $companyId);

        Log::info('Skills loaded for chat', [
            'user_id' => $userId,
            'company_id' => $companyId,
            'tools_count' => count($tools),
            'tool_names' => array_map(fn($t) => $t['function']['name'] ?? 'unknown', $tools),
        ]);

        if (empty($tools)) {
            // No skills enabled, use regular chat
            Log::info('No skills available, falling back to regular chat');
            return $this->streamChat($message, $conversationHistory, $context, $onChunk);
        }

        $systemPrompt = $this->buildChatSystemPromptWithSkills($context, $tools);

        // Build messages array
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $startTime = microtime(true);
        $toolCalls = [];
        $toolResults = [];

        try {
            Log::info('Starting OpenAI chat with skills', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'tools_count' => count($tools),
                'message_length' => strlen($message),
            ]);

            // First call: Check if AI wants to use tools
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->connectTimeout(10)->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                $errorMsg = $errorBody['error']['message'] ?? $response->body();
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'error' => $errorMsg,
                ]);
                throw new \Exception('AI service error: ' . $errorMsg);
            }

            $data = $response->json();
            $firstChoice = $data['choices'][0] ?? null;

            if (!$firstChoice) {
                throw new \Exception('No response from AI service');
            }

            $assistantMessage = $firstChoice['message'] ?? [];
            $finishReason = $firstChoice['finish_reason'] ?? '';

            Log::info('OpenAI initial response', [
                'finish_reason' => $finishReason,
                'has_tool_calls' => !empty($assistantMessage['tool_calls']),
            ]);

            // Check if AI wants to call tools
            if ($finishReason === 'tool_calls' && !empty($assistantMessage['tool_calls'])) {
                $toolCalls = $assistantMessage['tool_calls'];

                // Add assistant's tool call message to history
                $messages[] = $assistantMessage;

                // Execute each tool call with timeout protection
                foreach ($toolCalls as $toolCall) {
                    $functionName = $toolCall['function']['name'] ?? '';
                    $functionArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                    $toolCallId = $toolCall['id'] ?? '';

                    Log::info('Executing skill from chat', [
                        'skill' => $functionName,
                        'args' => $functionArgs,
                        'user_id' => $userId,
                        'company_id' => $companyId,
                    ]);

                    try {
                        // Execute the skill with a timeout wrapper
                        $skillStartTime = microtime(true);
                        $result = $this->skillService->executeSkill(
                            $functionName,
                            $functionArgs,
                            $userId,
                            $companyId,
                            $conversationId
                        );
                        $skillLatency = (int) ((microtime(true) - $skillStartTime) * 1000);

                        Log::info('Skill executed successfully', [
                            'skill' => $functionName,
                            'success' => $result['success'] ?? false,
                            'latency_ms' => $skillLatency,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Skill execution failed', [
                            'skill' => $functionName,
                            'error' => $e->getMessage(),
                        ]);
                        $result = [
                            'success' => false,
                            'error' => 'Skill execution failed: ' . $e->getMessage(),
                        ];
                    }

                    $toolResults[] = [
                        'skill' => $functionName,
                        'result' => $result,
                    ];

                    // Add tool result to messages
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode($result),
                    ];
                }

                // Now stream the final response with tool results
                return $this->streamFinalResponse($messages, $onChunk, $startTime, $toolResults);
            }

            // No tool calls - stream the response directly
            $content = $assistantMessage['content'] ?? '';
            $onChunk($content);

            return [
                'content' => $content,
                'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'tool_calls' => [],
                'tool_results' => [],
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenAI connection timeout', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw new \Exception('Connection timed out. Please try again.');
        } catch (\Exception $e) {
            Log::error('OpenAI chat with skills failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'company_id' => $companyId,
            ]);
            throw $e;
        }
    }

    /**
     * Stream the final response after tool execution.
     */
    protected function streamFinalResponse(
        array $messages,
        callable $onChunk,
        float $startTime,
        array $toolResults
    ): array {
        $fullContent = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $hasError = false;
        $errorMessage = '';

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/chat/completions',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->model,
                'messages' => $messages,
                'stream' => true,
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullContent, &$outputTokens, &$hasError, &$errorMessage, $onChunk) {
                $lines = explode("\n", $data);

                foreach ($lines as $line) {
                    $line = trim($line);

                    if (empty($line) || $line === 'data: [DONE]') {
                        continue;
                    }

                    if (str_starts_with($line, 'data: ')) {
                        $json = substr($line, 6);
                        $parsed = json_decode($json, true);

                        // Check for error in response
                        if ($parsed && isset($parsed['error'])) {
                            $hasError = true;
                            $errorMessage = $parsed['error']['message'] ?? 'Unknown error';
                            Log::error('OpenAI streaming error', ['error' => $parsed['error']]);
                            continue;
                        }

                        if ($parsed && isset($parsed['choices'][0]['delta']['content'])) {
                            $chunk = $parsed['choices'][0]['delta']['content'];
                            $fullContent .= $chunk;
                            $outputTokens++;
                            $onChunk($chunk);
                        }

                        if ($parsed && isset($parsed['usage'])) {
                            $outputTokens = $parsed['usage']['completion_tokens'] ?? $outputTokens;
                        }
                    }
                }

                return strlen($data);
            },
            CURLOPT_TIMEOUT => 60, // 60 second timeout
            CURLOPT_CONNECTTIMEOUT => 10, // 10 second connection timeout
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        // Log the result for debugging
        Log::info('OpenAI streaming completed', [
            'http_code' => $httpCode,
            'curl_error' => $error,
            'curl_errno' => $errno,
            'content_length' => strlen($fullContent),
            'has_error' => $hasError,
            'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
        ]);

        // Handle timeout
        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new \Exception('Request timed out. Please try again.');
        }

        // Handle other curl errors
        if ($errno !== 0) {
            throw new \Exception("Connection error: {$error}");
        }

        // Handle HTTP errors
        if ($httpCode !== 200 && $httpCode !== 0) {
            throw new \Exception("OpenAI API error: HTTP {$httpCode}");
        }

        // Handle streaming errors
        if ($hasError) {
            throw new \Exception("OpenAI error: {$errorMessage}");
        }

        $inputTokens = (int) (strlen(json_encode($messages)) / 4);

        return [
            'content' => $fullContent,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
            'tool_results' => $toolResults,
        ];
    }

    /**
     * Build the system prompt for chat with skills.
     */
    protected function buildChatSystemPromptWithSkills(array $context, array $tools): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);
        $skillsList = collect($tools)->map(function ($tool) {
            $name = $tool['function']['name'] ?? 'unknown';
            $desc = $tool['function']['description'] ?? '';
            return "- **{$name}**: {$desc}";
        })->join("\n");

        return <<<PROMPT
You are Beeta, an AI assistant for BeetaSky - a project management and CRM platform. You help users manage their tasks, projects, contacts, deals, and leads efficiently.

PERSONALITY:
- Friendly, professional, and helpful
- Concise and action-oriented
- Execute actions immediately without asking unnecessary questions
- Use clear formatting for lists and data

AVAILABLE SKILLS:
{$skillsList}

CRITICAL RULES FOR USING SKILLS:
1. **DO NOT ASK FOR OPTIONAL FIELDS** - Only ask for truly required information
2. Use default values for optional fields (priority=medium, status=new, relation_type=lead, etc.)
3. If user says "create a task called X" - just create it with title=X and defaults
4. Look in the CONTEXT for topic_id, project_id, contact_id if not provided by user
5. Execute the skill IMMEDIATELY once you have the required info
6. After executing, briefly confirm what was done

PROJECT MANAGEMENT SKILLS:
- create_task: Create new tasks
- update_task: Update tasks - **IMPORTANT**: Use the 'search' parameter to find tasks by name when you don't have the task_id. Example: {"search": "landing page", "status": "working"}
- search_tasks: Search for tasks by title/description
- list_tasks: List tasks in a topic/project
- assign_task: Assign users to tasks
- create_project, list_projects: Manage projects
- create_topic, list_topics: Organize tasks into topics/sections

TASK SEARCH RULES (CRITICAL):
- When user mentions a task by name (e.g., "move landing page task to in progress"), use the 'search' parameter in update_task
- NEVER make up or guess a task_id - always use search to find it first
- The search parameter accepts partial matches, so "landing page" will find "Create a landing page for facebook"

CRM SKILLS (Contacts & Leads):
- create_contact: Add new leads/contacts (default: relation_type=lead)
- list_contacts: Search and filter contacts
- get_contact: Get detailed contact info
- update_contact: Update contact fields
- convert_lead: Convert lead to customer
- add_contact_note: Log activities/interactions
- score_lead: Calculate lead scores (or score_all=true for all leads)

DEAL SKILLS (Sales Pipeline):
- create_deal: Create sales opportunity (stages: qualification, proposal, negotiation)
- update_deal: Move deals through pipeline, mark won/lost
- list_deals: View pipeline with filters

REMINDER SKILLS:
- create_reminder: Set follow-up reminders (supports natural dates like "tomorrow", "next Tuesday")
- list_reminders: View upcoming reminders

CRM CONTEXT AWARENESS:
- Check crm.lead_insights.stale_leads_count for leads needing follow-up (14+ days inactive)
- Check crm.lead_insights.hot_leads for high-scoring leads to prioritize
- Check crm.pipeline for deal pipeline status
- Use crm.conversion_stats to discuss lead conversion rates
- When user mentions a contact name, use the 'search' parameter to find them

WHEN HANDLING LEADS/CONTACTS:
- Reference specific contact names from context when available
- Mention lead scores when relevant (scores 60+ are "hot")
- Suggest follow-ups for stale leads
- When converting leads, congratulate the user!

MULTI-TURN CONVERSATION HANDLING (IMPORTANT):
When a skill returns a response requiring user input, you MUST handle it properly:

1. **status: "multiple_matches"** - Multiple items found:
   - Present the numbered list to the user clearly
   - Ask them to pick a number or clarify
   - Remember the matches list so you can use the task_id when they respond
   - Example: "I found 3 tasks matching 'landing page'. Which one do you mean?\n\n**1)** Landing page for Facebook _(new)_\n**2)** Landing page redesign _(working)_\n**3)** Fix landing page bugs _(done)_"

2. **status: "not_found"** - Nothing found:
   - Tell the user you couldn't find it
   - Ask for more specific keywords or the exact name
   - Example: "I couldn't find a task matching 'facebook page'. Could you give me the exact task name or more details?"

3. **When user responds with a number or clarification**:
   - Use the task_id from the previous matches list (e.g., if they say "1", use the first task's ID)
   - If they provide more keywords, search again with the new terms
   - Complete the originally intended action (update, delete, etc.)

4. **Maintaining context across messages**:
   - Remember what action the user originally wanted (e.g., "move to in progress")
   - When they clarify, combine with the original intent
   - Example flow:
     User: "Move landing page to in progress"
     You: "I found 2 tasks. Which one?\n1. Landing page for FB\n2. Landing page for IG"
     User: "1" or "the facebook one"
     You: (Use task_id from option 1 + status: "working" from original request)

CURRENT CONTEXT:
{$contextJson}

GUIDELINES:
1. Be action-oriented - execute skills when user intent is clear
2. After using a skill, summarize briefly: "✅ Created task 'X' in project Y"
3. If skill fails, explain the error simply and suggest what to try
4. Use markdown formatting for readability
5. For CRM actions, reference contact names when confirming actions
6. When asking follow-up questions, be concise and clear about what you need
7. Remember previous context - if user says "1" after you listed options, use that option

Remember: Execute actions quickly. Don't over-ask. Use sensible defaults. But when clarification is genuinely needed (multiple matches, not found), ask and wait for user response.
PROMPT;
    }

    /**
     * Build the system prompt for chat conversations.
     */
    protected function buildChatSystemPrompt(array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
You are Beeta, an AI assistant for BeetaSky - a project management and CRM platform. You help users manage their tasks, projects, and contacts efficiently.

PERSONALITY:
- Friendly, professional, and helpful
- Concise but thorough when needed
- Proactive in suggesting relevant actions
- Use clear formatting for lists and data

CAPABILITIES:
- Answer questions about the user's tasks, projects, and contacts
- Provide productivity tips and suggestions
- Help prioritize work based on due dates and priorities
- Summarize project status and progress
- Explain how to use system features

CURRENT CONTEXT (User's data):
{$contextJson}

GUIDELINES:
1. Reference specific task names, project names, and contact names when relevant
2. When discussing overdue or upcoming tasks, mention specific due dates
3. If asked about data not in context, politely explain you can only see summary information
4. For action requests (create task, etc.), explain how to do it in the UI since you cannot directly modify data
5. Be helpful but don't make up information not in the context
6. Use markdown formatting for better readability (bold, lists, etc.)

Remember: You are chatting with a team member who is managing their work. Be supportive and actionable.
PROMPT;
    }

    /**
     * Build context data for AI chat from company data.
     */
    public function buildChatContext(?string $companyId): array
    {
        if (!$companyId) {
            return [
                'has_company' => false,
                'message' => 'No company selected',
            ];
        }

        $now = now();
        $weekFromNow = now()->addDays(7);

        // Fetch tasks data
        $tasksQuery = Task::forCompany($companyId)->whereNull('deleted_at');
        $totalTasks = $tasksQuery->count();
        $pendingTasks = (clone $tasksQuery)->whereRaw('completed = false')->count();
        $overdueTasks = (clone $tasksQuery)
            ->whereRaw('completed = false')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $now)
            ->count();
        $dueSoonTasks = (clone $tasksQuery)
            ->whereRaw('completed = false')
            ->whereNotNull('due_date')
            ->where('due_date', '>=', $now)
            ->where('due_date', '<=', $weekFromNow)
            ->count();

        // Fetch sample tasks for context
        $sampleTasks = Task::forCompany($companyId)
            ->whereNull('deleted_at')
            ->whereRaw('completed = false')
            ->with('project:id,name')
            ->orderByRaw("CASE 
                WHEN due_date < NOW() THEN 1 
                WHEN priority = 'urgent' THEN 2 
                WHEN priority = 'high' THEN 3 
                ELSE 4 END")
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'priority', 'due_date', 'status', 'project_id']);

        // Fetch projects data
        $projectsQuery = Project::forCompany($companyId)->whereNull('deleted_at');
        $totalProjects = $projectsQuery->count();
        $activeProjects = (clone $projectsQuery)->where('status', 'working')->count();

        // Sample projects
        $sampleProjects = Project::forCompany($companyId)
            ->whereNull('deleted_at')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'status', 'due_date']);

        // Fetch contact stats
        $contactsQuery = CompanyContact::where('company_id', $companyId);
        $totalContacts = $contactsQuery->count();
        $leadsCount = (clone $contactsQuery)->where('relation_type', 'lead')->count();
        $customersCount = (clone $contactsQuery)->where('relation_type', 'customer')->count();
        $prospectsCount = (clone $contactsQuery)->where('relation_type', 'prospect')->count();

        // Stale leads (no activity in 14+ days)
        $staleLeadsCount = (clone $contactsQuery)
            ->where('relation_type', 'lead')
            ->where(function ($q) {
                $q->where('last_activity_at', '<', now()->subDays(14))
                    ->orWhereNull('last_activity_at');
            })
            ->count();

        // Hot leads (high score)
        $hotLeads = CompanyContact::where('company_id', $companyId)
            ->where('relation_type', 'lead')
            ->where('lead_score', '>=', 60)
            ->with('contact:id,full_name,email,organization')
            ->orderBy('lead_score', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($cc) => [
                'id' => $cc->contact_id,
                'name' => $cc->contact?->full_name,
                'organization' => $cc->contact?->organization,
                'score' => $cc->lead_score,
            ])->toArray();

        // Recent leads
        $recentLeads = CompanyContact::where('company_id', $companyId)
            ->where('relation_type', 'lead')
            ->with('contact:id,full_name,email,organization')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($cc) => [
                'id' => $cc->contact_id,
                'name' => $cc->contact?->full_name,
                'email' => $cc->contact?->email,
                'organization' => $cc->contact?->organization,
                'created' => $cc->created_at->diffForHumans(),
            ])->toArray();

        // Deals pipeline
        $openDeals = Deal::where('company_id', $companyId)->open()->get();
        $pipelineValue = $openDeals->sum('value');
        $weightedValue = $openDeals->sum('weighted_value');
        $dealsClosingSoon = Deal::where('company_id', $companyId)
            ->closingSoon(7)
            ->with('contact:id,full_name')
            ->limit(5)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'value' => $d->value,
                'stage' => $d->stage,
                'contact' => $d->contact?->full_name,
                'expected_close' => $d->expected_close_date?->format('M j'),
            ])->toArray();

        // Conversions this month
        $conversionsThisMonth = CompanyContact::where('company_id', $companyId)
            ->where('relation_type', 'customer')
            ->whereNotNull('converted_at')
            ->where('converted_at', '>=', now()->startOfMonth())
            ->count();

        // Calculate conversion rate
        $leadsLastMonth = CompanyContact::where('company_id', $companyId)
            ->where('relation_type', 'lead')
            ->where('created_at', '>=', now()->subMonth())
            ->count();
        $conversionRate = $leadsLastMonth > 0 
            ? round(($conversionsThisMonth / $leadsLastMonth) * 100, 1) 
            : 0;

        return [
            'has_company' => true,
            'tasks' => [
                'total' => $totalTasks,
                'pending' => $pendingTasks,
                'overdue' => $overdueTasks,
                'due_soon' => $dueSoonTasks,
                'sample' => $sampleTasks->map(fn($t) => [
                    'title' => $t->title,
                    'priority' => $t->priority,
                    'due_date' => $t->due_date?->format('M j, Y'),
                    'status' => $t->status,
                    'project' => $t->project?->name ?? 'No project',
                    'is_overdue' => $t->due_date && $t->due_date->isPast(),
                ])->toArray(),
            ],
            'projects' => [
                'total' => $totalProjects,
                'active' => $activeProjects,
                'sample' => $sampleProjects->map(fn($p) => [
                    'name' => $p->name,
                    'status' => $p->status,
                    'due_date' => $p->due_date?->format('M j, Y'),
                ])->toArray(),
            ],
            'crm' => [
                'contacts' => [
                    'total' => $totalContacts,
                    'leads' => $leadsCount,
                    'customers' => $customersCount,
                    'prospects' => $prospectsCount,
                ],
                'lead_insights' => [
                    'stale_leads_count' => $staleLeadsCount,
                    'hot_leads' => $hotLeads,
                    'recent_leads' => $recentLeads,
                ],
                'pipeline' => [
                    'open_deals' => count($openDeals),
                    'total_value' => $pipelineValue,
                    'weighted_value' => $weightedValue,
                    'closing_soon' => $dealsClosingSoon,
                ],
                'conversion_stats' => [
                    'this_month' => $conversionsThisMonth,
                    'conversion_rate' => $conversionRate,
                ],
            ],
            // Legacy contacts key for backward compatibility
            'contacts' => [
                'total' => $totalContacts,
                'leads' => $leadsCount,
                'customers' => $customersCount,
            ],
        ];
    }

    /**
     * Analyze dashboard data and return suggestions.
     */
    public function analyzeDashboard(array $context, ?string $userId = null, ?string $companyId = null): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildDashboardPrompt($context);

        $startTime = microtime(true);
        
        try {
            $response = $this->callOpenAI($systemPrompt, $userPrompt);
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // Parse the JSON response from AI
            $suggestions = $this->parseAIResponse($response);

            // Log the AI run
            $this->logAiRun(
                'dashboard_analysis',
                $userId,
                $companyId,
                $userPrompt,
                $response,
                $latencyMs,
                'success'
            );

            return $suggestions;
        } catch (\Exception $e) {
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
            
            Log::error('AI Dashboard Analysis failed', [
                'error' => $e->getMessage(),
                'context' => $context,
            ]);

            // Log failed AI run
            $this->logAiRun(
                'dashboard_analysis',
                $userId,
                $companyId,
                $userPrompt,
                null,
                $latencyMs,
                'error',
                $e->getMessage()
            );

            // Return fallback suggestions
            return $this->getFallbackSuggestions($context);
        }
    }

    /**
     * Build the system prompt for the AI assistant.
     */
    protected function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are a smart project management assistant. Your role is to analyze the user's dashboard data and provide actionable suggestions to help them be more productive.

RULES:
1. Always respond with valid JSON only, no markdown or explanations
2. Focus on the most impactful suggestions (max 5)
3. Be specific - mention actual task names, project names when available
4. Prioritize: overdue tasks > high priority tasks > missing setup > productivity tips
5. Each suggestion must have actionable next steps

CRITICAL HIERARCHY (MUST FOLLOW):
The system has a strict creation order. You MUST NOT suggest items out of order:
- LEVEL 1: Company (REQUIRED first - nothing else can exist without it)
- LEVEL 2: Project (REQUIRES Company - cannot suggest if no company)
- LEVEL 3: Task (REQUIRES Project - cannot suggest if no projects exist)
- Contact: Can be created anytime after Company exists

If has_company=false: ONLY suggest creating a company. Do NOT suggest projects or tasks.
If has_company=true but projects.total=0: Suggest creating a project. Do NOT suggest tasks.
If has_company=true and projects.total>0: Can suggest tasks and analyze existing ones.

RESPONSE FORMAT:
{
  "summary": "Brief 1-2 sentence summary of their current status",
  "suggestions": [
    {
      "id": "sug_1",
      "type": "warning|tip|action|setup",
      "title": "Short title (max 50 chars)",
      "description": "Detailed description with specific names/data",
      "priority": "high|medium|low",
      "actions": [
        {
          "type": "navigate|create_company|create_project|create_task|complete_task|prioritize",
          "label": "Button text",
          "path": "/path/to/page (for navigate type)",
          "task_id": "uuid (for complete_task type)",
          "project_id": "uuid (for prioritize type)"
        }
      ]
    }
  ]
}

ACTION TYPES:
- navigate: Navigate to a specific page
- create_company: Trigger company creation flow
- create_project: Trigger project creation flow
- create_task: Trigger task creation flow
- complete_task: Mark a specific task as complete
- prioritize: Open task prioritization view for a project
PROMPT;
    }

    /**
     * Build the user prompt with dashboard context.
     */
    protected function buildDashboardPrompt(array $context): string
    {
        $json = json_encode($context, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
Analyze this dashboard data and provide suggestions:

{$json}

IMPORTANT - Follow this decision tree:
1. If has_company is false → ONLY suggest "Create Company". Stop here, don't suggest anything else.
2. If has_company is true BUT projects.total is 0 → Suggest "Create Project". Do NOT suggest tasks.
3. If has_company is true AND projects.total > 0 → Now you can analyze tasks:
   - Check for overdue tasks and highlight them
   - Look at high priority tasks that aren't completed  
   - Look for tasks due soon that need attention
   - If tasks.total is 0, suggest creating tasks
4. Contacts can be suggested anytime after company exists

Respond with JSON only.
PROMPT;
    }

    /**
     * Call OpenAI API.
     */
    protected function callOpenAI(string $systemPrompt, string $userPrompt): array
    {
        Log::info('OpenAI call starting', [
            'has_api_key' => !empty($this->apiKey),
            'api_key_prefix' => substr($this->apiKey ?? '', 0, 10) . '...',
            'model' => $this->model,
        ]);

        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($this->baseUrl . '/chat/completions', [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Parse the AI response and extract suggestions.
     */
    protected function parseAIResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        if (empty($content)) {
            throw new \Exception('Empty response from AI');
        }

        $parsed = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON in AI response: ' . json_last_error_msg());
        }

        // Validate structure
        if (!isset($parsed['suggestions']) || !is_array($parsed['suggestions'])) {
            throw new \Exception('Invalid suggestion format from AI');
        }

        // Add generated timestamp
        $parsed['generated_at'] = now()->toIso8601String();

        // Extract token usage for cost tracking
        if (isset($response['usage'])) {
            $parsed['usage'] = [
                'input_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $response['usage']['completion_tokens'] ?? 0,
            ];
        }

        return $parsed;
    }

    /**
     * Get fallback suggestions when AI fails.
     */
    protected function getFallbackSuggestions(array $context): array
    {
        $suggestions = [];
        $hasCompany = $context['has_company'] ?? false;
        $totalProjects = $context['projects']['total'] ?? 0;
        $totalTasks = $context['tasks']['total'] ?? 0;

        // HIERARCHY: Company → Project → Task (strict order)
        
        // Level 1: No Company - ONLY suggest company creation
        if (!$hasCompany) {
            return [
                'summary' => 'Welcome! Let\'s get you started by creating your company.',
                'suggestions' => [
                    [
                        'id' => 'sug_setup_1',
                        'type' => 'setup',
                        'title' => 'Create Your Company',
                        'description' => 'You need to create a company first to start managing projects, tasks, and contacts.',
                        'priority' => 'high',
                        'actions' => [
                            ['type' => 'create_company', 'label' => 'Create Company'],
                        ],
                    ],
                ],
                'generated_at' => now()->toIso8601String(),
                'fallback' => true,
            ];
        }

        // Level 2: Has Company but no Projects - suggest project creation
        if ($totalProjects === 0) {
            $suggestions[] = [
                'id' => 'sug_no_projects',
                'type' => 'setup',
                'title' => 'Create Your First Project',
                'description' => 'Get started by creating a project to organize your tasks and collaborate with your team.',
                'priority' => 'high',
                'actions' => [
                    ['type' => 'create_project', 'label' => 'Create Project'],
                ],
            ];
            
            return [
                'summary' => 'Great! You have a company. Now let\'s create your first project.',
                'suggestions' => $suggestions,
                'generated_at' => now()->toIso8601String(),
                'fallback' => true,
            ];
        }

        // Level 3: Has Projects - can now suggest tasks
        if ($totalTasks === 0) {
            $suggestions[] = [
                'id' => 'sug_no_tasks',
                'type' => 'tip',
                'title' => 'Add Tasks to Your Project',
                'description' => 'Break down your work into manageable tasks to track progress.',
                'priority' => 'medium',
                'actions' => [
                    ['type' => 'create_task', 'label' => 'Create Task'],
                ],
            ];
        }

        // Check for overdue tasks (only if projects exist)
        $overdue = $context['tasks']['overdue'] ?? 0;
        if ($overdue > 0) {
            $suggestions[] = [
                'id' => 'sug_overdue',
                'type' => 'warning',
                'title' => "{$overdue} Overdue Task" . ($overdue > 1 ? 's' : ''),
                'description' => "You have {$overdue} task" . ($overdue > 1 ? 's' : '') . " past their due date.",
                'priority' => 'high',
                'actions' => [
                    ['type' => 'navigate', 'label' => 'View Overdue', 'path' => '/tasks?filter=overdue'],
                ],
            ];
        }

        $summary = $totalTasks > 0 
            ? "You have {$totalTasks} task" . ($totalTasks > 1 ? 's' : '') . " across {$totalProjects} project" . ($totalProjects > 1 ? 's' : '') . "."
            : 'Your projects are set up. Add some tasks to get started!';

        return [
            'summary' => $summary,
            'suggestions' => $suggestions,
            'generated_at' => now()->toIso8601String(),
            'fallback' => true,
        ];
    }

    /**
     * Log AI run to database.
     */
    protected function logAiRun(
        string $runType,
        ?string $userId,
        ?string $companyId,
        string $requestPayload,
        ?array $responsePayload,
        int $latencyMs,
        string $status,
        ?string $errorMessage = null
    ): void {
        try {
            AiRun::create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'run_type' => $runType,
                'input_tokens' => $responsePayload['usage']['prompt_tokens'] ?? null,
                'output_tokens' => $responsePayload['usage']['completion_tokens'] ?? null,
                'latency_ms' => $latencyMs,
                'request_payload' => ['prompt' => substr($requestPayload, 0, 1000)],
                'response_payload' => $responsePayload ? array_intersect_key($responsePayload, array_flip(['summary', 'suggestions'])) : null,
                'status' => $status,
                'error_message' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            // Don't fail if logging fails
            Log::warning('Failed to log AI run', ['error' => $e->getMessage()]);
        }
    }
}

