<?php

namespace App\Mcp\Tools\Project;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateProjectTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'update_project';

    /**
     * The tool's description.
     */
    protected string $description = 'Update a project. Requires owner or admin role.';

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
                    'description' => 'The UUID of the project to update',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'The new name of the project',
                    'maxLength' => 255,
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'The new description of the project',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'The new project status',
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

        $data = array_filter([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'status' => $request->input('status'),
            'start_date' => $request->input('start_date'),
            'due_date' => $request->input('due_date'),
            'budget' => $request->input('budget'),
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return Response::error('At least one field to update is required');
        }

        try {
            $result = $service->updateProject($user, $companyId, $projectId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to update project');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'project' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to update project: ' . $e->getMessage());
        }
    }
}

