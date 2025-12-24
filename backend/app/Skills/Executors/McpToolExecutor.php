<?php

namespace App\Skills\Executors;

use App\Models\AiSkill;
use App\Models\User;
use App\Services\CRMService;
use App\Services\DealService;
use App\Services\LeadScoringService;
use App\Services\ProjectManagementService;
use App\Services\ReminderService;
use Illuminate\Support\Facades\Log;

/**
 * Executes MCP tools by wrapping existing MCP tool classes.
 */
class McpToolExecutor
{
    protected ProjectManagementService $projectService;
    protected CRMService $crmService;
    protected LeadScoringService $leadScoringService;
    protected DealService $dealService;
    protected ReminderService $reminderService;

    public function __construct(
        ProjectManagementService $projectService,
        CRMService $crmService,
        LeadScoringService $leadScoringService,
        DealService $dealService,
        ReminderService $reminderService
    ) {
        $this->projectService = $projectService;
        $this->crmService = $crmService;
        $this->leadScoringService = $leadScoringService;
        $this->dealService = $dealService;
        $this->reminderService = $reminderService;
    }

    /**
     * Execute an MCP tool skill.
     */
    public function execute(AiSkill $skill, array $params, array $context): array
    {
        $toolClass = $skill->mcp_tool_class;

        if (!$toolClass) {
            return [
                'success' => false,
                'error' => "MCP tool class not specified for skill: {$skill->slug}",
            ];
        }

        try {
            // Get the user for authentication context
            $user = User::find($context['user_id']);

            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'User not found for MCP tool execution',
                ];
            }

            // Add company_id to params if not present
            if (!isset($params['company_id']) && isset($context['company_id'])) {
                $params['company_id'] = $context['company_id'];
            }

            // Route to appropriate service method based on tool class
            $result = $this->routeToService($toolClass, $user, $params, $context);

            return $result;
        } catch (\Exception $e) {
            Log::error('MCP tool execution failed', [
                'tool_class' => $toolClass,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'MCP tool execution failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Route to the appropriate service method based on tool class.
     */
    protected function routeToService(string $toolClass, User $user, array $params, array $context): array
    {
        $companyId = $params['company_id'] ?? $context['company_id'] ?? null;

        // Extract the tool name from the class
        $className = class_basename($toolClass);

        // Route based on tool type
        return match (true) {
            // Task tools
            str_contains($className, 'CreateTaskTool') => $this->createTask($user, $companyId, $params),
            str_contains($className, 'UpdateTaskTool') => $this->updateTask($user, $companyId, $params),
            str_contains($className, 'DeleteTaskTool') => $this->deleteTask($user, $companyId, $params),
            str_contains($className, 'GetTaskTool') => $this->getTask($user, $companyId, $params),
            str_contains($className, 'ListTasksTool') => $this->listTasks($user, $companyId, $params),
            str_contains($className, 'AssignTaskTool') => $this->assignTask($user, $companyId, $params),
            str_contains($className, 'SearchTasksTool') => $this->searchTasks($user, $companyId, $params),

            // Project tools
            str_contains($className, 'CreateProjectTool') => $this->createProject($user, $companyId, $params),
            str_contains($className, 'UpdateProjectTool') => $this->updateProject($user, $companyId, $params),
            str_contains($className, 'DeleteProjectTool') => $this->deleteProject($user, $companyId, $params),
            str_contains($className, 'GetProjectTool') => $this->getProject($user, $companyId, $params),
            str_contains($className, 'ListProjectsTool') => $this->listProjects($user, $companyId, $params),

            // Topic tools
            str_contains($className, 'CreateTopicTool') => $this->createTopic($user, $companyId, $params),
            str_contains($className, 'UpdateTopicTool') => $this->updateTopic($user, $companyId, $params),
            str_contains($className, 'DeleteTopicTool') => $this->deleteTopic($user, $companyId, $params),
            str_contains($className, 'ListTopicsTool') => $this->listTopics($user, $companyId, $params),

            // Comment tools
            str_contains($className, 'CreateCommentTool') => $this->createComment($user, $companyId, $params),
            str_contains($className, 'UpdateCommentTool') => $this->updateComment($user, $companyId, $params),
            str_contains($className, 'DeleteCommentTool') => $this->deleteComment($user, $companyId, $params),
            str_contains($className, 'ListCommentsTool') => $this->listComments($user, $companyId, $params),

            // Contact tools (CRM)
            str_contains($className, 'CreateContactTool') => $this->createContact($user, $companyId, $params),
            str_contains($className, 'ListContactsTool') => $this->listContacts($user, $companyId, $params),
            str_contains($className, 'GetContactTool') => $this->getContact($user, $companyId, $params),
            str_contains($className, 'UpdateContactTool') => $this->updateContact($user, $companyId, $params),
            str_contains($className, 'ConvertLeadTool') => $this->convertLead($user, $companyId, $params),
            str_contains($className, 'AddContactNoteTool') => $this->addContactNote($user, $companyId, $params),
            str_contains($className, 'ScoreLeadTool') => $this->scoreLead($user, $companyId, $params),

            // Deal tools
            str_contains($className, 'CreateDealTool') => $this->createDeal($user, $companyId, $params),
            str_contains($className, 'UpdateDealTool') => $this->updateDeal($user, $companyId, $params),
            str_contains($className, 'ListDealsTool') => $this->listDeals($user, $companyId, $params),

            // Reminder tools
            str_contains($className, 'CreateReminderTool') => $this->createReminder($user, $companyId, $params),
            str_contains($className, 'ListRemindersTool') => $this->listReminders($user, $companyId, $params),

            default => [
                'success' => false,
                'error' => "No handler found for tool: {$className}",
            ],
        };
    }

    // Task methods
    protected function createTask(User $user, ?string $companyId, array $params): array
    {
        $topicId = $params['topic_id'] ?? null;
        
        // If no topic_id provided, try to find one automatically
        if (!$topicId && $companyId) {
            $topicId = $this->findDefaultTopic($companyId);
        }
        
        if (!$topicId) {
            return [
                'success' => false, 
                'error' => 'No topic specified and no default topic found. Please create a project with a topic first, or specify which topic to add the task to.'
            ];
        }

        // Set default priority if not provided
        if (!isset($params['priority'])) {
            $params['priority'] = 'medium';
        }

        // Set default status if not provided
        if (!isset($params['status'])) {
            $params['status'] = 'todo';
        }

        return $this->projectService->createTask($user, $companyId, $topicId, $params);
    }

    /**
     * Find a default topic for the company.
     */
    protected function findDefaultTopic(?string $companyId): ?string
    {
        if (!$companyId) {
            return null;
        }

        // Try to find the most recently updated topic
        $topic = \App\Models\Topic::whereHas('project', function ($q) use ($companyId) {
            $q->where('company_id', $companyId)
              ->whereNull('deleted_at');
        })
        ->whereNull('deleted_at')
        ->orderBy('updated_at', 'desc')
        ->first();

        return $topic?->id;
    }

    protected function updateTask(User $user, ?string $companyId, array $params): array
    {
        $taskId = $params['task_id'] ?? $params['id'] ?? null;
        $searchQuery = $params['search'] ?? null;
        $selection = $params['selection'] ?? null; // User's selection from numbered list
        
        // If no task_id, try to find by search
        if (!$taskId && $searchQuery) {
            $limit = $selection ? max(5, (int)$selection) : 5;
            $searchResult = $this->projectService->searchTasks($user, $companyId, $searchQuery, ['limit' => $limit]);
            
            if (!$searchResult['success'] || empty($searchResult['data'])) {
                return [
                    'success' => false,
                    'status' => 'not_found',
                    'error' => "No task found matching: '{$searchQuery}'",
                    'action_required' => 'Ask user to clarify the task name or provide more details.',
                ];
            }
            
            $matches = $searchResult['data'];
            $matchCount = count($matches);
            
            // If user provided a selection number, use that match
            if ($selection && $selection >= 1 && $selection <= $matchCount) {
                $taskId = $matches[$selection - 1]['id'];
            }
            // Multiple matches without selection - return list for user to choose
            elseif ($matchCount > 1) {
                $taskList = array_map(function($task, $index) {
                    return [
                        'number' => $index + 1,
                        'id' => $task['id'],
                        'title' => $task['title'],
                        'status' => $task['status'],
                        'project' => $task['project_name'] ?? 'Unknown',
                    ];
                }, $matches, array_keys($matches));
                
                return [
                    'success' => false,
                    'status' => 'multiple_matches',
                    'message' => "Found {$matchCount} tasks matching '{$searchQuery}'",
                    'matches' => $taskList,
                    'action_required' => 'Ask the user which task they want to update.',
                    'next_step' => 'When user responds with a number, call update_task with the same search term plus selection parameter.',
                ];
            }
            // Single match
            else {
                $taskId = $matches[0]['id'];
            }
        }
        
        // Also support 'title' as search term for convenience
        if (!$taskId && !empty($params['title']) && empty($params['new_title'])) {
            $findResult = $this->projectService->findTaskByTitle($user, $companyId, $params['title']);
            if ($findResult['success']) {
                $taskId = $findResult['data']['id'];
            }
        }
        
        if (!$taskId) {
            return [
                'success' => false,
                'status' => 'missing_input',
                'error' => 'task_id or search parameter is required.',
                'action_required' => 'Ask user which task they want to update.',
            ];
        }

        return $this->projectService->updateTask($user, $companyId, $taskId, $params);
    }

    protected function deleteTask(User $user, ?string $companyId, array $params): array
    {
        $taskId = $params['task_id'] ?? $params['id'] ?? null;
        if (!$taskId) {
            return ['success' => false, 'error' => 'task_id is required'];
        }

        return $this->projectService->deleteTask($user, $companyId, $taskId);
    }

    protected function getTask(User $user, ?string $companyId, array $params): array
    {
        $taskId = $params['task_id'] ?? $params['id'] ?? null;
        if (!$taskId) {
            return ['success' => false, 'error' => 'task_id is required'];
        }

        return $this->projectService->getTask($user, $companyId, $taskId);
    }

    protected function listTasks(User $user, ?string $companyId, array $params): array
    {
        $topicId = $params['topic_id'] ?? null;
        $projectId = $params['project_id'] ?? null;
        
        $filters = array_filter([
            'status' => $params['status'] ?? null,
            'priority' => $params['priority'] ?? null,
            'assigned_to' => $params['assigned_to'] ?? null,
            'limit' => $params['limit'] ?? null,
        ], fn($v) => $v !== null);

        return $this->projectService->listTasks($user, $companyId, $topicId, $projectId, $filters);
    }

    protected function assignTask(User $user, ?string $companyId, array $params): array
    {
        $taskId = $params['task_id'] ?? $params['id'] ?? null;
        $assigneeId = $params['assignee_id'] ?? $params['user_id'] ?? null;

        if (!$taskId) {
            return ['success' => false, 'error' => 'task_id is required'];
        }

        return $this->projectService->assignTask($user, $companyId, $taskId, $assigneeId);
    }

    protected function searchTasks(User $user, ?string $companyId, array $params): array
    {
        $search = $params['search'] ?? $params['query'] ?? null;

        if (!$search) {
            return ['success' => false, 'error' => 'search term is required'];
        }

        $result = $this->projectService->searchTasks($user, $companyId, $search, $params);
        
        if (!$result['success']) {
            return $result;
        }
        
        $tasks = $result['data'];
        $total = count($tasks);
        
        if ($total === 0) {
            return [
                'success' => true,
                'status' => 'not_found',
                'message' => "No tasks found matching '{$search}'",
                'data' => [],
            ];
        }
        
        // Add numbered references for easy selection
        $formattedTasks = array_map(function($task, $index) {
            return array_merge($task, ['number' => $index + 1]);
        }, $tasks, array_keys($tasks));
        
        return [
            'success' => true,
            'status' => $total === 1 ? 'single_match' : 'multiple_matches',
            'total' => $total,
            'search_query' => $search,
            'data' => $formattedTasks,
            'note' => $total > 1 
                ? 'Multiple matches found. Ask user to specify which one by number or provide the task_id.' 
                : 'Single match found.',
        ];
    }

    // Project methods
    protected function createProject(User $user, ?string $companyId, array $params): array
    {
        return $this->projectService->createProject($user, $companyId, $params);
    }

    protected function updateProject(User $user, ?string $companyId, array $params): array
    {
        $projectId = $params['project_id'] ?? $params['id'] ?? null;
        if (!$projectId) {
            return ['success' => false, 'error' => 'project_id is required'];
        }

        return $this->projectService->updateProject($user, $companyId, $projectId, $params);
    }

    protected function deleteProject(User $user, ?string $companyId, array $params): array
    {
        $projectId = $params['project_id'] ?? $params['id'] ?? null;
        if (!$projectId) {
            return ['success' => false, 'error' => 'project_id is required'];
        }

        return $this->projectService->deleteProject($user, $companyId, $projectId);
    }

    protected function getProject(User $user, ?string $companyId, array $params): array
    {
        $projectId = $params['project_id'] ?? $params['id'] ?? null;
        if (!$projectId) {
            return ['success' => false, 'error' => 'project_id is required'];
        }

        return $this->projectService->getProject($user, $companyId, $projectId);
    }

    protected function listProjects(User $user, ?string $companyId, array $params): array
    {
        return $this->projectService->listProjects($user, $companyId, $params);
    }

    // Topic methods
    protected function createTopic(User $user, ?string $companyId, array $params): array
    {
        $projectId = $params['project_id'] ?? null;
        if (!$projectId) {
            return ['success' => false, 'error' => 'project_id is required'];
        }

        return $this->projectService->createTopic($user, $companyId, $projectId, $params);
    }

    protected function updateTopic(User $user, ?string $companyId, array $params): array
    {
        $topicId = $params['topic_id'] ?? $params['id'] ?? null;
        if (!$topicId) {
            return ['success' => false, 'error' => 'topic_id is required'];
        }

        return $this->projectService->updateTopic($user, $companyId, $topicId, $params);
    }

    protected function deleteTopic(User $user, ?string $companyId, array $params): array
    {
        $topicId = $params['topic_id'] ?? $params['id'] ?? null;
        if (!$topicId) {
            return ['success' => false, 'error' => 'topic_id is required'];
        }

        return $this->projectService->deleteTopic($user, $companyId, $topicId);
    }

    protected function listTopics(User $user, ?string $companyId, array $params): array
    {
        $projectId = $params['project_id'] ?? null;
        if (!$projectId) {
            return ['success' => false, 'error' => 'project_id is required'];
        }

        return $this->projectService->listTopics($user, $companyId, $projectId);
    }

    // Comment methods
    protected function createComment(User $user, ?string $companyId, array $params): array
    {
        $taskId = $params['task_id'] ?? null;
        if (!$taskId) {
            return ['success' => false, 'error' => 'task_id is required'];
        }

        return $this->projectService->createComment($user, $companyId, $taskId, $params);
    }

    protected function updateComment(User $user, ?string $companyId, array $params): array
    {
        $commentId = $params['comment_id'] ?? $params['id'] ?? null;
        if (!$commentId) {
            return ['success' => false, 'error' => 'comment_id is required'];
        }

        return $this->projectService->updateComment($user, $companyId, $commentId, $params);
    }

    protected function deleteComment(User $user, ?string $companyId, array $params): array
    {
        $commentId = $params['comment_id'] ?? $params['id'] ?? null;
        if (!$commentId) {
            return ['success' => false, 'error' => 'comment_id is required'];
        }

        return $this->projectService->deleteComment($user, $companyId, $commentId);
    }

    protected function listComments(User $user, ?string $companyId, array $params): array
    {
        $taskId = $params['task_id'] ?? null;
        if (!$taskId) {
            return ['success' => false, 'error' => 'task_id is required'];
        }

        return $this->projectService->listComments($user, $companyId, $taskId);
    }

    // Contact methods (CRM)
    protected function createContact(User $user, ?string $companyId, array $params): array
    {
        return $this->crmService->createContact($user, $companyId, $params);
    }

    protected function listContacts(User $user, ?string $companyId, array $params): array
    {
        return $this->crmService->listContacts($user, $companyId, $params);
    }

    protected function getContact(User $user, ?string $companyId, array $params): array
    {
        $contactId = $params['contact_id'] ?? $params['id'] ?? null;
        
        // If no contact_id, try to find by search
        if (!$contactId && !empty($params['search'])) {
            $findResult = $this->crmService->findContact($user, $companyId, $params['search']);
            if (!$findResult['success']) {
                return $findResult;
            }
            $contactId = $findResult['data']['id'];
        }

        if (!$contactId) {
            return ['success' => false, 'error' => 'contact_id or search term is required'];
        }

        return $this->crmService->getContact($user, $companyId, $contactId);
    }

    protected function updateContact(User $user, ?string $companyId, array $params): array
    {
        $contactId = $params['contact_id'] ?? $params['id'] ?? null;
        
        // If no contact_id, try to find by search
        if (!$contactId && !empty($params['search'])) {
            $findResult = $this->crmService->findContact($user, $companyId, $params['search']);
            if (!$findResult['success']) {
                return $findResult;
            }
            $contactId = $findResult['data']['id'];
        }

        if (!$contactId) {
            return ['success' => false, 'error' => 'contact_id or search term is required'];
        }

        return $this->crmService->updateContact($user, $companyId, $contactId, $params);
    }

    protected function convertLead(User $user, ?string $companyId, array $params): array
    {
        $contactId = $params['contact_id'] ?? $params['id'] ?? null;
        
        // If no contact_id, try to find by search
        if (!$contactId && !empty($params['search'])) {
            $findResult = $this->crmService->findContact($user, $companyId, $params['search']);
            if (!$findResult['success']) {
                return $findResult;
            }
            $contactId = $findResult['data']['id'];
        }

        if (!$contactId) {
            return ['success' => false, 'error' => 'contact_id or search term is required'];
        }

        return $this->crmService->convertLead($user, $companyId, $contactId);
    }

    protected function addContactNote(User $user, ?string $companyId, array $params): array
    {
        $contactId = $params['contact_id'] ?? $params['id'] ?? null;
        
        // If no contact_id, try to find by search
        if (!$contactId && !empty($params['search'])) {
            $findResult = $this->crmService->findContact($user, $companyId, $params['search']);
            if (!$findResult['success']) {
                return $findResult;
            }
            $contactId = $findResult['data']['id'];
        }

        if (!$contactId) {
            return ['success' => false, 'error' => 'contact_id or search term is required'];
        }

        return $this->crmService->addNote($user, $companyId, $contactId, $params);
    }

    protected function scoreLead(User $user, ?string $companyId, array $params): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        // Score all leads
        if (!empty($params['score_all'])) {
            return $this->leadScoringService->scoreAllLeads($companyId);
        }

        // Find contact
        $contactId = $params['contact_id'] ?? $params['id'] ?? null;

        if (!$contactId && !empty($params['search'])) {
            $findResult = $this->crmService->findContact($user, $companyId, $params['search']);
            if (!$findResult['success']) {
                return $findResult;
            }
            $contactId = $findResult['data']['id'];
        }

        if (!$contactId) {
            return ['success' => false, 'error' => 'Please provide contact_id, search, or score_all=true'];
        }

        // Score single contact
        $companyContact = \App\Models\CompanyContact::where('company_id', $companyId)
            ->where('contact_id', $contactId)
            ->with('contact')
            ->first();

        if (!$companyContact) {
            return ['success' => false, 'error' => 'Contact not found'];
        }

        return $this->leadScoringService->scoreContact($companyContact);
    }

    // Deal methods
    protected function createDeal(User $user, ?string $companyId, array $params): array
    {
        return $this->dealService->createDeal($user, $companyId, $params);
    }

    protected function updateDeal(User $user, ?string $companyId, array $params): array
    {
        $dealId = $params['deal_id'] ?? $params['id'] ?? null;
        if (!$dealId) {
            return ['success' => false, 'error' => 'deal_id is required'];
        }

        return $this->dealService->updateDeal($user, $companyId, $dealId, $params);
    }

    protected function listDeals(User $user, ?string $companyId, array $params): array
    {
        return $this->dealService->listDeals($user, $companyId, $params);
    }

    // Reminder methods
    protected function createReminder(User $user, ?string $companyId, array $params): array
    {
        $contactId = $params['contact_id'] ?? $params['id'] ?? null;

        // If no contact_id, try to find by search
        if (!$contactId && !empty($params['search'])) {
            $findResult = $this->crmService->findContact($user, $companyId, $params['search']);
            if (!$findResult['success']) {
                return $findResult;
            }
            $contactId = $findResult['data']['id'];
        }

        if (!$contactId) {
            return ['success' => false, 'error' => 'contact_id or search term is required'];
        }

        $params['contact_id'] = $contactId;
        return $this->reminderService->createReminder($user, $companyId, $params);
    }

    protected function listReminders(User $user, ?string $companyId, array $params): array
    {
        return $this->reminderService->listReminders($user, $companyId, $params);
    }
}

