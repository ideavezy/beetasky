<?php

namespace App\Mcp\Tools\Task;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTaskTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'update_task';

    /**
     * The tool's description.
     */
    protected string $description = 'Update a task. Can find by task_id OR by searching title. Can change title, description, status, priority, due date, or mark as complete.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the task to update. Use this when you have the exact task ID.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search for task by title or description. Use this when you don\'t have the task_id.',
                ],
                'selection' => [
                    'type' => 'integer',
                    'description' => 'When user picks a number from a previous search result (e.g., "1" or "the first one"), pass that number here along with the original search term.',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'The new title of the task',
                    'maxLength' => 500,
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'The new description of the task',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The new detailed content of the task',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'The new status of the task',
                    'enum' => ['backlog', 'todo', 'in_progress', 'on_hold', 'in_review', 'done'],
                ],
                'priority' => [
                    'type' => 'string',
                    'description' => 'The new priority of the task',
                    'enum' => ['low', 'medium', 'high', 'urgent'],
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'The new due date (YYYY-MM-DD or ISO 8601 format, or null to clear)',
                ],
                'completed' => [
                    'type' => 'boolean',
                    'description' => 'Set to true to mark task as complete, false to mark as incomplete',
                ],
                'topic_id' => [
                    'type' => 'string',
                    'description' => 'Move the task to a different topic by providing the new topic UUID',
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

        $taskId = $request->input('task_id');
        $searchQuery = $request->input('search');
        $selection = $request->input('selection'); // User's selection number from a previous list
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        // If no task_id, try to find by search query
        if (!$taskId && $searchQuery) {
            $limit = $selection ? max(5, $selection) : 5; // Ensure we get enough results for the selection
            $searchResult = $service->searchTasks($user, $companyId, $searchQuery, ['limit' => $limit]);
            
            if (!$searchResult['success'] || empty($searchResult['data'])) {
                return Response::text(json_encode([
                    'status' => 'not_found',
                    'message' => "No task found matching: '{$searchQuery}'",
                    'suggestion' => 'Ask the user for more specific task name or different keywords.',
                    'action_required' => 'Ask user to clarify the task name or provide more details.',
                ], JSON_PRETTY_PRINT));
            }
            
            $matches = $searchResult['data'];
            $matchCount = count($matches);
            
            // If user provided a selection number, use that match directly
            if ($selection && $selection >= 1 && $selection <= $matchCount) {
                $selectedIndex = $selection - 1; // Convert to 0-based index
                $taskId = $matches[$selectedIndex]['id'];
                $foundTaskTitle = $matches[$selectedIndex]['title'];
            }
            // If multiple matches and no selection, ask user to clarify
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
                
                // Build a clear numbered list for the user
                $listItems = array_map(fn($t) => "**{$t['number']})** {$t['title']} _({$t['status']})_ - {$t['project']}", $taskList);
                
                return Response::text(json_encode([
                    'status' => 'multiple_matches',
                    'message' => "Found {$matchCount} tasks matching '{$searchQuery}'",
                    'matches' => $taskList,
                    'action_required' => 'Ask the user which task they want to update. Present the numbered list and wait for their response.',
                    'example_followup' => "I found {$matchCount} tasks matching that. Which one did you mean?\n\n" . implode("\n", $listItems),
                    'next_step' => 'When user responds with a number, call update_task again with the same search term plus selection parameter set to their number.',
                ], JSON_PRETTY_PRINT));
            }
            // Single match - proceed
            else {
                $taskId = $matches[0]['id'];
                $foundTaskTitle = $matches[0]['title'];
            }
        } elseif (!$taskId) {
            return Response::text(json_encode([
                'status' => 'missing_input',
                'message' => 'Either task_id or search query is required',
                'action_required' => 'Ask the user which task they want to update.',
            ], JSON_PRETTY_PRINT));
        }

        $data = array_filter([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'content' => $request->input('content'),
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'due_date' => $request->input('due_date'),
            'completed' => $request->input('completed'),
            'topic_id' => $request->input('topic_id'),
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return Response::error('At least one field to update is required');
        }

        try {
            $result = $service->updateTask($user, $companyId, $taskId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to update task');
            }

            $response = [
                'message' => $result['message'],
                'task' => $result['data'],
            ];
            
            // Add context about which task was found if we searched for it
            if (isset($foundTaskTitle)) {
                $response['note'] = "Found and updated task: '{$foundTaskTitle}'";
            }

            return Response::text(json_encode($response, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to update task: ' . $e->getMessage());
        }
    }
}

