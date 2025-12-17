<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Comment\CreateCommentTool;
use App\Mcp\Tools\Comment\DeleteCommentTool;
use App\Mcp\Tools\Comment\ListCommentsTool;
use App\Mcp\Tools\Comment\UpdateCommentTool;
use App\Mcp\Tools\Company\CreateCompanyTool;
use App\Mcp\Tools\Company\GetCompanyTool;
use App\Mcp\Tools\Company\ListCompaniesTool;
use App\Mcp\Tools\Company\UpdateCompanyTool;
use App\Mcp\Tools\Project\CreateProjectTool;
use App\Mcp\Tools\Project\DeleteProjectTool;
use App\Mcp\Tools\Project\GetProjectTool;
use App\Mcp\Tools\Project\ListProjectsTool;
use App\Mcp\Tools\Project\UpdateProjectTool;
use App\Mcp\Tools\Task\AssignTaskTool;
use App\Mcp\Tools\Task\CreateTaskTool;
use App\Mcp\Tools\Task\DeleteTaskTool;
use App\Mcp\Tools\Task\GetTaskTool;
use App\Mcp\Tools\Task\ListTasksTool;
use App\Mcp\Tools\Task\UpdateTaskTool;
use App\Mcp\Tools\Topic\CreateTopicTool;
use App\Mcp\Tools\Topic\DeleteTopicTool;
use App\Mcp\Tools\Topic\ListTopicsTool;
use App\Mcp\Tools\Topic\UpdateTopicTool;
use Laravel\Mcp\Server;

/**
 * MCP Server for Project Management.
 * 
 * Exposes tools for managing companies, projects, topics, tasks, and comments
 * via the Model Context Protocol. Can be accessed by internal chat UI and
 * external AI clients.
 */
class ProjectManagementServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'BeetaSky Project Management';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<EOT
This server provides project management capabilities for BeetaSky CRM.

AVAILABLE OPERATIONS:

**Companies:**
- list_companies: Get all companies the user has access to
- get_company: Get details of a specific company
- create_company: Create a new company
- update_company: Update company details

**Projects:**
- list_projects: Get projects in a company (requires X-Company-ID header or company_id parameter)
- get_project: Get project details including topics and members
- create_project: Create a new project with a default "General" topic
- update_project: Update project details, status, dates
- delete_project: Delete a project (owner only)

**Topics:**
- list_topics: Get topics/sections within a project
- create_topic: Create a new topic to organize tasks
- update_topic: Update topic name, description, position
- delete_topic: Delete a topic (cannot delete the last topic)

**Tasks:**
- list_tasks: Get tasks, optionally filtered by topic, project, status, priority
- get_task: Get full task details including comments and assignees
- create_task: Create a new task in a topic
- update_task: Update task title, description, status, priority, due_date, or mark complete
- delete_task: Delete a task
- assign_task: Assign a user to a task

**Comments:**
- list_comments: Get comments on a task
- create_comment: Add a comment to a task
- update_comment: Edit your own comment
- delete_comment: Delete a comment (author or admin only)

IMPORTANT CONTEXT:
- All operations require authentication (Supabase JWT or API key)
- Most operations require X-Company-ID header to specify the company context
- Task statuses: new, working, question, on_hold, in_review, done, canceled
- Task priorities: low, medium, high, urgent
- Project statuses: planning, active, on_hold, completed, cancelled
EOT;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Company tools
        ListCompaniesTool::class,
        GetCompanyTool::class,
        CreateCompanyTool::class,
        UpdateCompanyTool::class,

        // Project tools
        ListProjectsTool::class,
        GetProjectTool::class,
        CreateProjectTool::class,
        UpdateProjectTool::class,
        DeleteProjectTool::class,

        // Topic tools
        ListTopicsTool::class,
        CreateTopicTool::class,
        UpdateTopicTool::class,
        DeleteTopicTool::class,

        // Task tools
        ListTasksTool::class,
        GetTaskTool::class,
        CreateTaskTool::class,
        UpdateTaskTool::class,
        DeleteTaskTool::class,
        AssignTaskTool::class,

        // Comment tools
        ListCommentsTool::class,
        CreateCommentTool::class,
        UpdateCommentTool::class,
        DeleteCommentTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}

