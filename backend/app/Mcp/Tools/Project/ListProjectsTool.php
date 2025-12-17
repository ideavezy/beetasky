<?php

namespace App\Mcp\Tools\Project;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListProjectsTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'list_projects';

    /**
     * The tool's description.
     */
    protected string $description = 'List all projects in a company that the user has access to.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter by project status',
                    'enum' => ['planning', 'active', 'on_hold', 'completed', 'cancelled'],
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search projects by name',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of projects to return (default: 50)',
                ],
            ],
            'required' => [],
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

        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $filters = array_filter([
            'status' => $request->input('status'),
            'search' => $request->input('search'),
            'limit' => $request->input('limit'),
        ], fn($v) => $v !== null);

        $result = $service->listProjects($user, $companyId, $filters);

        if (!$result['success']) {
            return Response::error($result['error'] ?? 'Failed to list projects');
        }

        return Response::text(json_encode($result['data'], JSON_PRETTY_PRINT));
    }
}

