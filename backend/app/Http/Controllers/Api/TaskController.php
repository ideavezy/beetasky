<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Topic;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskActivityLog;
use App\Models\TaskNotificationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Display tasks for a topic.
     */
    public function index(Request $request, string $topicId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $topic = Topic::forCompany($companyId)
            ->with('project')
            ->find($topicId);

        if (!$topic || !$topic->project->hasMember($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found or access denied',
            ], 404);
        }

        $tasks = Task::forTopic($topicId)
            ->with(['assignedUsers', 'comments'])
            ->withCount('comments')
            ->orderBy('order')
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'content' => $task->content,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'completed' => $task->completed,
                    'due_date' => $task->due_date,
                    'order' => $task->order,
                    'comments_count' => $task->comments_count,
                    'assignees' => $task->assignedUsers->map(fn($u) => [
                        'id' => $u->id,
                        'name' => $u->first_name . ' ' . ($u->last_name ?? ''),
                        'avatar' => $u->avatar_url,
                    ]),
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request, string $topicId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $topic = Topic::forCompany($companyId)
                ->with('project')
                ->find($topicId);

            if (!$topic || !$topic->project->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Topic not found or access denied',
                ], 404);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:500',
                'description' => 'nullable|string',
                'content' => 'nullable|string',
                'status' => 'nullable|string|in:new,working,question,on_hold,in_review,done,canceled',
                'priority' => 'nullable|string|in:low,medium,high,urgent',
                'due_date' => 'nullable|date',
                'order' => 'nullable|integer',
                'tags' => 'nullable|array',
            ]);

            // Get max order if not provided
            if (!isset($validated['order'])) {
                $maxOrder = Task::forTopic($topicId)->max('order') ?? -1;
                $validated['order'] = $maxOrder + 1;
            }

            $task = DB::transaction(function () use ($validated, $topic, $user, $companyId, $topicId) {
                $task = Task::create([
                    ...$validated,
                    'topic_id' => $topicId,
                    'project_id' => $topic->project_id,
                    'company_id' => $companyId,
                    'status' => $validated['status'] ?? 'todo',
                    'priority' => $validated['priority'] ?? 'medium',
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

                // Create notification events for project members
                $this->createNotificationEvents($task, 'new_task', $user);

                return $task;
            });

            $task->load(['assignedUsers', 'comments']);

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => $task,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified task.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $task = Task::forCompany($companyId)
            ->with([
                'assignedUsers',
                'comments.user',
                'comments.attachments',
                'attachments',
                'topic',
                'project',
            ])
            ->find($id);

        if (!$task || !$task->project->hasMember($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found or access denied',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $task = Task::forCompany($companyId)
                ->with('project')
                ->find($id);

            if (!$task || !$task->project->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or access denied',
                ], 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:500',
                'description' => 'nullable|string',
                'content' => 'nullable|string',
                'status' => 'nullable|string|in:new,working,question,on_hold,in_review,done,canceled',
                'priority' => 'nullable|string|in:low,medium,high,urgent',
                'due_date' => 'nullable|date',
                'completed' => 'nullable|boolean',
                'order' => 'nullable|integer',
                'topic_id' => 'nullable|uuid|exists:topics,id',
                'tags' => 'nullable|array',
            ]);

            $oldValues = $task->toArray();
            $oldStatus = $task->status;
            $wasCompleted = $task->completed;

            // Handle completion
            if (isset($validated['completed'])) {
                if ($validated['completed'] && !$wasCompleted) {
                    $validated['completed_at'] = now();
                    $validated['completed_by'] = $user->id;
                    $validated['status'] = 'done';
                } elseif (!$validated['completed'] && $wasCompleted) {
                    $validated['completed_at'] = null;
                    $validated['completed_by'] = null;
                    if ($task->status === 'done') {
                        $validated['status'] = 'todo';
                    }
                }
            }

            $task->update($validated);

            // Log activity
            $action = 'update';
            $description = "Updated task: {$task->title}";
            
            if (isset($validated['completed']) && $validated['completed'] && !$wasCompleted) {
                $action = 'complete';
                $description = "Completed task: {$task->title}";
            } elseif (isset($validated['completed']) && !$validated['completed'] && $wasCompleted) {
                $action = 'uncomplete';
                $description = "Uncompleted task: {$task->title}";
            } elseif (isset($validated['status']) && $validated['status'] !== $oldStatus) {
                $action = 'status_change';
                $description = "Changed task status from {$oldStatus} to {$validated['status']}";
            }

            TaskActivityLog::log(
                $action,
                $description,
                $task,
                $oldValues,
                $task->toArray(),
                $user->id,
                $companyId
            );

            // Create notification for status change
            if ($action === 'status_change' || $action === 'complete') {
                $this->createNotificationEvents($task, 'task_status_change', $user);
            }

            $task->load(['assignedUsers', 'comments.user']);

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $task,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $task = Task::forCompany($companyId)
                ->with('project')
                ->find($id);

            if (!$task || !$task->project->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or access denied',
                ], 404);
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

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update task positions within a topic.
     */
    public function updatePositions(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $validated = $request->validate([
                'positions' => 'required|array',
                'positions.*.id' => 'required|uuid',
                'positions.*.order' => 'required|integer|min:0',
                'positions.*.topic_id' => 'nullable|uuid',
            ]);

            DB::transaction(function () use ($validated, $companyId) {
                foreach ($validated['positions'] as $item) {
                    $updateData = ['order' => $item['order']];
                    if (isset($item['topic_id'])) {
                        $updateData['topic_id'] = $item['topic_id'];
                    }
                    
                    Task::where('id', $item['id'])
                        ->where('company_id', $companyId)
                        ->update($updateData);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Positions updated successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update positions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign a user to a task.
     */
    public function assignUser(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $task = Task::forCompany($companyId)
                ->with('project')
                ->find($id);

            if (!$task || !$task->project->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or access denied',
                ], 404);
            }

            $validated = $request->validate([
                'user_id' => 'required|uuid|exists:users,id',
            ]);

            // Check if already assigned
            if (TaskAssignment::where('task_id', $id)->where('user_id', $validated['user_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already assigned to this task',
                ], 422);
            }

            TaskAssignment::create([
                'task_id' => $id,
                'user_id' => $validated['user_id'],
                'company_id' => $companyId,
                'assigned_by' => $user->id,
            ]);

            TaskActivityLog::log(
                'assign',
                "Assigned user to task: {$task->title}",
                $task,
                null,
                ['user_id' => $validated['user_id']],
                $user->id,
                $companyId
            );

            // Notify the assigned user
            TaskNotificationEvent::create([
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'company_id' => $companyId,
                'recipient_id' => $validated['user_id'],
                'actor_id' => $user->id,
                'action' => 'assigned_task',
                'payload' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                ],
            ]);

            $task->load('assignedUsers');

            return response()->json([
                'success' => true,
                'message' => 'User assigned successfully',
                'data' => $task->assignedUsers->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->first_name . ' ' . ($u->last_name ?? ''),
                    'avatar' => $u->avatar_url,
                ]),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unassign a user from a task.
     */
    public function unassignUser(Request $request, string $taskId, string $userId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $task = Task::forCompany($companyId)
                ->with('project')
                ->find($taskId);

            if (!$task || !$task->project->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or access denied',
                ], 404);
            }

            TaskAssignment::where('task_id', $taskId)
                ->where('user_id', $userId)
                ->delete();

            TaskActivityLog::log(
                'unassign',
                "Unassigned user from task: {$task->title}",
                $task,
                ['user_id' => $userId],
                null,
                $user->id,
                $companyId
            );

            return response()->json([
                'success' => true,
                'message' => 'User unassigned successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard tasks for the current user.
     * Enhanced for Work Execution view with advanced filtering.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $query = Task::forCompany($companyId)
            ->whereHas('project.members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with(['project', 'topic', 'assignedUsers'])
            ->withCount('comments');

        // Search by title
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Filter by status (single or multiple)
        if ($request->has('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
        }

        // Filter by project
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by priority (single or multiple)
        if ($request->has('priority')) {
            $priorities = is_array($request->priority) ? $request->priority : [$request->priority];
            $query->whereIn('priority', $priorities);
        }

        // Filter assigned tasks only
        if ($request->boolean('assigned_only')) {
            $query->whereHas('assignedUsers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Exclude completed tasks
        // Uses whereRaw for PostgreSQL boolean compatibility with emulated prepares
        if ($request->boolean('exclude_completed')) {
            $query->whereRaw('completed = false');
        }

        // Filter by due date
        if ($request->has('due_filter')) {
            switch ($request->due_filter) {
                case 'overdue':
                    $query->where('due_date', '<', now())
                          ->whereRaw('completed = false');
                    break;
                case 'due_soon':
                    $query->whereBetween('due_date', [now(), now()->addDays(7)])
                          ->whereRaw('completed = false');
                    break;
                case 'today':
                    $query->whereDate('due_date', now());
                    break;
                case 'this_week':
                    $query->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'no_date':
                    $query->whereNull('due_date');
                    break;
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Validate sort fields
        $allowedSorts = ['created_at', 'updated_at', 'due_date', 'priority', 'status', 'title'];
        if (in_array($sortBy, $allowedSorts)) {
            // Handle null values for due_date sorting
            if ($sortBy === 'due_date') {
                $query->orderByRaw("due_date IS NULL, due_date {$sortOrder}");
            } elseif ($sortBy === 'priority') {
                // Custom priority ordering: urgent > high > medium > low
                $query->orderByRaw("CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                    ELSE 5 END " . ($sortOrder === 'desc' ? 'DESC' : 'ASC'));
            } elseif ($sortBy === 'status') {
                // Custom status ordering for workflow
                $query->orderByRaw("CASE status 
                    WHEN 'working' THEN 1 
                    WHEN 'in_review' THEN 2 
                    WHEN 'question' THEN 3 
                    WHEN 'new' THEN 4 
                    WHEN 'on_hold' THEN 5 
                    WHEN 'done' THEN 6 
                    WHEN 'canceled' THEN 7 
                    ELSE 8 END " . ($sortOrder === 'desc' ? 'DESC' : 'ASC'));
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min($request->get('per_page', 50), 200); // Max 200 for work execution view
        $tasks = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tasks->map(fn($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'completed' => $task->completed,
                'due_date' => $task->due_date,
                'order' => $task->order,
                'comments_count' => $task->comments_count,
                'project' => [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                    'code' => $task->project->code,
                    'status' => $task->project->status,
                ],
                'topic' => $task->topic ? [
                    'id' => $task->topic->id,
                    'name' => $task->topic->name,
                    'color' => $task->topic->color,
                ] : null,
                'assignees' => $task->assignedUsers->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->first_name . ' ' . ($u->last_name ?? ''),
                    'avatar' => $u->avatar_url,
                ]),
                'created_at' => $task->created_at,
                'updated_at' => $task->updated_at,
            ]),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'has_more' => $tasks->hasMorePages(),
            ],
        ]);
    }

    /**
     * Create notification events for project members.
     */
    protected function createNotificationEvents(Task $task, string $action, $actor): void
    {
        $project = $task->project;
        $members = $project->activeMembers()->where('user_id', '!=', $actor->id)->get();

        foreach ($members as $member) {
            TaskNotificationEvent::create([
                'project_id' => $project->id,
                'task_id' => $task->id,
                'company_id' => $task->company_id,
                'recipient_id' => $member->id,
                'actor_id' => $actor->id,
                'action' => $action,
                'payload' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'project_name' => $project->name,
                ],
            ]);
        }
    }
}

