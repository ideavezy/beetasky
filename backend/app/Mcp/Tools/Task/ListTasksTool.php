<?php

namespace App\Mcp\Tools\Task;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListTasksTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'list_tasks';

    /**
     * The tool's description.
     */
    protected string $description = 'List tasks. Can filter by topic, project, status, priority, or assignee.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic_id' => [
                    'type' => 'string',
                    'description' => 'Filter tasks by topic UUID',
                ],
                'project_id' => [
                    'type' => 'string',
                    'description' => 'Filter tasks by project UUID (if topic_id not provided)',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter by task status',
                    'enum' => ['backlog', 'todo', 'in_progress', 'on_hold', 'in_review', 'done'],
                ],
                'priority' => [
                    'type' => 'string',
                    'description' => 'Filter by task priority',
                    'enum' => ['low', 'medium', 'high', 'urgent'],
                ],
                'assigned_to' => [
                    'type' => 'string',
                    'description' => 'Filter by assignee user UUID',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of tasks to return (default: 100)',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ProjectManagementService $service): Response
    {
        $user = $request->user();

        if (!$user) {
            return Response::error('Authentication required');
        }

        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $topicId = $request->input('topic_id');
        $projectId = $request->input('project_id');

        if (!$topicId && !$projectId) {
            return Response::error('Either topic_id or project_id is required');
        }

        $filters = array_filter([
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'assigned_to' => $request->input('assigned_to'),
            'limit' => $request->input('limit'),
        ], fn($v) => $v !== null);

        $result = $service->listTasks($user, $companyId, $topicId, $projectId, $filters);

        if (!$result['success']) {
            return Response::error($result['error'] ?? 'Failed to list tasks');
        }

        return Response::text(json_encode($result['data'], JSON_PRETTY_PRINT));
    }
}

