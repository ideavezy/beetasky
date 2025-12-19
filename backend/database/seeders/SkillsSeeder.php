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
                'description' => 'Update a task. Find by task_id OR search by title. If multiple matches, returns list for user to choose. Use selection param when user picks a number.',
                'icon' => 'PencilSquareIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Task\\UpdateTaskTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'string', 'description' => 'The UUID of the task. Use when you have the exact ID.'],
                        'search' => ['type' => 'string', 'description' => 'Search for task by title/description. Use when you don\'t have task_id.'],
                        'selection' => ['type' => 'integer', 'description' => 'When user picks a number from previous search results (e.g., "1" or "the first one"), pass that number here WITH the original search term.'],
                        'title' => ['type' => 'string', 'description' => 'New title. Optional.'],
                        'description' => ['type' => 'string', 'description' => 'New description. Optional.'],
                        'status' => ['type' => 'string', 'enum' => ['new', 'working', 'question', 'on_hold', 'in_review', 'done', 'canceled'], 'description' => 'New status. Optional.'],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'description' => 'New priority. Optional.'],
                        'due_date' => ['type' => 'string', 'description' => 'New due date. Optional.'],
                        'completed' => ['type' => 'boolean', 'description' => 'Mark as complete (true/false). Optional.'],
                    ],
                    'required' => [],
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
            [
                'category' => 'project_management',
                'name' => 'Search Tasks',
                'slug' => 'search_tasks',
                'description' => 'Search for tasks by title or description across all projects. Use this to find tasks when you only know part of the name.',
                'icon' => 'MagnifyingGlassIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Task\\SearchTasksTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string', 'description' => 'Search term to find in task title or description (REQUIRED)'],
                        'status' => ['type' => 'string', 'description' => 'Filter by status', 'enum' => ['new', 'working', 'question', 'on_hold', 'in_review', 'done', 'canceled']],
                        'priority' => ['type' => 'string', 'description' => 'Filter by priority', 'enum' => ['low', 'medium', 'high', 'urgent']],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum results (default: 20)'],
                    ],
                    'required' => ['search'],
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

            // CRM Contact Tools
            [
                'category' => 'crm',
                'name' => 'Create Contact',
                'slug' => 'create_contact',
                'description' => 'Create a new contact (lead, prospect, customer, vendor, or partner). Only name is required.',
                'icon' => 'UserPlusIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\CreateContactTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'full_name' => ['type' => 'string', 'description' => 'Full name of the contact (REQUIRED)'],
                        'name' => ['type' => 'string', 'description' => 'Alias for full_name'],
                        'email' => ['type' => 'string', 'description' => 'Email address'],
                        'phone' => ['type' => 'string', 'description' => 'Phone number'],
                        'organization' => ['type' => 'string', 'description' => 'Company or organization name'],
                        'company' => ['type' => 'string', 'description' => 'Alias for organization'],
                        'job_title' => ['type' => 'string', 'description' => 'Job title or role'],
                        'relation_type' => ['type' => 'string', 'enum' => ['lead', 'prospect', 'customer', 'vendor', 'partner'], 'default' => 'lead', 'description' => 'Type of contact (default: lead)'],
                        'source' => ['type' => 'string', 'enum' => ['website', 'referral', 'social_media', 'advertisement', 'cold_call', 'email_campaign', 'trade_show', 'partner', 'ai_chat', 'manual', 'other'], 'default' => 'ai_chat', 'description' => 'How this contact was acquired'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'List Contacts',
                'slug' => 'list_contacts',
                'description' => 'List and search contacts with optional filters.',
                'icon' => 'UsersIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\ListContactsTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string', 'description' => 'Search by name, email, or organization'],
                        'type' => ['type' => 'string', 'enum' => ['lead', 'prospect', 'customer', 'vendor', 'partner'], 'description' => 'Filter by contact type'],
                        'relation_type' => ['type' => 'string', 'description' => 'Alias for type filter'],
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'converted', 'lost'], 'description' => 'Filter by status'],
                        'assigned_to' => ['type' => 'string', 'description' => 'Filter by assignee (use "me" for current user)'],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum results (default: 10)'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'Get Contact',
                'slug' => 'get_contact',
                'description' => 'Get detailed information about a specific contact.',
                'icon' => 'UserCircleIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\GetContactTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'The UUID of the contact'],
                        'search' => ['type' => 'string', 'description' => 'Search for contact by name or email (if contact_id not provided)'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'Update Contact',
                'slug' => 'update_contact',
                'description' => 'Update contact information. Only include fields you want to change.',
                'icon' => 'PencilSquareIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\UpdateContactTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'The UUID of the contact to update'],
                        'search' => ['type' => 'string', 'description' => 'Search for contact by name or email (if contact_id not provided)'],
                        'full_name' => ['type' => 'string', 'description' => 'Updated full name'],
                        'email' => ['type' => 'string', 'description' => 'Updated email address'],
                        'phone' => ['type' => 'string', 'description' => 'Updated phone number'],
                        'organization' => ['type' => 'string', 'description' => 'Updated organization name'],
                        'job_title' => ['type' => 'string', 'description' => 'Updated job title'],
                        'relation_type' => ['type' => 'string', 'enum' => ['lead', 'prospect', 'customer', 'vendor', 'partner'], 'description' => 'Change contact type'],
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'converted', 'lost'], 'description' => 'Change status'],
                        'assigned_to' => ['type' => 'string', 'description' => 'User ID to assign contact to'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'Convert Lead',
                'slug' => 'convert_lead',
                'description' => 'Convert a lead or prospect to a customer.',
                'icon' => 'ArrowPathIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\ConvertLeadTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'The UUID of the lead to convert'],
                        'search' => ['type' => 'string', 'description' => 'Search for contact by name or email (if contact_id not provided)'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'Add Contact Note',
                'slug' => 'add_contact_note',
                'description' => 'Add a note or activity log to a contact.',
                'icon' => 'DocumentPlusIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\AddContactNoteTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'The UUID of the contact'],
                        'search' => ['type' => 'string', 'description' => 'Search for contact by name or email (if contact_id not provided)'],
                        'content' => ['type' => 'string', 'description' => 'The note content (REQUIRED)'],
                        'note' => ['type' => 'string', 'description' => 'Alias for content'],
                        'type' => ['type' => 'string', 'enum' => ['note', 'call', 'email', 'meeting', 'task'], 'default' => 'note', 'description' => 'Type of activity'],
                        'is_pinned' => ['type' => 'boolean', 'description' => 'Pin this note to the top'],
                    ],
                    'required' => ['content'],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'Score Lead',
                'slug' => 'score_lead',
                'description' => 'Calculate lead scores based on source quality, profile completeness, activity, and engagement recency.',
                'icon' => 'ChartBarIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\ScoreLeadTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'Score a specific contact by UUID'],
                        'search' => ['type' => 'string', 'description' => 'Search for contact by name or email'],
                        'score_all' => ['type' => 'boolean', 'description' => 'Score all leads in the company'],
                    ],
                    'required' => [],
                ],
            ],

            // Deal Tools (Sales Pipeline)
            [
                'category' => 'crm',
                'name' => 'Create Deal',
                'slug' => 'create_deal',
                'description' => 'Create a new sales deal/opportunity in the pipeline.',
                'icon' => 'CurrencyDollarIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Deal\\CreateDealTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Title of the deal (REQUIRED)'],
                        'value' => ['type' => 'number', 'description' => 'Deal value/amount'],
                        'currency' => ['type' => 'string', 'default' => 'USD', 'description' => 'Currency code'],
                        'stage' => ['type' => 'string', 'enum' => ['qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'], 'default' => 'qualification', 'description' => 'Pipeline stage'],
                        'contact_id' => ['type' => 'string', 'description' => 'UUID of the associated contact'],
                        'expected_close_date' => ['type' => 'string', 'description' => 'Expected close date (YYYY-MM-DD)'],
                        'description' => ['type' => 'string', 'description' => 'Deal description'],
                    ],
                    'required' => ['title'],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'Update Deal',
                'slug' => 'update_deal',
                'description' => 'Update a deal, move it to a new stage, or mark it as won/lost.',
                'icon' => 'ArrowPathRoundedSquareIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Deal\\UpdateDealTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'deal_id' => ['type' => 'string', 'description' => 'The UUID of the deal (REQUIRED)'],
                        'title' => ['type' => 'string', 'description' => 'New deal title'],
                        'value' => ['type' => 'number', 'description' => 'New deal value'],
                        'stage' => ['type' => 'string', 'enum' => ['qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'], 'description' => 'Move to a new stage'],
                        'mark_won' => ['type' => 'boolean', 'description' => 'Mark the deal as won'],
                        'mark_lost' => ['type' => 'boolean', 'description' => 'Mark the deal as lost'],
                        'lost_reason' => ['type' => 'string', 'description' => 'Reason for losing the deal'],
                        'expected_close_date' => ['type' => 'string', 'description' => 'New expected close date'],
                    ],
                    'required' => ['deal_id'],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'List Deals',
                'slug' => 'list_deals',
                'description' => 'List deals in the sales pipeline with optional filters.',
                'icon' => 'ViewColumnsIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Deal\\ListDealsTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'stage' => ['type' => 'string', 'enum' => ['qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'], 'description' => 'Filter by stage'],
                        'open_only' => ['type' => 'boolean', 'default' => true, 'description' => 'Show only open deals'],
                        'assigned_to' => ['type' => 'string', 'description' => 'Filter by assignee (use "me" for current user)'],
                        'contact_id' => ['type' => 'string', 'description' => 'Filter by associated contact'],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum results (default: 20)'],
                    ],
                    'required' => [],
                ],
            ],

            // Reminder Tools
            [
                'category' => 'crm',
                'name' => 'Create Reminder',
                'slug' => 'create_reminder',
                'description' => 'Create a follow-up reminder for a contact. Supports natural language dates like "tomorrow", "next Tuesday".',
                'icon' => 'BellIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\CreateReminderTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'The UUID of the contact'],
                        'search' => ['type' => 'string', 'description' => 'Search for contact by name or email'],
                        'title' => ['type' => 'string', 'description' => 'Title of the reminder (REQUIRED)'],
                        'remind_at' => ['type' => 'string', 'description' => 'When to remind (REQUIRED). Supports dates or natural language'],
                        'date' => ['type' => 'string', 'description' => 'Alias for remind_at'],
                        'type' => ['type' => 'string', 'enum' => ['follow_up', 'call', 'email', 'meeting', 'task'], 'default' => 'follow_up', 'description' => 'Type of reminder'],
                        'description' => ['type' => 'string', 'description' => 'Additional details'],
                    ],
                    'required' => ['title', 'remind_at'],
                ],
            ],
            [
                'category' => 'crm',
                'name' => 'List Reminders',
                'slug' => 'list_reminders',
                'description' => 'List upcoming follow-up reminders for your contacts.',
                'icon' => 'BellAlertIcon',
                'skill_type' => 'mcp_tool',
                'mcp_tool_class' => 'App\\Mcp\\Tools\\Contact\\ListRemindersTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'Filter by specific contact'],
                        'type' => ['type' => 'string', 'enum' => ['follow_up', 'call', 'email', 'meeting', 'task'], 'description' => 'Filter by type'],
                        'pending_only' => ['type' => 'boolean', 'default' => true, 'description' => 'Show only pending reminders'],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum results (default: 20)'],
                    ],
                    'required' => [],
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
