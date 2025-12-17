<?php

namespace App\Mcp\Tools\Task;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetTaskTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'get_task';

    /**
     * The tool's description.
     */
    protected string $description = 'Get detailed information about a specific task, including comments and assignees.';

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
                    'description' => 'The UUID of the task to retrieve',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
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

        $result = $service->getTask($user, $companyId, $taskId);

        if (!$result['success']) {
            return Response::error($result['error'] ?? 'Failed to get task');
        }

        return Response::text(json_encode($result['data'], JSON_PRETTY_PRINT));
    }
}

