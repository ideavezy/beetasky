<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectNotificationSetting;
use App\Models\TaskNotificationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskNotificationController extends Controller
{
    /**
     * Get notifications for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $query = TaskNotificationEvent::forCompany($companyId)
            ->forRecipient($user->id)
            ->with(['project', 'task', 'actor'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $notifications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $notifications->map(fn($n) => [
                'id' => $n->id,
                'action' => $n->action,
                'action_label' => TaskNotificationEvent::ACTIONS[$n->action] ?? $n->action,
                'payload' => $n->payload,
                'status' => $n->status,
                'project' => $n->project ? ['id' => $n->project->id, 'name' => $n->project->name] : null,
                'task' => $n->task ? ['id' => $n->task->id, 'title' => $n->task->title] : null,
                'actor' => $n->actor ? [
                    'id' => $n->actor->id,
                    'name' => $n->actor->first_name . ' ' . ($n->actor->last_name ?? ''),
                    'avatar' => $n->actor->avatar_url,
                ] : null,
                'time' => $n->created_at->diffForHumans(),
                'created_at' => $n->created_at,
            ]),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Mark notifications as sent/read.
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $validated = $request->validate([
                'notification_ids' => 'required|array',
                'notification_ids.*' => 'uuid',
            ]);

            TaskNotificationEvent::forCompany($companyId)
                ->forRecipient($user->id)
                ->whereIn('id', $validated['notification_ids'])
                ->update(['status' => 'sent', 'sent_at' => now()]);

            return response()->json(['success' => true, 'message' => 'Notifications marked as read']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Get notification settings for a project.
     */
    public function settings(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $project = Project::forCompany($companyId)
            ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->find($projectId);

        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found'], 404);
        }

        $settings = ProjectNotificationSetting::getOrCreate($projectId, $user->id, $companyId);

        return response()->json(['success' => true, 'data' => $settings]);
    }

    /**
     * Update notification settings for a project.
     */
    public function updateSettings(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $project = Project::forCompany($companyId)
                ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
                ->find($projectId);

            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Project not found'], 404);
            }

            $validated = $request->validate([
                'notify_new_tasks' => 'nullable|boolean',
                'notify_task_assignments' => 'nullable|boolean',
                'notify_task_status_changes' => 'nullable|boolean',
                'notify_comments' => 'nullable|boolean',
                'notify_mentions' => 'nullable|boolean',
                'email_digest' => 'nullable|boolean',
                'digest_frequency' => 'nullable|string|in:instant,daily,weekly',
            ]);

            $settings = ProjectNotificationSetting::getOrCreate($projectId, $user->id, $companyId);
            $settings->update($validated);

            return response()->json(['success' => true, 'message' => 'Settings updated', 'data' => $settings]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $count = TaskNotificationEvent::forCompany($companyId)
            ->forRecipient($user->id)
            ->pending()
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    }
}

