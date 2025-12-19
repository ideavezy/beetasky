<?php

namespace App\Skills\Executors;

use App\Models\AiSkill;
use App\Models\User;
use App\Services\ProjectManagementService;
use Illuminate\Support\Facades\Log;

/**
 * Executes MCP tools by wrapping existing MCP tool classes.
 */
class McpToolExecutor
{
    protected ProjectManagementService $projectService;

    public function __construct(ProjectManagementService $projectService)
    {
        $this->projectService = $projectService;
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
            $params['status'] = 'new';
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
        if (!$taskId) {
            return ['success' => false, 'error' => 'task_id is required'];
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
        return $this->projectService->listTasks($user, $companyId, $params);
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
}

