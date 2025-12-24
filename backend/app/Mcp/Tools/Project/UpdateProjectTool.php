<?php

namespace App\Mcp\Tools\Project;

use App\Models\Project;
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
    protected string $description = 'Update a project. Can find by project_id OR by searching name. Requires owner or admin role.';

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
                    'description' => 'The UUID of the project to update. Use this when you have the exact project ID.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search for project by name. Use this when you don\'t have the project_id.',
                ],
                'selection' => [
                    'type' => 'integer',
                    'description' => 'When user picks a number from a previous search result (e.g., "1" or "the first one"), pass that number here along with the original search term.',
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

        $projectId = $request->input('project_id');
        $searchQuery = $request->input('search');
        $selection = $request->input('selection');
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $foundProjectName = null;

        // If no project_id, try to find by search query
        if (!$projectId && $searchQuery) {
            $limit = $selection ? max(5, $selection) : 5;
            
            // Search for projects by name
            $projects = Project::forCompany($companyId)
                ->whereNull('deleted_at')
                ->where('name', 'ilike', '%' . $searchQuery . '%')
                ->limit($limit)
                ->get(['id', 'name', 'status', 'created_at']);

            if ($projects->isEmpty()) {
                return Response::text(json_encode([
                    'success' => false,
                    'status' => 'not_found',
                    'message' => "No project found matching: '{$searchQuery}'",
                    'suggestion' => 'Ask the user for more specific project name or different keywords.',
                    'action_required' => 'Ask user to clarify the project name or provide more details.',
                ], JSON_PRETTY_PRINT));
            }

            $matchCount = $projects->count();

            // If user provided a selection number, use that match directly
            if ($selection && $selection >= 1 && $selection <= $matchCount) {
                $selectedIndex = $selection - 1;
                $projectId = $projects[$selectedIndex]->id;
                $foundProjectName = $projects[$selectedIndex]->name;
            }
            // If multiple matches and no selection, ask user to clarify
            elseif ($matchCount > 1) {
                $projectList = $projects->map(function ($project, $index) {
                    return [
                        'number' => $index + 1,
                        'id' => $project->id,
                        'name' => $project->name,
                        'status' => $project->status,
                    ];
                })->toArray();

                $listItems = array_map(
                    fn($p) => "**{$p['number']})** {$p['name']} _({$p['status']})_",
                    $projectList
                );

                return Response::text(json_encode([
                    'success' => false,
                    'status' => 'multiple_matches',
                    'message' => "Found {$matchCount} projects matching '{$searchQuery}'",
                    'matches' => $projectList,
                    'action_required' => 'Ask the user which project they want to update. Present the numbered list and wait for their response.',
                    'example_followup' => "I found {$matchCount} projects matching that. Which one did you mean?\n\n" . implode("\n", $listItems),
                    'next_step' => 'When user responds with a number, call update_project again with the same search term plus selection parameter set to their number.',
                ], JSON_PRETTY_PRINT));
            }
            // Single match - proceed
            else {
                $projectId = $projects[0]->id;
                $foundProjectName = $projects[0]->name;
            }
        } elseif (!$projectId) {
            return Response::text(json_encode([
                'success' => false,
                'status' => 'missing_input',
                'message' => 'Either project_id or search query is required',
                'action_required' => 'Ask the user which project they want to update.',
            ], JSON_PRETTY_PRINT));
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

            $response = [
                'success' => true,
                'message' => $result['message'],
                'project' => $result['data'],
            ];

            // Add context about which project was found if we searched for it
            if ($foundProjectName) {
                $response['note'] = "Found and updated project: '{$foundProjectName}'";
            }

            return Response::text(json_encode($response, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to update project: ' . $e->getMessage());
        }
    }
}

