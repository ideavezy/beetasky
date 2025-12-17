<?php

namespace App\Mcp\Tools\Project;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetProjectTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'get_project';

    /**
     * The tool's description.
     */
    protected string $description = 'Get detailed information about a specific project, including topics and members.';

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
                    'description' => 'The UUID of the project to retrieve',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
            ],
            'required' => ['project_id'],
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

        if (!$projectId) {
            return Response::error('project_id is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $result = $service->getProject($user, $companyId, $projectId);

        if (!$result['success']) {
            return Response::error($result['error'] ?? 'Failed to get project');
        }

        return Response::text(json_encode($result['data'], JSON_PRETTY_PRINT));
    }
}

