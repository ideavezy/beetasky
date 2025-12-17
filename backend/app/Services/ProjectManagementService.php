<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\TaskAssignment;
use App\Models\TaskComment;
use App\Models\TaskNotificationEvent;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserPreset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service class for project management operations.
 * Used by both REST API controllers and MCP tools.
 */
class ProjectManagementService
{
    // ========================================
    // COMPANY OPERATIONS
    // ========================================

    /**
     * List companies for a user.
     */
    public function listCompanies(User $user): array
    {
        $companies = $user->companies()
            ->orderBy('name')
            ->get()
            ->map(fn($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo_url' => $company->logo_url,
                'billing_status' => $company->billing_status,
                'role' => $company->pivot->role_in_company,
                'is_active' => $company->pivot->is_active,
                'created_at' => $company->created_at?->toIso8601String(),
            ]);

        return ['success' => true, 'data' => $companies->toArray()];
    }

    /**
     * Get a specific company.
     */
    public function getCompany(User $user, string $companyId): array
    {
        $company = $user->companies()->find($companyId);

        if (!$company) {
            return ['success' => false, 'error' => 'Company not found'];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo_url' => $company->logo_url,
                'billing_status' => $company->billing_status,
                'billing_cycle' => $company->billing_cycle,
                'settings' => $company->settings,
                'role' => $company->pivot->role_in_company,
                'is_active' => $company->pivot->is_active,
                'created_at' => $company->created_at?->toIso8601String(),
                'updated_at' => $company->updated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Create a new company.
     */
    public function createCompany(User $user, array $data): array
    {
        // Generate unique slug
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $company = Company::create([
            'name' => $data['name'],
            'slug' => $slug,
            'owner_id' => $user->id,
            'billing_status' => 'trial',
            'billing_cycle' => 'monthly',
        ]);

        // Add the user as owner
        DB::table('company_user')->insert([
            'id' => Str::uuid()->toString(),
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_in_company' => 'owner',
            'permissions' => json_encode(['*']),
            'joined_at' => now(),
        ]);

        // Set as default company
        $preset = UserPreset::firstOrCreate(
            ['user_id' => $user->id],
            ['settings' => []]
        );
        $preset->update(['default_company_id' => $company->id]);

        return [
            'success' => true,
            'message' => 'Company created successfully',
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'role' => 'owner',
            ],
        ];
    }

    /**
     * Update a company.
     */
    public function updateCompany(User $user, string $companyId, array $data): array
    {
        $company = $user->companies()
            ->wherePivotIn('role_in_company', ['owner', 'manager'])
            ->find($companyId);

        if (!$company) {
            return ['success' => false, 'error' => 'Company not found or insufficient permissions'];
        }

        $company->update(array_filter([
            'name' => $data['name'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'settings' => $data['settings'] ?? null,
        ], fn($v) => $v !== null));

        return [
            'success' => true,
            'message' => 'Company updated successfully',
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ];
    }

    // ========================================
    // PROJECT OPERATIONS
    // ========================================

    /**
     * List projects for a user in a company.
     */
    public function listProjects(User $user, string $companyId, array $filters = []): array
    {
        $query = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('status', 'active');
            })
            ->with(['members.user', 'creator'])
            ->withCount(['tasks', 'topics']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        $projects = $query->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 50)
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'code' => $project->code,
                'start_date' => $project->start_date,
                'due_date' => $project->due_date,
                'tasks_count' => $project->tasks_count,
                'topics_count' => $project->topics_count,
                'created_at' => $project->created_at?->toIso8601String(),
            ]);

        return ['success' => true, 'data' => $projects->toArray()];
    }

    /**
     * Get a specific project.
     */
    public function getProject(User $user, string $companyId, string $projectId): array
    {
        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with(['members.user', 'topics', 'creator'])
            ->withCount(['tasks', 'topics'])
            ->find($projectId);

        if (!$project) {
            return ['success' => false, 'error' => 'Project not found or access denied'];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'code' => $project->code,
                'start_date' => $project->start_date,
                'due_date' => $project->due_date,
                'budget' => $project->budget,
                'ai_enabled' => $project->ai_enabled,
                'tasks_count' => $project->tasks_count,
                'topics_count' => $project->topics_count,
                'topics' => $project->topics->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'position' => $t->position,
                ]),
                'members' => $project->members->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->first_name . ' ' . ($m->last_name ?? ''),
                    'role' => $m->pivot->role,
                ]),
                'created_at' => $project->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Create a new project.
     */
    public function createProject(User $user, string $companyId, array $data): array
    {
        $result = DB::transaction(function () use ($user, $companyId, $data) {
            $project = Project::create([
                'company_id' => $companyId,
                'created_by' => $user->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'code' => $data['code'] ?? null,
                'status' => $data['status'] ?? 'active',
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'budget' => $data['budget'] ?? null,
                'ai_enabled' => $data['ai_enabled'] ?? false,
                'contact_id' => $data['contact_id'] ?? null,
            ]);

            // Add creator as owner
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'company_id' => $companyId,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            // Create default "General" topic
            $topic = Topic::create([
                'project_id' => $project->id,
                'company_id' => $companyId,
                'name' => 'General',
                'description' => 'Default topic for general tasks',
                'position' => 0,
            ]);

            TaskActivityLog::log(
                'create',
                "Created project: {$project->name}",
                $project,
                null,
                $project->toArray(),
                $user->id,
                $companyId
            );

            return ['project' => $project, 'topic' => $topic];
        });

        return [
            'success' => true,
            'message' => 'Project created successfully',
            'data' => [
                'id' => $result['project']->id,
                'name' => $result['project']->name,
                'status' => $result['project']->status,
                'default_topic_id' => $result['topic']->id,
            ],
        ];
    }

    /**
     * Update a project.
     */
    public function updateProject(User $user, string $companyId, string $projectId, array $data): array
    {
        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereIn('role', ['owner', 'admin']);
            })
            ->find($projectId);

        if (!$project) {
            return ['success' => false, 'error' => 'Project not found or access denied'];
        }

        $oldValues = $project->toArray();
        $project->update(array_filter($data, fn($v) => $v !== null));

        TaskActivityLog::log(
            'update',
            "Updated project: {$project->name}",
            $project,
            $oldValues,
            $project->toArray(),
            $user->id,
            $companyId
        );

        return [
            'success' => true,
            'message' => 'Project updated successfully',
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
        ];
    }

    /**
     * Delete a project.
     */
    public function deleteProject(User $user, string $companyId, string $projectId): array
    {
        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('role', 'owner');
            })
            ->find($projectId);

        if (!$project) {
            return ['success' => false, 'error' => 'Project not found or access denied'];
        }

        DB::transaction(function () use ($project, $user, $companyId) {
            TaskActivityLog::log(
                'delete',
                "Deleted project: {$project->name}",
                $project,
                $project->toArray(),
                null,
                $user->id,
                $companyId
            );

            $project->delete();
        });

        return ['success' => true, 'message' => 'Project deleted successfully'];
    }

    // ========================================
    // TOPIC OPERATIONS
    // ========================================

    /**
     * List topics for a project.
     */
    public function listTopics(User $user, string $companyId, string $projectId): array
    {
        $project = Project::forCompany($companyId)
            ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->find($projectId);

        if (!$project) {
            return ['success' => false, 'error' => 'Project not found or access denied'];
        }

        $topics = Topic::where('project_id', $projectId)
            ->withCount('tasks')
            ->orderBy('position')
            ->get()
            ->map(fn($topic) => [
                'id' => $topic->id,
                'name' => $topic->name,
                'description' => $topic->description,
                'position' => $topic->position,
                'color' => $topic->color,
                'tasks_count' => $topic->tasks_count,
            ]);

        return ['success' => true, 'data' => $topics->toArray()];
    }

    /**
     * Create a topic.
     */
    public function createTopic(User $user, string $companyId, string $projectId, array $data): array
    {
        $project = Project::forCompany($companyId)
            ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->find($projectId);

        if (!$project) {
            return ['success' => false, 'error' => 'Project not found or access denied'];
        }

        $maxPosition = Topic::where('project_id', $projectId)->max('position') ?? -1;

        $topic = Topic::create([
            'project_id' => $projectId,
            'company_id' => $companyId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'position' => $data['position'] ?? $maxPosition + 1,
            'color' => $data['color'] ?? null,
        ]);

        TaskActivityLog::log(
            'create',
            "Created topic: {$topic->name}",
            $project,
            null,
            $topic->toArray(),
            $user->id,
            $companyId,
            $topic
        );

        return [
            'success' => true,
            'message' => 'Topic created successfully',
            'data' => [
                'id' => $topic->id,
                'name' => $topic->name,
                'position' => $topic->position,
            ],
        ];
    }

    /**
     * Update a topic.
     */
    public function updateTopic(User $user, string $companyId, string $topicId, array $data): array
    {
        $topic = Topic::forCompany($companyId)
            ->with('project')
            ->find($topicId);

        if (!$topic || !$topic->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Topic not found or access denied'];
        }

        $oldValues = $topic->toArray();
        $topic->update(array_filter($data, fn($v) => $v !== null));

        return [
            'success' => true,
            'message' => 'Topic updated successfully',
            'data' => [
                'id' => $topic->id,
                'name' => $topic->name,
                'position' => $topic->position,
            ],
        ];
    }

    /**
     * Delete a topic.
     */
    public function deleteTopic(User $user, string $companyId, string $topicId): array
    {
        $topic = Topic::forCompany($companyId)
            ->with('project')
            ->find($topicId);

        if (!$topic || !$topic->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Topic not found or access denied'];
        }

        // Check if it's the last topic
        $topicCount = Topic::where('project_id', $topic->project_id)->count();
        if ($topicCount <= 1) {
            return ['success' => false, 'error' => 'Cannot delete the last topic in a project'];
        }

        $topic->delete();

        return ['success' => true, 'message' => 'Topic deleted successfully'];
    }

    // ========================================
    // TASK OPERATIONS
    // ========================================

    /**
     * List tasks for a topic or project.
     */
    public function listTasks(User $user, string $companyId, ?string $topicId = null, ?string $projectId = null, array $filters = []): array
    {
        $query = Task::forCompany($companyId)
            ->with(['assignedUsers', 'topic'])
            ->withCount('comments');

        if ($topicId) {
            $topic = Topic::forCompany($companyId)->with('project')->find($topicId);
            if (!$topic || !$topic->project->hasMember($user)) {
                return ['success' => false, 'error' => 'Topic not found or access denied'];
            }
            $query->where('topic_id', $topicId);
        } elseif ($projectId) {
            $project = Project::forCompany($companyId)
                ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
                ->find($projectId);
            if (!$project) {
                return ['success' => false, 'error' => 'Project not found or access denied'];
            }
            $query->where('project_id', $projectId);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->whereHas('assignedUsers', fn($q) => $q->where('user_id', $filters['assigned_to']));
        }

        $tasks = $query->orderBy('order')
            ->limit($filters['limit'] ?? 100)
            ->get()
            ->map(fn($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'completed' => $task->completed,
                'due_date' => $task->due_date,
                'order' => $task->order,
                'topic_id' => $task->topic_id,
                'topic_name' => $task->topic?->name,
                'comments_count' => $task->comments_count,
                'assignees' => $task->assignedUsers->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->first_name . ' ' . ($u->last_name ?? ''),
                ]),
                'created_at' => $task->created_at?->toIso8601String(),
            ]);

        return ['success' => true, 'data' => $tasks->toArray()];
    }

    /**
     * Get a specific task.
     */
    public function getTask(User $user, string $companyId, string $taskId): array
    {
        $task = Task::forCompany($companyId)
            ->with(['assignedUsers', 'comments.user', 'topic', 'project'])
            ->find($taskId);

        if (!$task || !$task->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Task not found or access denied'];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'content' => $task->content,
                'status' => $task->status,
                'priority' => $task->priority,
                'completed' => $task->completed,
                'completed_at' => $task->completed_at,
                'due_date' => $task->due_date,
                'order' => $task->order,
                'topic' => [
                    'id' => $task->topic->id,
                    'name' => $task->topic->name,
                ],
                'project' => [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ],
                'assignees' => $task->assignedUsers->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->first_name . ' ' . ($u->last_name ?? ''),
                ]),
                'comments' => $task->comments->map(fn($c) => [
                    'id' => $c->id,
                    'content' => $c->content,
                    'user' => $c->user ? [
                        'id' => $c->user->id,
                        'name' => $c->user->first_name . ' ' . ($c->user->last_name ?? ''),
                    ] : null,
                    'created_at' => $c->created_at?->toIso8601String(),
                ]),
                'created_at' => $task->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Create a task.
     */
    public function createTask(User $user, string $companyId, string $topicId, array $data): array
    {
        $topic = Topic::forCompany($companyId)
            ->with('project')
            ->find($topicId);

        if (!$topic || !$topic->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Topic not found or access denied'];
        }

        $maxOrder = Task::where('topic_id', $topicId)->max('order') ?? -1;

        $task = DB::transaction(function () use ($data, $topic, $user, $companyId, $topicId, $maxOrder) {
            $task = Task::create([
                'topic_id' => $topicId,
                'project_id' => $topic->project_id,
                'company_id' => $companyId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content' => $data['content'] ?? null,
                'status' => $data['status'] ?? 'new',
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'order' => $data['order'] ?? $maxOrder + 1,
                'tags' => $data['tags'] ?? [],
            ]);

            TaskActivityLog::log(
                'create',
                "Created task: {$task->title}",
                $task,
                null,
                $task->toArray(),
                $user->id,
                $companyId
            );

            return $task;
        });

        return [
            'success' => true,
            'message' => 'Task created successfully',
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'topic_id' => $task->topic_id,
                'project_id' => $task->project_id,
            ],
        ];
    }

    /**
     * Update a task.
     */
    public function updateTask(User $user, string $companyId, string $taskId, array $data): array
    {
        $task = Task::forCompany($companyId)
            ->with('project')
            ->find($taskId);

        if (!$task || !$task->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Task not found or access denied'];
        }

        $oldValues = $task->toArray();
        $oldStatus = $task->status;
        $wasCompleted = $task->completed;

        // Handle completion
        if (isset($data['completed'])) {
            if ($data['completed'] && !$wasCompleted) {
                $data['completed_at'] = now();
                $data['completed_by'] = $user->id;
                $data['status'] = 'done';
            } elseif (!$data['completed'] && $wasCompleted) {
                $data['completed_at'] = null;
                $data['completed_by'] = null;
                if ($task->status === 'done') {
                    $data['status'] = 'new';
                }
            }
        }

        $task->update(array_filter($data, fn($v) => $v !== null));

        // Determine action type
        $action = 'update';
        if (isset($data['completed']) && $data['completed'] && !$wasCompleted) {
            $action = 'complete';
        } elseif (isset($data['status']) && $data['status'] !== $oldStatus) {
            $action = 'status_change';
        }

        TaskActivityLog::log(
            $action,
            "Updated task: {$task->title}",
            $task,
            $oldValues,
            $task->toArray(),
            $user->id,
            $companyId
        );

        return [
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'completed' => $task->completed,
            ],
        ];
    }

    /**
     * Delete a task.
     */
    public function deleteTask(User $user, string $companyId, string $taskId): array
    {
        $task = Task::forCompany($companyId)
            ->with('project')
            ->find($taskId);

        if (!$task || !$task->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Task not found or access denied'];
        }

        DB::transaction(function () use ($task, $user, $companyId) {
            TaskActivityLog::log(
                'delete',
                "Deleted task: {$task->title}",
                $task,
                $task->toArray(),
                null,
                $user->id,
                $companyId
            );

            $task->delete();
        });

        return ['success' => true, 'message' => 'Task deleted successfully'];
    }

    /**
     * Assign a user to a task.
     */
    public function assignTask(User $user, string $companyId, string $taskId, string $assigneeId): array
    {
        $task = Task::forCompany($companyId)
            ->with('project')
            ->find($taskId);

        if (!$task || !$task->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Task not found or access denied'];
        }

        // Check if already assigned
        if (TaskAssignment::where('task_id', $taskId)->where('user_id', $assigneeId)->exists()) {
            return ['success' => false, 'error' => 'User is already assigned to this task'];
        }

        TaskAssignment::create([
            'task_id' => $taskId,
            'user_id' => $assigneeId,
            'company_id' => $companyId,
            'assigned_by' => $user->id,
        ]);

        TaskActivityLog::log(
            'assign',
            "Assigned user to task: {$task->title}",
            $task,
            null,
            ['user_id' => $assigneeId],
            $user->id,
            $companyId
        );

        // Create notification
        TaskNotificationEvent::create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'company_id' => $companyId,
            'recipient_id' => $assigneeId,
            'actor_id' => $user->id,
            'action' => 'assigned_task',
            'payload' => [
                'task_id' => $task->id,
                'task_title' => $task->title,
            ],
        ]);

        return ['success' => true, 'message' => 'User assigned successfully'];
    }

    // ========================================
    // COMMENT OPERATIONS
    // ========================================

    /**
     * List comments for a task.
     */
    public function listComments(User $user, string $companyId, string $taskId): array
    {
        $task = Task::forCompany($companyId)
            ->with('project')
            ->find($taskId);

        if (!$task || !$task->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Task not found or access denied'];
        }

        $comments = TaskComment::where('task_id', $taskId)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'is_internal' => $comment->is_internal,
                'user' => $comment->user ? [
                    'id' => $comment->user->id,
                    'name' => $comment->user->first_name . ' ' . ($comment->user->last_name ?? ''),
                    'avatar' => $comment->user->avatar_url,
                ] : null,
                'created_at' => $comment->created_at?->toIso8601String(),
            ]);

        return ['success' => true, 'data' => $comments->toArray()];
    }

    /**
     * Create a comment.
     */
    public function createComment(User $user, string $companyId, string $taskId, array $data): array
    {
        $task = Task::forCompany($companyId)
            ->with('project')
            ->find($taskId);

        if (!$task || !$task->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Task not found or access denied'];
        }

        $comment = TaskComment::create([
            'task_id' => $taskId,
            'user_id' => $user->id,
            'content' => $data['content'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);

        TaskActivityLog::log(
            'comment',
            "Added comment to task: {$task->title}",
            $task,
            null,
            ['comment_id' => $comment->id],
            $user->id,
            $companyId
        );

        return [
            'success' => true,
            'message' => 'Comment created successfully',
            'data' => [
                'id' => $comment->id,
                'content' => $comment->content,
            ],
        ];
    }

    /**
     * Update a comment.
     */
    public function updateComment(User $user, string $companyId, string $commentId, array $data): array
    {
        $comment = TaskComment::with(['task.project'])->find($commentId);

        if (!$comment || !$comment->task || !$comment->task->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Comment not found or access denied'];
        }

        // Only the author can edit their comment
        if ($comment->user_id !== $user->id) {
            return ['success' => false, 'error' => 'You can only edit your own comments'];
        }

        $comment->update([
            'content' => $data['content'],
        ]);

        return [
            'success' => true,
            'message' => 'Comment updated successfully',
            'data' => [
                'id' => $comment->id,
                'content' => $comment->content,
            ],
        ];
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(User $user, string $companyId, string $commentId): array
    {
        $comment = TaskComment::with(['task.project'])->find($commentId);

        if (!$comment || !$comment->task || !$comment->task->project->hasMember($user)) {
            return ['success' => false, 'error' => 'Comment not found or access denied'];
        }

        // Only the author or project admins can delete
        if ($comment->user_id !== $user->id) {
            $member = ProjectMember::where('project_id', $comment->task->project_id)
                ->where('user_id', $user->id)
                ->whereIn('role', ['owner', 'admin'])
                ->first();

            if (!$member) {
                return ['success' => false, 'error' => 'You can only delete your own comments'];
            }
        }

        $comment->delete();

        return ['success' => true, 'message' => 'Comment deleted successfully'];
    }
}

