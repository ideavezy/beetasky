<?php

namespace Database\Seeders;

use App\Models\AiSkill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkillsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Register existing MCP tools as system skills
        // NOTE: Only truly required fields are in 'required'. Defaults are documented in descriptions.
        $mcpSkills = [
            // Task Tools
            [
                'category' => 'project_management',
                'name' => 'Create Task',
                'slug' => 'create_task',
                'description' => 'Create a new task. Only title is required, other fields have sensible defaults.',
                'icon' => 'PlusCircleIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Task\\CreateTaskTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'The title of the task (REQUIRED)'],
                        'topic_id' => ['type' => 'string', 'description' => 'The UUID of the topic. If not provided, uses first available topic in context.'],
                        'description' => ['type' => 'string', 'description' => 'Brief description. Optional.'],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'default' => 'medium', 'description' => 'Priority level. Defaults to medium.'],
                        'due_date' => ['type' => 'string', 'description' => 'Due date (YYYY-MM-DD). Optional.'],
                    ],
                    'required' => ['title'],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'Update Task',
                'slug' => 'update_task',
                'description' => 'Update a task. Only include fields you want to change.',
                'icon' => 'PencilSquareIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Task\\UpdateTaskTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'string', 'description' => 'The UUID of the task to update (REQUIRED)'],
                        'title' => ['type' => 'string', 'description' => 'New title. Optional.'],
                        'description' => ['type' => 'string', 'description' => 'New description. Optional.'],
                        'status' => ['type' => 'string', 'enum' => ['new', 'working', 'question', 'on_hold', 'in_review', 'done', 'canceled'], 'description' => 'New status. Optional.'],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'description' => 'New priority. Optional.'],
                        'due_date' => ['type' => 'string', 'description' => 'New due date. Optional.'],
                        'completed' => ['type' => 'boolean', 'description' => 'Mark as complete (true/false). Optional.'],
                    ],
                    'required' => ['task_id'],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'Delete Task',
                'slug' => 'delete_task',
                'description' => 'Delete a task',
                'icon' => 'TrashIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Task\\DeleteTaskTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'string', 'description' => 'The UUID of the task to delete'],
                    ],
                    'required' => ['task_id'],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'Get Task',
                'slug' => 'get_task',
                'description' => 'Get details of a specific task',
                'icon' => 'DocumentTextIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Task\\GetTaskTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'string', 'description' => 'The UUID of the task'],
                    ],
                    'required' => ['task_id'],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'List Tasks',
                'slug' => 'list_tasks',
                'description' => 'List tasks with optional filters',
                'icon' => 'ListBulletIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Task\\ListTasksTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'topic_id' => ['type' => 'string', 'description' => 'Filter by topic'],
                        'project_id' => ['type' => 'string', 'description' => 'Filter by project'],
                        'status' => ['type' => 'string', 'description' => 'Filter by status'],
                        'priority' => ['type' => 'string', 'description' => 'Filter by priority'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'Assign Task',
                'slug' => 'assign_task',
                'description' => 'Assign a user to a task',
                'icon' => 'UserPlusIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Task\\AssignTaskTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'string', 'description' => 'The UUID of the task'],
                        'assignee_id' => ['type' => 'string', 'description' => 'The UUID of the user to assign'],
                    ],
                    'required' => ['task_id'],
                ],
            ],

            // Project Tools
            [
                'category' => 'project_management',
                'name' => 'Create Project',
                'slug' => 'create_project',
                'description' => 'Create a new project',
                'icon' => 'FolderPlusIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Project\\CreateProjectTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Project name'],
                        'description' => ['type' => 'string', 'description' => 'Project description'],
                        'due_date' => ['type' => 'string', 'description' => 'Due date (YYYY-MM-DD format)'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'Update Project',
                'slug' => 'update_project',
                'description' => 'Update project details',
                'icon' => 'FolderIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Project\\UpdateProjectTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => ['type' => 'string', 'description' => 'The UUID of the project'],
                        'name' => ['type' => 'string', 'description' => 'New project name'],
                        'description' => ['type' => 'string', 'description' => 'New description'],
                        'status' => ['type' => 'string', 'enum' => ['planning', 'active', 'on_hold', 'completed', 'cancelled']],
                    ],
                    'required' => ['project_id'],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'List Projects',
                'slug' => 'list_projects',
                'description' => 'List all projects',
                'icon' => 'FolderOpenIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Project\\ListProjectsTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'Filter by status'],
                    ],
                    'required' => [],
                ],
            ],

            // Topic Tools
            [
                'category' => 'project_management',
                'name' => 'Create Topic',
                'slug' => 'create_topic',
                'description' => 'Create a new topic/section in a project',
                'icon' => 'RectangleGroupIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Topic\\CreateTopicTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => ['type' => 'string', 'description' => 'The UUID of the project'],
                        'name' => ['type' => 'string', 'description' => 'Topic name'],
                        'description' => ['type' => 'string', 'description' => 'Topic description'],
                    ],
                    'required' => ['project_id', 'name'],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'List Topics',
                'slug' => 'list_topics',
                'description' => 'List topics in a project',
                'icon' => 'Squares2X2Icon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Topic\\ListTopicsTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => ['type' => 'string', 'description' => 'The UUID of the project'],
                    ],
                    'required' => ['project_id'],
                ],
            ],

            // Comment Tools
            [
                'category' => 'project_management',
                'name' => 'Create Comment',
                'slug' => 'create_comment',
                'description' => 'Add a comment to a task',
                'icon' => 'ChatBubbleLeftIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Comment\\CreateCommentTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'string', 'description' => 'The UUID of the task'],
                        'content' => ['type' => 'string', 'description' => 'Comment content'],
                    ],
                    'required' => ['task_id', 'content'],
                ],
            ],
            [
                'category' => 'project_management',
                'name' => 'List Comments',
                'slug' => 'list_comments',
                'description' => 'List comments on a task',
                'icon' => 'ChatBubbleLeftRightIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Comment\\ListCommentsTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'string', 'description' => 'The UUID of the task'],
                    ],
                    'required' => ['task_id'],
                ],
            ],
        ];

        foreach ($mcpSkills as $skillData) {
            // Generate function definition
            $skillData['function_definition'] = [
                'type' => 'function',
                'function' => [
                    'name' => $skillData['slug'],
                    'description' => $skillData['description'],
                    'parameters' => $skillData['input_schema'],
                ],
            ];

            // Use direct insert/update to avoid casting issues with PostgreSQL
            $existing = AiSkill::where('slug', $skillData['slug'])->first();
            
            if ($existing) {
                $existing->update([
                    'category' => $skillData['category'],
                    'name' => $skillData['name'],
                    'description' => $skillData['description'],
                    'icon' => $skillData['icon'],
                    'skill_type' => $skillData['skill_type'],
                    'mcp_tool_class' => $skillData['mcp_tool_class'],
                    'input_schema' => $skillData['input_schema'],
                    'function_definition' => $skillData['function_definition'],
                    'is_system' => true,
                    'is_active' => true,
                ]);
            } else {
                AiSkill::create([
                    'category' => $skillData['category'],
                    'name' => $skillData['name'],
                    'slug' => $skillData['slug'],
                    'description' => $skillData['description'],
                    'icon' => $skillData['icon'],
                    'skill_type' => $skillData['skill_type'],
                    'mcp_tool_class' => $skillData['mcp_tool_class'],
                    'input_schema' => $skillData['input_schema'],
                    'function_definition' => $skillData['function_definition'],
                    'is_system' => true,
                    'is_active' => true,
                    'secret_fields' => [],
                    'api_config' => [],
                    'composite_steps' => [],
                ]);
            }
        }

        $this->command->info('Seeded ' . count($mcpSkills) . ' MCP tool skills.');

        // Seed example API skill templates (inactive by default)
        $exampleSkills = [
            [
                'category' => 'integration',
                'name' => 'Send Slack Message (Template)',
                'slug' => 'send_slack_message_template',
                'description' => 'Template: Send a message to a Slack channel. Configure with your Slack webhook URL.',
                'icon' => 'ChatBubbleOvalLeftEllipsisIcon',
                'skill_type' => 'api_call',
                'api_config' => [
                    'method' => 'POST',
                    'url' => '{{webhook_url}}',
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body_template' => [
                        'text' => '{{message}}',
                        'channel' => '{{channel}}',
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string', 'description' => 'Message to send'],
                        'channel' => ['type' => 'string', 'description' => 'Channel name (optional)'],
                    ],
                    'required' => ['message'],
                ],
                'secret_fields' => ['webhook_url'],
                'is_active' => false, // Template - needs configuration
            ],
            [
                'category' => 'integration',
                'name' => 'HTTP GET Request (Template)',
                'slug' => 'http_get_template',
                'description' => 'Template: Make an HTTP GET request to any API endpoint.',
                'icon' => 'ArrowDownTrayIcon',
                'skill_type' => 'api_call',
                'api_config' => [
                    'method' => 'GET',
                    'url' => '{{api_url}}',
                    'headers' => [
                        'Authorization' => 'Bearer {{api_key}}',
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'api_url' => ['type' => 'string', 'description' => 'API endpoint URL'],
                    ],
                    'required' => ['api_url'],
                ],
                'secret_fields' => ['api_key'],
                'is_active' => false, // Template - needs configuration
            ],
            [
                'category' => 'integration',
                'name' => 'HTTP POST Request (Template)',
                'slug' => 'http_post_template',
                'description' => 'Template: Make an HTTP POST request to any API endpoint.',
                'icon' => 'ArrowUpTrayIcon',
                'skill_type' => 'api_call',
                'api_config' => [
                    'method' => 'POST',
                    'url' => '{{api_url}}',
                    'headers' => [
                        'Authorization' => 'Bearer {{api_key}}',
                        'Content-Type' => 'application/json',
                    ],
                    'body_template' => [
                        'data' => '{{payload}}',
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'api_url' => ['type' => 'string', 'description' => 'API endpoint URL'],
                        'payload' => ['type' => 'string', 'description' => 'JSON payload to send'],
                    ],
                    'required' => ['api_url'],
                ],
                'secret_fields' => ['api_key'],
                'is_active' => false, // Template - needs configuration
            ],
            [
                'category' => 'integration',
                'name' => 'Webhook Trigger (Template)',
                'slug' => 'webhook_trigger_template',
                'description' => 'Template: Trigger a webhook with custom payload.',
                'icon' => 'BoltIcon',
                'skill_type' => 'webhook',
                'api_config' => [
                    'url' => '{{webhook_url}}',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Webhook-Secret' => '{{webhook_secret}}',
                    ],
                    'payload_template' => [
                        'event' => '{{event_type}}',
                        'data' => '{{event_data}}',
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_type' => ['type' => 'string', 'description' => 'Type of event'],
                        'event_data' => ['type' => 'string', 'description' => 'Event data (JSON)'],
                    ],
                    'required' => ['event_type'],
                ],
                'secret_fields' => ['webhook_url', 'webhook_secret'],
                'is_active' => false, // Template - needs configuration
            ],
        ];

        foreach ($exampleSkills as $skillData) {
            $skillData['function_definition'] = [
                'type' => 'function',
                'function' => [
                    'name' => $skillData['slug'],
                    'description' => $skillData['description'],
                    'parameters' => $skillData['input_schema'],
                ],
            ];

            $existing = AiSkill::where('slug', $skillData['slug'])->first();
            
            if ($existing) {
                $existing->update([
                    'category' => $skillData['category'],
                    'name' => $skillData['name'],
                    'description' => $skillData['description'],
                    'icon' => $skillData['icon'],
                    'skill_type' => $skillData['skill_type'],
                    'api_config' => $skillData['api_config'],
                    'input_schema' => $skillData['input_schema'],
                    'function_definition' => $skillData['function_definition'],
                    'secret_fields' => $skillData['secret_fields'],
                    'is_system' => true,
                    'is_active' => $skillData['is_active'],
                ]);
            } else {
                AiSkill::create([
                    'category' => $skillData['category'],
                    'name' => $skillData['name'],
                    'slug' => $skillData['slug'],
                    'description' => $skillData['description'],
                    'icon' => $skillData['icon'],
                    'skill_type' => $skillData['skill_type'],
                    'api_config' => $skillData['api_config'],
                    'input_schema' => $skillData['input_schema'],
                    'function_definition' => $skillData['function_definition'],
                    'secret_fields' => $skillData['secret_fields'],
                    'is_system' => true,
                    'is_active' => $skillData['is_active'],
                    'composite_steps' => [],
                ]);
            }
        }

        $this->command->info('Seeded ' . count($exampleSkills) . ' template skills.');
    }
}
