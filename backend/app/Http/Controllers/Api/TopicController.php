<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Topic;
use App\Models\TopicAssignment;
use App\Models\TaskActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TopicController extends Controller
{
    /**
     * Display topics for a project.
     */
    public function index(Request $request, string $projectId): JsonResponse
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

        $topics = Topic::forProject($projectId)
            ->with(['tasks.assignedUsers', 'assignedUsers'])
            ->orderBy('position')
            ->get()
            ->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'description' => $topic->description,
                    'position' => $topic->position,
                    'color' => $topic->color,
                    'is_locked' => $topic->is_locked,
                    'tasks' => $topic->tasks->map(fn($task) => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'priority' => $task->priority,
                        'completed' => $task->completed,
                        'due_date' => $task->due_date,
                        'order' => $task->order,
                        'assignees' => $task->assignedUsers->map(fn($u) => [
                            'id' => $u->id,
                            'name' => $u->first_name . ' ' . ($u->last_name ?? ''),
                            'avatar' => $u->avatar_url,
                        ]),
                    ]),
                    'assigned_users' => $topic->assignedUsers->map(fn($u) => [
                        'id' => $u->id,
                        'name' => $u->first_name . ' ' . ($u->last_name ?? ''),
                        'avatar' => $u->avatar_url,
                    ]),
                    'completion_percentage' => $topic->completion_percentage,
                    'created_at' => $topic->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $topics,
        ]);
    }

    /**
     * Store a newly created topic.
     */
    public function store(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
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

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'color' => 'nullable|string|max:20',
                'position' => 'nullable|integer',
            ]);

            // Get max position if not provided
            if (!isset($validated['position'])) {
                $maxPosition = Topic::forProject($projectId)->max('position') ?? -1;
                $validated['position'] = $maxPosition + 1;
            }

            $topic = Topic::create([
                ...$validated,
                'project_id' => $projectId,
                'company_id' => $companyId,
            ]);

            TaskActivityLog::log(
                'create',
                "Created topic: {$topic->name}",
                $topic,
                null,
                $topic->toArray(),
                $user->id,
                $companyId
            );

            $topic->load(['tasks', 'assignedUsers']);

            return response()->json([
                'success' => true,
                'message' => 'Topic created successfully',
                'data' => $topic,
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
                'message' => 'Failed to create topic',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified topic.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $topic = Topic::forCompany($companyId)
            ->with(['tasks.assignedUsers', 'assignedUsers', 'project'])
            ->find($id);

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found',
            ], 404);
        }

        // Check project access
        if (!$topic->project->hasMember($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $topic,
        ]);
    }

    /**
     * Update the specified topic.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $topic = Topic::forCompany($companyId)
                ->with('project')
                ->find($id);

            if (!$topic) {
                return response()->json([
                    'success' => false,
                    'message' => 'Topic not found',
                ], 404);
            }

            // Check project access
            if (!$topic->project->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'color' => 'nullable|string|max:20',
                'position' => 'nullable|integer',
            ]);

            $oldValues = $topic->toArray();
            $topic->update($validated);

            TaskActivityLog::log(
                'update',
                "Updated topic: {$topic->name}",
                $topic,
                $oldValues,
                $topic->toArray(),
                $user->id,
                $companyId
            );

            $topic->load(['tasks.assignedUsers', 'assignedUsers']);

            return response()->json([
                'success' => true,
                'message' => 'Topic updated successfully',
                'data' => $topic,
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
                'message' => 'Failed to update topic',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified topic.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $topic = Topic::forCompany($companyId)
                ->with('project')
                ->find($id);

            if (!$topic) {
                return response()->json([
                    'success' => false,
                    'message' => 'Topic not found',
                ], 404);
            }

            // Check project access
            if (!$topic->project->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                ], 403);
            }

            DB::transaction(function () use ($topic, $user, $companyId) {
                TaskActivityLog::log(
                    'delete',
                    "Deleted topic: {$topic->name}",
                    $topic,
                    $topic->toArray(),
                    null,
                    $user->id,
                    $companyId
                );

                $topic->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Topic deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete topic',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update topic positions.
     */
    public function updatePositions(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
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

            $validated = $request->validate([
                'positions' => 'required|array',
                'positions.*.id' => 'required|uuid',
                'positions.*.position' => 'required|integer|min:0',
            ]);

            DB::transaction(function () use ($validated, $projectId) {
                foreach ($validated['positions'] as $item) {
                    Topic::where('id', $item['id'])
                        ->where('project_id', $projectId)
                        ->update(['position' => $item['position']]);
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
     * Assign a user to a topic.
     */
    public function assignUser(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $topic = Topic::forCompany($companyId)
                ->with('project')
                ->find($id);

            if (!$topic || !$topic->project->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Topic not found or access denied',
                ], 404);
            }

            $validated = $request->validate([
                'user_id' => 'required|uuid|exists:users,id',
            ]);

            // Check if already assigned
            if (TopicAssignment::where('topic_id', $id)->where('user_id', $validated['user_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already assigned to this topic',
                ], 422);
            }

            TopicAssignment::create([
                'topic_id' => $id,
                'user_id' => $validated['user_id'],
                'company_id' => $companyId,
                'assigned_by' => $user->id,
            ]);

            TaskActivityLog::log(
                'assign',
                "Assigned user to topic: {$topic->name}",
                $topic,
                null,
                ['user_id' => $validated['user_id']],
                $user->id,
                $companyId
            );

            $topic->load('assignedUsers');

            return response()->json([
                'success' => true,
                'message' => 'User assigned successfully',
                'data' => $topic->assignedUsers,
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
     * Unassign a user from a topic.
     */
    public function unassignUser(Request $request, string $topicId, string $userId): JsonResponse
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

            TopicAssignment::where('topic_id', $topicId)
                ->where('user_id', $userId)
                ->delete();

            TaskActivityLog::log(
                'unassign',
                "Unassigned user from topic: {$topic->name}",
                $topic,
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
}

