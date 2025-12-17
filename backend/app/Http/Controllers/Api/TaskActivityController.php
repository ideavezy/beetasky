<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskActivityController extends Controller
{
    /**
     * Get activity logs for a project.
     */
    public function projectActivity(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->find($projectId);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found or access denied',
            ], 404);
        }

        $query = TaskActivityLog::forCompany($companyId)
            ->with('user')
            ->where(function ($q) use ($projectId) {
                // Get activities for the project itself
                $q->where(function ($q2) use ($projectId) {
                    $q2->where('loggable_type', Project::class)
                        ->where('loggable_id', $projectId);
                })
                // Or for topics in this project
                ->orWhere(function ($q2) use ($projectId) {
                    $q2->where('loggable_type', 'App\\Models\\Topic')
                        ->whereIn('loggable_id', function ($subQuery) use ($projectId) {
                            $subQuery->select('id')
                                ->from('topics')
                                ->where('project_id', $projectId);
                        });
                })
                // Or for tasks in this project
                ->orWhere(function ($q2) use ($projectId) {
                    $q2->where('loggable_type', Task::class)
                        ->whereIn('loggable_id', function ($subQuery) use ($projectId) {
                            $subQuery->select('id')
                                ->from('tasks')
                                ->where('project_id', $projectId);
                        });
                });
            })
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $activities = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'user' => $activity->user ? [
                        'id' => $activity->user->id,
                        'name' => $activity->user->first_name . ' ' . ($activity->user->last_name ?? ''),
                        'avatar' => $activity->user->avatar_url,
                    ] : null,
                    'loggable_type' => class_basename($activity->loggable_type),
                    'loggable_id' => $activity->loggable_id,
                    'old_values' => $activity->old_values,
                    'new_values' => $activity->new_values,
                    'time' => $activity->created_at->diffForHumans(),
                    'created_at' => $activity->created_at,
                ];
            }),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'has_more' => $activities->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get activity logs for a task.
     */
    public function taskActivity(Request $request, string $taskId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $task = Task::forCompany($companyId)
            ->with('project')
            ->find($taskId);

        if (!$task || !$task->project->hasMember($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found or access denied',
            ], 404);
        }

        $activities = TaskActivityLog::forLoggable(Task::class, $taskId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'user' => $activity->user ? [
                        'id' => $activity->user->id,
                        'name' => $activity->user->first_name . ' ' . ($activity->user->last_name ?? ''),
                        'avatar' => $activity->user->avatar_url,
                    ] : null,
                    'old_values' => $activity->old_values,
                    'new_values' => $activity->new_values,
                    'time' => $activity->created_at->diffForHumans(),
                    'created_at' => $activity->created_at,
                ];
            }),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'has_more' => $activities->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get activity statistics for a project.
     */
    public function projectStats(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->find($projectId);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found or access denied',
            ], 404);
        }

        // Get activity counts by action
        $actionCounts = TaskActivityLog::forCompany($companyId)
            ->whereIn('loggable_id', function ($q) use ($projectId) {
                $q->select('id')
                    ->from('tasks')
                    ->where('project_id', $projectId);
            })
            ->where('loggable_type', Task::class)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action');

        // Get activity counts by user (top 5)
        $userCounts = TaskActivityLog::forCompany($companyId)
            ->whereIn('loggable_id', function ($q) use ($projectId) {
                $q->select('id')
                    ->from('tasks')
                    ->where('project_id', $projectId);
            })
            ->where('loggable_type', Task::class)
            ->whereNotNull('user_id')
            ->with('user')
            ->selectRaw('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'user' => $item->user ? [
                    'id' => $item->user->id,
                    'name' => $item->user->first_name . ' ' . ($item->user->last_name ?? ''),
                    'avatar' => $item->user->avatar_url,
                ] : null,
                'count' => $item->count,
            ]);

        // Get activity by day (last 7 days)
        $dailyActivity = TaskActivityLog::forCompany($companyId)
            ->whereIn('loggable_id', function ($q) use ($projectId) {
                $q->select('id')
                    ->from('tasks')
                    ->where('project_id', $projectId);
            })
            ->where('loggable_type', Task::class)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return response()->json([
            'success' => true,
            'data' => [
                'by_action' => $actionCounts,
                'by_user' => $userCounts,
                'daily' => $dailyActivity,
            ],
        ]);
    }

    /**
     * Get available activity filters for a project.
     */
    public function filters(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->find($projectId);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found or access denied',
            ], 404);
        }

        // Get unique actions
        $actions = TaskActivityLog::forCompany($companyId)
            ->whereIn('loggable_id', function ($q) use ($projectId) {
                $q->select('id')
                    ->from('tasks')
                    ->where('project_id', $projectId);
            })
            ->where('loggable_type', Task::class)
            ->distinct()
            ->pluck('action');

        // Get users who have activity
        $users = TaskActivityLog::forCompany($companyId)
            ->whereIn('loggable_id', function ($q) use ($projectId) {
                $q->select('id')
                    ->from('tasks')
                    ->where('project_id', $projectId);
            })
            ->where('loggable_type', Task::class)
            ->whereNotNull('user_id')
            ->with('user')
            ->distinct('user_id')
            ->get()
            ->map(fn($item) => [
                'id' => $item->user->id,
                'name' => $item->user->first_name . ' ' . ($item->user->last_name ?? ''),
            ])
            ->unique('id')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'actions' => $actions->map(fn($a) => [
                    'value' => $a,
                    'label' => TaskActivityLog::ACTIONS[$a] ?? ucfirst(str_replace('_', ' ', $a)),
                ]),
                'users' => $users,
            ],
        ]);
    }
}

