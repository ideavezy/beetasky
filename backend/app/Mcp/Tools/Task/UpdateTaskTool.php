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
    protected string $description = 'Update a task. Can change title, description, status, priority, due date, or mark as complete.';

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
                    'description' => 'The UUID of the task to update',
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
                    'enum' => ['new', 'working', 'question', 'on_hold', 'in_review', 'done', 'canceled'],
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
            'required' => ['task_id'],
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
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$taskId) {
            return Response::error('task_id is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
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

            return Response::text(json_encode([
                'message' => $result['message'],
                'task' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to update task: ' . $e->getMessage());
        }
    }
}

