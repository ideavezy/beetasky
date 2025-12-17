<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TaskActivityLog;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        $query = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('status', 'active');
            })
            ->with(['members', 'topics', 'creator'])
            ->withCount(['tasks', 'topics']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $projects = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Transform projects with computed attributes
        $projectsData = $projects->getCollection()->map(function ($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'code' => $project->code,
                'ai_enabled' => $project->ai_enabled,
                'start_date' => $project->start_date,
                'due_date' => $project->due_date,
                'members' => $project->members->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->first_name . ' ' . ($m->last_name ?? ''),
                    'avatar' => $m->avatar_url,
                    'role' => $m->pivot->role,
                ]),
                'tasks_count' => $project->tasks_count,
                'topics_count' => $project->topics_count,
                'completed_tasks_count' => $project->completed_tasks_count,
                'completion_percentage' => $project->completion_percentage,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $projectsData,
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'has_more' => $projects->hasMorePages(),
            ],
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'code' => 'nullable|string|max:50',
                'status' => 'nullable|string|in:planning,active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'budget' => 'nullable|numeric|min:0',
                'ai_enabled' => 'nullable|boolean',
                'contact_id' => 'nullable|uuid',
            ]);

            $result = DB::transaction(function () use ($validated, $user, $companyId) {
                $project = Project::create([
                    ...$validated,
                    'company_id' => $companyId,
                    'created_by' => $user->id,
                    'status' => $validated['status'] ?? 'active',
                    'ai_enabled' => $validated['ai_enabled'] ?? false,
                ]);

                // Add the creator as owner
                ProjectMember::create([
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                // Create the default "General" topic for the project
                $generalTopic = Topic::create([
                    'project_id' => $project->id,
                    'company_id' => $companyId,
                    'name' => 'General',
                    'description' => 'Default topic for general tasks',
                    'position' => 0,
                ]);

                // Log the activity
                TaskActivityLog::log(
                    'create',
                    "Created project: {$project->name}",
                    $project,
                    null,
                    $project->toArray(),
                    $user->id,
                    $companyId
                );

                return ['project' => $project, 'topic' => $generalTopic];
            });

            $project = $result['project'];
            $generalTopic = $result['topic'];

            // Update user's preset with default project and topic (OUTSIDE transaction - may fail if table doesn't exist)
            try {
                $preset = $user->getOrCreatePreset();
                $currentSettings = $preset->settings ?? [];
                
                // Only set defaults if user doesn't have any yet
                if (empty($currentSettings['default_project_id']) || empty($currentSettings['default_topic_id'])) {
                    $preset->update([
                        'settings' => array_merge($currentSettings, [
                            'default_project_id' => $project->id,
                            'default_topic_id' => $generalTopic->id,
                        ]),
                    ]);
                }
            } catch (\Exception $e) {
                // Preset table might not exist yet, log and continue
                \Log::warning('Could not update user preset', ['error' => $e->getMessage()]);
            }

            $project->load(['members', 'topics', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => $project,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to create project', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'company_id' => $companyId ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with([
                'members',
                'topics.tasks.assignedUsers',
                'creator',
            ])
            ->withCount(['tasks', 'topics'])
            ->find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found or access denied',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $project = Project::forCompany($companyId)
                ->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->whereIn('role', ['owner', 'admin']);
                })
                ->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied',
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'code' => 'nullable|string|max:50',
                'status' => 'nullable|string|in:planning,active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'budget' => 'nullable|numeric|min:0',
                'ai_enabled' => 'nullable|boolean',
                'ai_settings' => 'nullable|array',
                'settings' => 'nullable|array',
                'contact_id' => 'nullable|uuid',
            ]);

            $oldValues = $project->toArray();
            $project->update($validated);

            TaskActivityLog::log(
                'update',
                "Updated project: {$project->name}",
                $project,
                $oldValues,
                $project->toArray(),
                $user->id,
                $companyId
            );

            $project->load(['members', 'topics', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => $project,
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
                'message' => 'Failed to update project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified project (soft delete).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $project = Project::forCompany($companyId)
                ->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->where('role', 'owner');
                })
                ->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied',
                ], 404);
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

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get project members.
     */
    public function members(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found or access denied',
            ], 404);
        }

        $members = ProjectMember::where('project_id', $id)
            ->with('user')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'name' => $member->user->first_name . ' ' . ($member->user->last_name ?? ''),
                    'email' => $member->user->email,
                    'avatar' => $member->user->avatar_url,
                    'role' => $member->role,
                    'status' => $member->status,
                    'is_customer' => $member->is_customer,
                    'joined_at' => $member->joined_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Add a member to the project.
     */
    public function addMember(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $project = Project::forCompany($companyId)
                ->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->whereIn('role', ['owner', 'admin']);
                })
                ->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied',
                ], 404);
            }

            $validated = $request->validate([
                'user_id' => 'required|uuid|exists:users,id',
                'role' => 'nullable|string|in:admin,member',
                'is_customer' => 'nullable|boolean',
            ]);

            // Check if member already exists
            if (ProjectMember::where('project_id', $id)->where('user_id', $validated['user_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already a member of this project',
                ], 422);
            }

            $member = ProjectMember::create([
                'project_id' => $id,
                'user_id' => $validated['user_id'],
                'company_id' => $companyId,
                'role' => $validated['role'] ?? 'member',
                'status' => 'active',
                'invited_by' => $user->id,
                'joined_at' => now(),
                'is_customer' => $validated['is_customer'] ?? false,
            ]);

            $member->load('user');

            TaskActivityLog::log(
                'assign',
                "Added member to project: {$member->user->first_name}",
                $project,
                null,
                ['user_id' => $validated['user_id'], 'role' => $member->role],
                $user->id,
                $companyId
            );

            return response()->json([
                'success' => true,
                'message' => 'Member added successfully',
                'data' => $member,
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
                'message' => 'Failed to add member',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a project member.
     */
    public function updateMember(Request $request, string $projectId, string $userId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $project = Project::forCompany($companyId)
                ->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->whereIn('role', ['owner', 'admin']);
                })
                ->find($projectId);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied',
                ], 404);
            }

            $member = ProjectMember::where('project_id', $projectId)
                ->where('user_id', $userId)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found',
                ], 404);
            }

            // Can't change owner role unless you're the owner
            if ($member->role === 'owner' && $user->id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify owner role',
                ], 403);
            }

            $validated = $request->validate([
                'role' => 'nullable|string|in:admin,member',
                'status' => 'nullable|string|in:active,inactive',
                'is_customer' => 'nullable|boolean',
            ]);

            $member->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully',
                'data' => $member->load('user'),
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
                'message' => 'Failed to update member',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a member from the project.
     */
    public function removeMember(Request $request, string $projectId, string $userId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $project = Project::forCompany($companyId)
                ->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->whereIn('role', ['owner', 'admin']);
                })
                ->find($projectId);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied',
                ], 404);
            }

            $member = ProjectMember::where('project_id', $projectId)
                ->where('user_id', $userId)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found',
                ], 404);
            }

            // Can't remove the owner
            if ($member->role === 'owner') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove the project owner',
                ], 403);
            }

            $member->delete();

            TaskActivityLog::log(
                'unassign',
                "Removed member from project",
                $project,
                ['user_id' => $userId],
                null,
                $user->id,
                $companyId
            );

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get project settings.
     */
    public function settings(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found or access denied',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ai_enabled' => $project->ai_enabled,
                'ai_settings' => $project->ai_settings ?? [],
                'settings' => $project->settings ?? [],
            ],
        ]);
    }

    /**
     * Update project settings.
     */
    public function updateSettings(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $project = Project::forCompany($companyId)
                ->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->whereIn('role', ['owner', 'admin']);
                })
                ->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied',
                ], 404);
            }

            $validated = $request->validate([
                'ai_enabled' => 'nullable|boolean',
                'ai_settings' => 'nullable|array',
                'settings' => 'nullable|array',
            ]);

            $project->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => [
                    'ai_enabled' => $project->ai_enabled,
                    'ai_settings' => $project->ai_settings,
                    'settings' => $project->settings,
                ],
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
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

