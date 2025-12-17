<?php

namespace App\Mcp\Tools\Task;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTaskTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'create_task';

    /**
     * The tool's description.
     */
    protected string $description = 'Create a new task in a topic.';

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
                    'description' => 'The UUID of the topic to create the task in',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'The title of the task',
                    'maxLength' => 500,
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company. If not provided, uses X-Company-ID header.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'A brief description of the task',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Detailed content/instructions for the task (supports rich text)',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Initial task status (default: new)',
                    'enum' => ['new', 'working', 'question', 'on_hold', 'in_review', 'done', 'canceled'],
                ],
                'priority' => [
                    'type' => 'string',
                    'description' => 'Task priority (default: medium)',
                    'enum' => ['low', 'medium', 'high', 'urgent'],
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Due date for the task (YYYY-MM-DD or ISO 8601 format)',
                ],
            ],
            'required' => ['topic_id', 'title'],
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
        $title = $request->input('title');
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$topicId) {
            return Response::error('topic_id is required');
        }

        if (!$title) {
            return Response::error('Task title is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $data = [
            'title' => $title,
            'description' => $request->input('description'),
            'content' => $request->input('content'),
            'status' => $request->input('status') ?? 'new',
            'priority' => $request->input('priority') ?? 'medium',
            'due_date' => $request->input('due_date'),
        ];

        try {
            $result = $service->createTask($user, $companyId, $topicId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to create task');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'task' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to create task: ' . $e->getMessage());
        }
    }
}

