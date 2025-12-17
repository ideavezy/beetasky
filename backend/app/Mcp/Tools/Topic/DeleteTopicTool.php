<?php

namespace App\Mcp\Tools\Topic;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteTopicTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'delete_topic';

    /**
     * The tool's description.
     */
    protected string $description = 'Delete a topic. Cannot delete the last topic in a project.';

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
                    'description' => 'The UUID of the topic to delete',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
            ],
            'required' => ['topic_id'],
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

        $topicId = $request->input('topic_id');
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$topicId) {
            return Response::error('topic_id is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        try {
            $result = $service->deleteTopic($user, $companyId, $topicId);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to delete topic');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to delete topic: ' . $e->getMessage());
        }
    }
}

