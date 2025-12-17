<?php

namespace App\Services;

use App\Models\AiRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
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

