<?php

namespace App\Mcp\Tools\Project;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateProjectTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'create_project';

    /**
     * The tool's description.
     */
    protected string $description = 'Create a new project. A default "General" topic will be created automatically.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'The name of the project',
                    'maxLength' => 255,
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'A description of the project',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Initial project status (default: active)',
                    'enum' => ['planning', 'active', 'on_hold', 'completed', 'cancelled'],
                ],
                'start_date' => [
                    'type' => 'string',
                    'description' => 'Project start date (YYYY-MM-DD format)',
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Project due date (YYYY-MM-DD format)',
                ],
                'budget' => [
                    'type' => 'number',
                    'description' => 'Project budget amount',
                ],
            ],
            'required' => ['name'],
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

        $name = $request->input('name');

        if (!$name) {
            return Response::error('Project name is required');
        }

        $data = [
            'name' => $name,
            'description' => $request->input('description'),
            'status' => $request->input('status') ?? 'active',
            'start_date' => $request->input('start_date'),
            'due_date' => $request->input('due_date'),
            'budget' => $request->input('budget'),
        ];

        try {
            $result = $service->createProject($user, $companyId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to create project');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'project' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to create project: ' . $e->getMessage());
        }
    }
}

