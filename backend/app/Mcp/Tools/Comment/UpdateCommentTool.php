<?php

namespace App\Mcp\Tools\Comment;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateCommentTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'update_comment';

    /**
     * The tool's description.
     */
    protected string $description = 'Update a comment. Only the comment author can edit their own comments.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'comment_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the comment to update',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The new content of the comment',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
            ],
            'required' => ['comment_id', 'content'],
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

        $commentId = $request->input('comment_id');
        $content = $request->input('content');
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$commentId) {
            return Response::error('comment_id is required');
        }

        if (!$content) {
            return Response::error('Comment content is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        try {
            $result = $service->updateComment($user, $companyId, $commentId, ['content' => $content]);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to update comment');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'comment' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to update comment: ' . $e->getMessage());
        }
    }
}

