<?php

namespace App\Mcp\Tools\Comment;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListCommentsTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'list_comments';

    /**
     * The tool's description.
     */
    protected string $description = 'List all comments on a task.';

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

        $result = $service->listComments($user, $companyId, $taskId);

        if (!$result['success']) {
            return Response::error($result['error'] ?? 'Failed to list comments');
        }

        return Response::text(json_encode($result['data'], JSON_PRETTY_PRINT));
    }
}

