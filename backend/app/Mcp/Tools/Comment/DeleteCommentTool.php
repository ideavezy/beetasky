<?php

namespace App\Mcp\Tools\Comment;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteCommentTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'delete_comment';

    /**
     * The tool's description.
     */
    protected string $description = 'Delete a comment. Only the author or project admin can delete comments.';

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
                    'description' => 'The UUID of the comment to delete',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
            ],
            'required' => ['comment_id'],
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
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$commentId) {
            return Response::error('comment_id is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        try {
            $result = $service->deleteComment($user, $companyId, $commentId);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to delete comment');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to delete comment: ' . $e->getMessage());
        }
    }
}

