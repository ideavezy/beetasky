<?php

namespace App\Mcp\Tools\Topic;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTopicTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'update_topic';

    /**
     * The tool's description.
     */
    protected string $description = 'Update a topic (section) within a project.';

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
                    'description' => 'The UUID of the topic to update',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'The new name of the topic',
                    'maxLength' => 255,
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'The new description of the topic',
                ],
                'color' => [
                    'type' => 'string',
                    'description' => 'A new color for the topic',
                ],
                'position' => [
                    'type' => 'integer',
                    'description' => 'The new position of the topic',
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

        $data = array_filter([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'color' => $request->input('color'),
            'position' => $request->input('position'),
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return Response::error('At least one field to update is required');
        }

        try {
            $result = $service->updateTopic($user, $companyId, $topicId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to update topic');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'topic' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to update topic: ' . $e->getMessage());
        }
    }
}

