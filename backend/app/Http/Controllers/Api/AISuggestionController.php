<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyContact;
use App\Models\Deal;
use App\Models\Project;
use App\Models\Task;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AISuggestionController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get AI-powered dashboard suggestions - fetches all data from DB directly.
     * This is the optimized endpoint that doesn't require frontend to send context.
     */
    public function dashboardSuggestions(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        Log::info('AI Dashboard Suggestions request received', [
            'user_id' => $user->id ?? null,
            'company_id' => $companyId,
        ]);

        try {
            $hasCompany = !empty($companyId);
            
            // Build context directly from database
            $context = $this->buildDashboardContext($companyId, $hasCompany);

            // Get AI suggestions
            $result = $this->aiService->analyzeDashboard(
                $context,
                $user->id,
                $companyId
            );

            Log::info('AI Dashboard Suggestions generated', [
                'suggestions_count' => count($result['suggestions'] ?? []),
                'has_summary' => isset($result['summary']),
                'is_fallback' => $result['fallback'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('AI Dashboard Suggestions failed', [
                'error' => $e->getMessage(),
                'company_id' => $companyId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate suggestions',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Build dashboard context from database.
     */
    protected function buildDashboardContext(?string $companyId, bool $hasCompany): array
    {
        if (!$hasCompany || !$companyId) {
            return [
                'has_company' => false,
                'tasks' => ['total' => 0, 'pending' => 0, 'overdue' => 0, 'due_soon' => 0],
                'projects' => ['total' => 0, 'active' => 0],
                'contacts' => ['total' => 0, 'leads' => 0, 'customers' => 0],
                'tasks_sample' => [],
            ];
        }

        $now = now();
        $weekFromNow = now()->addDays(7);

        // Fetch tasks data
        // Uses whereRaw for PostgreSQL boolean compatibility with emulated prepares
        $tasksQuery = Task::forCompany($companyId)->whereNull('deleted_at');
        $totalTasks = $tasksQuery->count();
        $pendingTasks = (clone $tasksQuery)->whereRaw('completed = false')->where('status', '!=', 'done')->count();
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

        // Fetch sample tasks for AI analysis (prioritize overdue and high priority)
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
        $activeProjects = (clone $projectsQuery)->where('status', 'active')->count();

        // Fetch contact stats
        $contactsQuery = CompanyContact::where('company_id', $companyId);
        $totalContacts = $contactsQuery->count();
        $leadsCount = (clone $contactsQuery)->where('relation_type', 'lead')->count();
        $customersCount = (clone $contactsQuery)->where('relation_type', 'customer')->count();

        // CRM: Stale leads (no activity in 14+ days)
        $staleLeadsCount = (clone $contactsQuery)
            ->where('relation_type', 'lead')
            ->where(function ($q) {
                $q->where('last_activity_at', '<', now()->subDays(14))
                    ->orWhereNull('last_activity_at');
            })
            ->count();

        // CRM: Conversions this month
        $conversionsThisMonth = (clone $contactsQuery)
            ->where('relation_type', 'customer')
            ->whereNotNull('converted_at')
            ->where('converted_at', '>=', now()->startOfMonth())
            ->count();

        // CRM: Deal pipeline
        $openDeals = Deal::where('company_id', $companyId)->open()->get();
        $pipelineValue = $openDeals->sum('value');
        $dealsClosingSoon = Deal::where('company_id', $companyId)->closingSoon(7)->count();

        return [
            'has_company' => true,
            'tasks' => [
                'total' => $totalTasks,
                'pending' => $pendingTasks,
                'overdue' => $overdueTasks,
                'due_soon' => $dueSoonTasks,
            ],
            'projects' => [
                'total' => $totalProjects,
                'active' => $activeProjects,
            ],
            'contacts' => [
                'total' => $totalContacts,
                'leads' => $leadsCount,
                'customers' => $customersCount,
            ],
            'crm' => [
                'stale_leads' => $staleLeadsCount,
                'conversions_this_month' => $conversionsThisMonth,
                'pipeline_value' => $pipelineValue,
                'deals_closing_soon' => $dealsClosingSoon,
                'open_deals' => count($openDeals),
            ],
            'tasks_sample' => $sampleTasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
                'due_date' => $t->due_date?->toIso8601String(),
                'status' => $t->status,
                'project_name' => $t->project?->name ?? 'Unknown',
            ])->toArray(),
        ];
    }

    /**
     * Get AI-powered suggestions based on dashboard context (legacy endpoint).
     * @deprecated Use dashboardSuggestions() instead for better performance.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        Log::info('AI Suggestions request received', [
            'user_id' => $user->id ?? null,
            'company_id' => $companyId,
            'context_keys' => array_keys($request->input('context', [])),
        ]);

        try {
            $validated = $request->validate([
                'context' => 'required|array',
                'context.has_company' => 'required|boolean',
                'context.tasks' => 'nullable|array',
                'context.tasks.total' => 'nullable|integer',
                'context.tasks.pending' => 'nullable|integer',
                'context.tasks.overdue' => 'nullable|integer',
                'context.tasks.due_soon' => 'nullable|integer',
                'context.projects' => 'nullable|array',
                'context.projects.total' => 'nullable|integer',
                'context.projects.active' => 'nullable|integer',
                'context.contacts' => 'nullable|array',
                'context.contacts.total' => 'nullable|integer',
                'context.contacts.leads' => 'nullable|integer',
                'context.contacts.customers' => 'nullable|integer',
                'context.tasks_sample' => 'nullable|array',
                'context.tasks_sample.*.id' => 'nullable|string',
                'context.tasks_sample.*.title' => 'nullable|string',
                'context.tasks_sample.*.priority' => 'nullable|string',
                'context.tasks_sample.*.due_date' => 'nullable|string',
                'context.tasks_sample.*.status' => 'nullable|string',
                'context.tasks_sample.*.project_name' => 'nullable|string',
            ]);

            $context = $validated['context'];

            // Get AI suggestions
            $result = $this->aiService->analyzeDashboard(
                $context,
                $user->id,
                $companyId
            );

            Log::info('AI Suggestions generated', [
                'suggestions_count' => count($result['suggestions'] ?? []),
                'has_summary' => isset($result['summary']),
                'is_fallback' => $result['fallback'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            Log::warning('AI Suggestions validation failed', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate suggestions',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Execute an AI-suggested action.
     */
    public function executeAction(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $validated = $request->validate([
                'action_type' => 'required|string|in:complete_task,prioritize',
                'task_id' => 'nullable|uuid',
                'project_id' => 'nullable|uuid',
            ]);

            $result = match ($validated['action_type']) {
                'complete_task' => $this->completeTask($validated['task_id'], $user, $companyId),
                'prioritize' => $this->getPrioritizedTasks($validated['project_id'], $companyId),
                default => throw new \InvalidArgumentException('Unknown action type'),
            };

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute action',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Complete a task.
     */
    protected function completeTask(string $taskId, $user, ?string $companyId): array
    {
        $task = \App\Models\Task::forCompany($companyId)
            ->with('project')
            ->find($taskId);

        if (!$task || !$task->project->hasMember($user)) {
            throw new \Exception('Task not found or access denied');
        }

        $task->update([
            'completed' => true,
            'completed_at' => now(),
            'completed_by' => $user->id,
            'status' => 'done',
        ]);

        return [
            'message' => "Task '{$task->title}' marked as complete",
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => 'done',
            ],
        ];
    }

    /**
     * Get prioritized tasks for a project.
     */
    protected function getPrioritizedTasks(string $projectId, ?string $companyId): array
    {
        $project = \App\Models\Project::forCompany($companyId)->find($projectId);

        if (!$project) {
            throw new \Exception('Project not found');
        }

        // Uses whereRaw for PostgreSQL boolean compatibility with emulated prepares
        $tasks = \App\Models\Task::where('project_id', $projectId)
            ->whereRaw('completed = false')
            ->orderByRaw("CASE priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
                ELSE 5 END")
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'priority', 'due_date', 'status']);

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'prioritized_tasks' => $tasks,
        ];
    }
}

