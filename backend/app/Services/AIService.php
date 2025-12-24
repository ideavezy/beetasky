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
     * Maximum iterations for tool calling loop.
     * Prevents infinite loops while allowing multi-step operations.
     */
    protected int $maxToolIterations = 5;

    /**
     * Stream a chat response with skills (function calling) support.
     * Supports iterative tool calling - AI can search, then act on results.
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
        $allToolResults = [];
        $iteration = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        try {
            Log::info('Starting OpenAI chat with skills (iterative)', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'tools_count' => count($tools),
                'message_length' => strlen($message),
            ]);

            // Iterative tool calling loop
            while ($iteration < $this->maxToolIterations) {
                $iteration++;

                Log::info("Tool calling iteration {$iteration}", [
                    'messages_count' => count($messages),
                ]);

                // Call OpenAI
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
                        'iteration' => $iteration,
                ]);
                throw new \Exception('AI service error: ' . $errorMsg);
            }

            $data = $response->json();
                $totalInputTokens += $data['usage']['prompt_tokens'] ?? 0;
                $totalOutputTokens += $data['usage']['completion_tokens'] ?? 0;

                $choice = $data['choices'][0] ?? null;
                if (!$choice) {
                throw new \Exception('No response from AI service');
            }

                $assistantMessage = $choice['message'] ?? [];
                $finishReason = $choice['finish_reason'] ?? '';

                Log::info("OpenAI response iteration {$iteration}", [
                'finish_reason' => $finishReason,
                'has_tool_calls' => !empty($assistantMessage['tool_calls']),
                    'has_content' => !empty($assistantMessage['content']),
            ]);

            // Check if AI wants to call tools
            if ($finishReason === 'tool_calls' && !empty($assistantMessage['tool_calls'])) {
                // Add assistant's tool call message to history
                $messages[] = $assistantMessage;

                    // Execute each tool call
                    foreach ($assistantMessage['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'] ?? '';
                    $functionArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                    $toolCallId = $toolCall['id'] ?? '';

                        Log::info('Executing skill from chat (iteration ' . $iteration . ')', [
                        'skill' => $functionName,
                        'args' => $functionArgs,
                        'user_id' => $userId,
                        'company_id' => $companyId,
                    ]);

                    try {
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
                                'status' => $result['status'] ?? null,
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

                        $allToolResults[] = [
                        'skill' => $functionName,
                        'result' => $result,
                            'iteration' => $iteration,
                    ];

                        // Add tool result to messages for next iteration
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode($result),
                    ];
                }

                    // Continue the loop - AI will process tool results and may call more tools
                    continue;
            }

                // No more tool calls - AI is ready to respond
                // Stream the final response
                if (!empty($assistantMessage['content'])) {
                    $content = $assistantMessage['content'];
            $onChunk($content);

            return [
                'content' => $content,
                        'input_tokens' => $totalInputTokens,
                        'output_tokens' => $totalOutputTokens,
                'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
                        'tool_results' => $allToolResults,
                        'iterations' => $iteration,
                    ];
                }

                // Edge case: finish_reason is 'stop' but no content
                // This shouldn't happen, but handle it gracefully
                Log::warning('AI finished without content', [
                    'finish_reason' => $finishReason,
                    'iteration' => $iteration,
                ]);

                // If we have tool results, stream a summary response
                if (!empty($allToolResults)) {
                    return $this->streamFinalResponse($messages, $onChunk, $startTime, $allToolResults);
                }

                // Truly empty response
                $onChunk("I apologize, but I wasn't able to process your request. Could you please try rephrasing it?");
                return [
                    'content' => "I apologize, but I wasn't able to process your request. Could you please try rephrasing it?",
                    'input_tokens' => $totalInputTokens,
                    'output_tokens' => $totalOutputTokens,
                    'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
                    'tool_results' => $allToolResults,
                    'iterations' => $iteration,
                ];
            }

            // Max iterations reached - stream what we have
            Log::warning('Max tool iterations reached', [
                'max' => $this->maxToolIterations,
                'tool_results_count' => count($allToolResults),
            ]);

            return $this->streamFinalResponse($messages, $onChunk, $startTime, $allToolResults);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenAI connection timeout', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'iteration' => $iteration,
            ]);
            throw new \Exception('Connection timed out. Please try again.');
        } catch (\Exception $e) {
            Log::error('OpenAI chat with skills failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'company_id' => $companyId,
                'iteration' => $iteration,
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

⚠️ CRITICAL ACTION RULES - NEVER BREAK THESE:

1. **NEVER SAY "LET ME FIND" WITHOUT ACTUALLY CALLING A SKILL**
   - WRONG: "Let me find the project ID for you. Please hold on." (then doing nothing)
   - RIGHT: Actually call search_projects or list_projects skill IMMEDIATELY

2. **ALWAYS USE SEARCH SKILLS WHEN GIVEN A NAME/TITLE**
   - If user says "rename project Test" → Call list_projects or search_projects with search="Test" FIRST
   - If user says "update task Landing page" → Call search_tasks with search="Landing page" FIRST
   - If user says "find contact John" → Call list_contacts with search="John" FIRST

3. **CHAIN SKILLS IN ONE TURN**
   - Search first → Get results → Then call update/action skill with the ID from results
   - Example: User says "mark task 'Homepage' as done"
     Step 1: Call search_tasks(search="Homepage") → Get task_id from result
     Step 2: Call update_task(task_id=<from step 1>, status="done")

4. **USE SEARCH PARAMETERS, NOT IDs FROM USER TEXT**
   - User saying "project Test" means search for name "Test", NOT that "Test" is an ID
   - User saying "task Homepage" means search for title "Homepage", NOT that "Homepage" is an ID

5. **EXECUTE IMMEDIATELY - NO EMPTY PROMISES**
   - Don't say "I'll find that for you" then stop
   - Don't say "Please hold on" then do nothing
   - Either call a skill NOW or ask the user for specific missing info

SKILL USAGE PATTERNS:

**Finding items by name:**
- Projects: list_projects(search="name") or search_projects(query="name")
- Tasks: search_tasks(search="title") or update_task(search="title", ...)
- Contacts: list_contacts(search="name")
- Deals: list_deals(search="title")

**Updating items by name (chained):**
- First search, then update with the returned ID
- update_task supports a 'search' parameter for convenience

**Creating items:**
- create_task, create_project, create_contact - use defaults for optional fields

AVAILABLE SKILLS:
{$skillsList}

PROJECT MANAGEMENT SKILLS:
- create_task: Create new tasks
- update_task: Update tasks - Use 'search' param to find by name, or 'task_id' if you have the ID
- update_project: Update projects - Use 'search' param to find by name, or 'project_id' if you have the ID
- search_tasks: Search for tasks by title/description
- list_tasks: List tasks in a topic/project
- list_projects: List/search projects
- assign_task: Assign users to tasks
- create_project: Create new projects
- create_topic, list_topics: Organize tasks into topics/sections

CRM SKILLS:
- create_contact: Add new leads/contacts
- list_contacts: Search and filter contacts
- get_contact: Get detailed contact info
- update_contact: Update contact fields
- convert_lead: Convert lead to customer
- add_contact_note: Log activities/interactions
- create_deal, update_deal, list_deals: Manage deals pipeline
- create_reminder, list_reminders: Manage reminders

HANDLING SEARCH RESULTS:

1. **Single match** → Use the ID immediately for the requested action
2. **Multiple matches** → Present numbered list and ask user to choose
3. **No matches** → Ask user for more specific name or check spelling

MULTI-TURN HANDLING:
When user responds with a number (e.g., "1") after you listed options:
- Use the ID from that option
- Complete the original action they requested

CURRENT CONTEXT:
{$contextJson}

RESPONSE GUIDELINES:
1. Be action-oriented - execute skills when user intent is clear
2. After using a skill, confirm: "✅ Done: [what was done]"
3. If skill fails, explain simply and suggest alternatives
4. Use markdown for readability
5. If genuinely need clarification (multiple matches, ambiguous request), ask ONCE clearly

REMEMBER: Your job is to EXECUTE actions, not just acknowledge them. If you can't find something, SEARCH for it. Never respond with "let me find" without actually searching.
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
     * Generate contract template sections using AI.
     *
     * @param string $prompt User's description of the contract they need
     * @param array $options Additional options (contract_type, client_info, etc.)
     * @param string|null $userId User ID for logging
     * @param string|null $companyId Company ID for logging
     * @return array Generated sections in the template format
     */
    public function generateContractSections(
        string $prompt,
        array $options = [],
        ?string $userId = null,
        ?string $companyId = null
    ): array {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        $contractType = $options['contract_type'] ?? 'fixed_price';
        $clientName = $options['client_name'] ?? '{{client.full_name}}';
        $projectName = $options['project_name'] ?? '{{project.name}}';

        $systemPrompt = <<<'PROMPT'
You are an expert contract writer. Generate professional contract sections based on the user's request.

OUTPUT FORMAT:
You MUST return a valid JSON object with this exact structure:
{
  "sections": [
    {
      "type": "heading",
      "content": { "text": "Section Title" }
    },
    {
      "type": "paragraph", 
      "content": { "html": "<p>Paragraph content with <strong>formatting</strong> as needed.</p>" }
    }
  ],
  "clickwrap_text": "I agree to the terms and conditions outlined above."
}

SECTION TYPES AVAILABLE:
- "heading": For section titles. Use { "text": "Title Here" }
- "paragraph": For rich text content. Use { "html": "<p>Content here</p>" }. You can use <strong>, <em>, <ul>, <ol>, <li> tags.
- "table": For tabular data. Use { "rows": 3, "cols": 2, "cells": [["Header1", "Header2"], ["Value1", "Value2"], ["Value3", "Value4"]], "hasHeader": true }
- "signature": For signature blocks. Use { "label": "Client Signature", "nameField": "{{client.full_name}}" }

MERGE FIELDS TO USE:
- {{client.first_name}}, {{client.last_name}}, {{client.full_name}}, {{client.email}}, {{client.phone}}, {{client.organization}}
- {{project.name}}, {{project.description}}, {{project.start_date}}, {{project.end_date}}
- {{company.name}}, {{company.email}}, {{company.phone}}, {{company.address}}
- {{today}}, {{contract.created_date}}, {{contract.expires_at}}

CONTRACT TYPE: __CONTRACT_TYPE__
CLIENT: __CLIENT_NAME__
PROJECT: __PROJECT_NAME__

GUIDELINES:
1. Create a professional, legally-sound contract structure
2. Include all necessary sections for the type of service/project described
3. Use merge fields for dynamic content (client name, dates, etc.)
4. Include a signature section at the end
5. Keep language clear and professional
6. Include sections for: scope of work, deliverables, timeline, payment terms, confidentiality (if relevant), termination, and general provisions
7. Return ONLY valid JSON, no markdown code blocks or extra text
PROMPT;

        // Inject runtime values (nowdoc doesn't interpolate variables)
        $systemPrompt = str_replace(
            ['__CONTRACT_TYPE__', '__CLIENT_NAME__', '__PROJECT_NAME__'],
            [$contractType, $clientName, $projectName],
            $systemPrompt
        );

        $userMessage = "Generate a contract for: {$prompt}";

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000,
                'response_format' => ['type' => 'json_object'],
            ]);

            $latencyMs = (int)((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                $this->logAiRun(
                    'contract_generation',
                    $userId,
                    $companyId,
                    $prompt,
                    null,
                    $latencyMs,
                    'failed',
                    $response->body()
                );
                throw new \Exception('OpenAI API error: ' . $response->body());
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            
            // Parse the JSON response
            $result = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse AI response as JSON');
            }

            // Add IDs and order to sections
            $sections = $result['sections'] ?? [];
            foreach ($sections as $index => &$section) {
                $section['id'] = 'section-' . time() . '-' . $index;
                $section['order'] = $index;
            }
            unset($section);

            $this->logAiRun(
                'contract_generation',
                $userId,
                $companyId,
                $prompt,
                [
                    'usage' => $data['usage'] ?? [],
                    'summary' => 'Generated ' . count($sections) . ' contract sections',
                ],
                $latencyMs,
                'completed'
            );

            return [
                'sections' => $sections,
                'clickwrap_text' => $result['clickwrap_text'] ?? 'I agree to the terms and conditions outlined above.',
                'usage' => $data['usage'] ?? [],
            ];

        } catch (\Exception $e) {
            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            
            $this->logAiRun(
                'contract_generation',
                $userId,
                $companyId,
                $prompt,
                null,
                $latencyMs,
                'failed',
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Generate a single contract template section (heading or paragraph) using AI.
     * Accepts surrounding template context to improve coherence.
     *
     * @return array { type: 'heading'|'paragraph', content: {...}, usage?: {...} }
     */
    public function generateContractSection(
        string $prompt,
        string $sectionType,
        array $templateContext = [],
        ?string $userId = null,
        ?string $companyId = null
    ): array {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        if (!in_array($sectionType, ['heading', 'paragraph'], true)) {
            throw new \InvalidArgumentException('Invalid section type');
        }

        $templateName = $templateContext['template_name'] ?? null;
        $contractType = $templateContext['contract_type'] ?? 'fixed_price';
        $contextSections = $templateContext['sections'] ?? [];

        // Build a concise context summary to keep token usage sane
        $contextSummary = '';
        if (is_array($contextSections) && count($contextSections) > 0) {
            usort($contextSections, function ($a, $b) {
                $ao = is_array($a) ? ($a['order'] ?? 0) : 0;
                $bo = is_array($b) ? ($b['order'] ?? 0) : 0;
                return $ao <=> $bo;
            });

            $lines = [];
            foreach ($contextSections as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $t = $s['type'] ?? 'unknown';
                $text = trim((string)($s['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $text = mb_substr($text, 0, 600);
                $lines[] = strtoupper((string)$t) . ': ' . $text;
                if (count($lines) >= 20) {
                    break;
                }
            }
            $contextSummary = implode("\n", $lines);
        }

        $systemPrompt = <<<'PROMPT'
You are an expert contract writer. You will generate content for ONE contract template section.

SECTION TYPE TO GENERATE: __SECTION_TYPE__
CONTRACT TYPE: __CONTRACT_TYPE__
TEMPLATE NAME: __TEMPLATE_NAME__

OUTPUT FORMAT:
Return ONLY valid JSON with this exact structure:
{
  "type": "heading|paragraph",
  "content": { ... }
}

RULES:
- If type is "heading", content MUST be: { "text": "Short clear heading" }
- If type is "paragraph", content MUST be: { "html": "<p>...</p>" }
- For paragraph HTML: use only <p>, <strong>, <em>, <ul>, <ol>, <li>, <br>. No markdown.
- Use merge fields when appropriate: {{client.full_name}}, {{company.name}}, {{project.name}}, {{today}}, etc.
- Keep the writing consistent with the provided context, but prioritize the user's instruction for this specific section.
- Do NOT include any extra keys or commentary outside the JSON object.
PROMPT;

        $systemPrompt = str_replace(
            ['__SECTION_TYPE__', '__CONTRACT_TYPE__', '__TEMPLATE_NAME__'],
            [$sectionType, (string)$contractType, (string)($templateName ?? '')],
            $systemPrompt
        );

        $userMessageParts = [];
        if ($contextSummary !== '') {
            $userMessageParts[] = "TEMPLATE CONTEXT (other sections):\n" . $contextSummary;
        }
        $userMessageParts[] = "USER INSTRUCTION FOR THIS SECTION:\n" . $prompt;
        $userMessage = implode("\n\n", $userMessageParts);

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'temperature' => 0.6,
                'max_tokens' => 1200,
                'response_format' => ['type' => 'json_object'],
            ]);

            $latencyMs = (int)((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                $this->logAiRun(
                    'contract_section_generation',
                    $userId,
                    $companyId,
                    substr($userMessage, 0, 1000),
                    null,
                    $latencyMs,
                    'failed',
                    $response->body()
                );
                throw new \Exception('OpenAI API error: ' . $response->body());
            }

            $data = $response->json();
            $contentStr = $data['choices'][0]['message']['content'] ?? '';
            $result = json_decode($contentStr, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                throw new \Exception('Failed to parse AI response as JSON');
            }

            $returnedType = $result['type'] ?? $sectionType;
            $returnedContent = $result['content'] ?? null;

            if (!in_array($returnedType, ['heading', 'paragraph'], true) || !is_array($returnedContent)) {
                throw new \Exception('AI response missing required keys (type/content)');
            }

            if ($returnedType === 'heading') {
                $text = trim((string)($returnedContent['text'] ?? ''));
                if ($text === '') {
                    throw new \Exception('AI heading text was empty');
                }
                $returnedContent = ['text' => mb_substr($text, 0, 120)];
            } else {
                $html = trim((string)($returnedContent['html'] ?? ''));
                if ($html === '') {
                    throw new \Exception('AI paragraph HTML was empty');
                }
                if (!str_contains($html, '<p')) {
                    $safe = htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
                    $html = '<p>' . $safe . '</p>';
                }
                $returnedContent = ['html' => $html];
            }

            $this->logAiRun(
                'contract_section_generation',
                $userId,
                $companyId,
                substr($userMessage, 0, 1000),
                [
                    'usage' => $data['usage'] ?? [],
                    'summary' => 'Generated section: ' . $returnedType,
                ],
                $latencyMs,
                'completed'
            );

            return [
                'type' => $returnedType,
                'content' => $returnedContent,
                'usage' => $data['usage'] ?? [],
            ];
        } catch (\Exception $e) {
            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->logAiRun(
                'contract_section_generation',
                $userId,
                $companyId,
                substr($userMessage ?? $prompt, 0, 1000),
                null,
                $latencyMs,
                'failed',
                $e->getMessage()
            );
            throw $e;
        }
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

