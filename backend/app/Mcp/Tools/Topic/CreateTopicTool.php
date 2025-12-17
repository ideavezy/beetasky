<?php

namespace App\Mcp\Tools\Topic;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTopicTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'create_topic';

    /**
     * The tool's description.
     */
    protected string $description = 'Create a new topic (section) within a project to organize tasks.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the project',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'The name of the topic',
                    'maxLength' => 255,
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'A description of the topic',
                ],
                'color' => [
                    'type' => 'string',
                    'description' => 'A color for the topic (e.g., "blue", "#FF0000")',
                ],
                'position' => [
                    'type' => 'integer',
                    'description' => 'The position of the topic in the project (0-indexed)',
                ],
            ],
            'required' => ['project_id', 'name'],
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

        $projectId = $request->input('project_id');
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');
        $name = $request->input('name');

        if (!$projectId) {
            return Response::error('project_id is required');
        }

        if (!$name) {
            return Response::error('Topic name is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $data = [
            'name' => $name,
            'description' => $request->input('description'),
            'color' => $request->input('color'),
            'position' => $request->input('position'),
        ];

        try {
            $result = $service->createTopic($user, $companyId, $projectId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to create topic');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'topic' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to create topic: ' . $e->getMessage());
        }
    }
}

