<?php

namespace App\Mcp\Tools\Task;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SearchTasksTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'search_tasks';

    /**
     * The tool's description.
     */
    protected string $description = 'Search for tasks by title or description across all projects. Use this to find tasks when you only know part of the name.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Search term to find in task title or description (REQUIRED)',
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Alias for search',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter by status',
                    'enum' => ['backlog', 'todo', 'in_progress', 'on_hold', 'in_review', 'done'],
                ],
                'priority' => [
                    'type' => 'string',
                    'description' => 'Filter by priority',
                    'enum' => ['low', 'medium', 'high', 'urgent'],
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum results (default: 20)',
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
            return Response::error('company_id is required');
        }

        $search = $request->input('search') ?? $request->input('query');

        if (!$search) {
            return Response::error('search term is required');
        }

        $filters = [
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'limit' => $request->input('limit') ?? 20,
        ];

        $result = $service->searchTasks($user, $companyId, $search, $filters);

        if (!$result['success']) {
            return Response::error($result['error'] ?? 'Failed to search tasks');
        }

        $tasks = $result['data'];
        $total = $result['total'];

        if (empty($tasks)) {
            return Response::text(json_encode([
                'status' => 'not_found',
                'message' => "No tasks found matching '{$search}'",
                'suggestion' => 'Try different keywords or check the exact task name.',
            ], JSON_PRETTY_PRINT));
        }

        // Format tasks with numbers for easy reference
        $formattedTasks = array_map(function($task, $index) {
            return [
                'number' => $index + 1,
                'id' => $task['id'],
                'title' => $task['title'],
                'status' => $task['status'],
                'priority' => $task['priority'],
                'project' => $task['project_name'] ?? 'Unknown',
                'topic' => $task['topic_name'] ?? 'Unknown',
                'assignees' => $task['assignees'] ?? null,
            ];
        }, $tasks, array_keys($tasks));

        // Build human-readable list with clear number formatting
        $taskListText = implode("\n", array_map(function($t) {
            $statusEmoji = match ($t['status']) {
                'backlog' => '📋',
                'todo' => '📝',
                'in_progress' => '🔄',
                'on_hold' => '⏸️',
                'in_review' => '👀',
                'done' => '✅',
                default => '📋',
            };
            return "**{$t['number']})** {$statusEmoji} {$t['title']} _({$t['status']})_ - {$t['project']}";
        }, $formattedTasks));

        return Response::text(json_encode([
            'status' => $total === 1 ? 'single_match' : 'multiple_matches',
            'total' => $total,
            'search_query' => $search,
            'tasks' => $formattedTasks,
            'display_text' => "Found {$total} task(s) matching '{$search}':\n\n{$taskListText}",
            'note' => $total > 1 
                ? 'If user wants to act on one of these, they can specify by number or use the task_id directly.' 
                : 'Single match found. Can proceed with action using this task_id.',
        ], JSON_PRETTY_PRINT));
    }
}

