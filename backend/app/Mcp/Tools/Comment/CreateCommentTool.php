<?php

namespace App\Mcp\Tools\Comment;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateCommentTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'create_comment';

    /**
     * The tool's description.
     */
    protected string $description = 'Add a comment to a task.';

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
                    'description' => 'The UUID of the task to comment on',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The content of the comment',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'is_internal' => [
                    'type' => 'boolean',
                    'description' => 'If true, the comment is only visible to team members (default: false)',
                ],
            ],
            'required' => ['task_id', 'content'],
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
        $content = $request->input('content');
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$taskId) {
            return Response::error('task_id is required');
        }

        if (!$content) {
            return Response::error('Comment content is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $data = [
            'content' => $content,
            'is_internal' => $request->input('is_internal') ?? false,
        ];

        try {
            $result = $service->createComment($user, $companyId, $taskId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to create comment');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'comment' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to create comment: ' . $e->getMessage());
        }
    }
}

