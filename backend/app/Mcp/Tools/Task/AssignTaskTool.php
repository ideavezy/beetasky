<?php

namespace App\Mcp\Tools\Task;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AssignTaskTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'assign_task';

    /**
     * The tool's description.
     */
    protected string $description = 'Assign a user to a task. The user will be notified.';

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
                    'description' => 'The UUID of the task',
                ],
                'user_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the user to assign to the task',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
            ],
            'required' => ['task_id', 'user_id'],
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
        $assigneeId = $request->input('user_id');
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$taskId) {
            return Response::error('task_id is required');
        }

        if (!$assigneeId) {
            return Response::error('user_id is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        try {
            $result = $service->assignTask($user, $companyId, $taskId, $assigneeId);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to assign task');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to assign task: ' . $e->getMessage());
        }
    }
}

