<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskAttachment;
use App\Models\TaskActivityLog;
use App\Models\TaskNotificationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskCommentController extends Controller
{
    /**
     * Display comments for a task.
     */
    public function index(Request $request, string $taskId): JsonResponse
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

        $comments = TaskComment::forTask($taskId)
            ->with(['user', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'ai_generated' => $comment->ai_generated,
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->first_name . ' ' . ($comment->user->last_name ?? ''),
                        'avatar' => $comment->user->avatar_url,
                    ] : null,
                    'attachments' => $comment->attachments->map(fn($a) => [
                        'id' => $a->id,
                        'filename' => $a->filename,
                        'mime_type' => $a->mime_type,
                        'size' => $a->size,
                        'path' => $a->path,
                    ]),
                    'time' => $comment->created_at->diffForHumans(),
                    'created_at' => $comment->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'comments' => $comments,
        ]);
    }

    /**
     * Store a newly created comment.
     */
    public function store(Request $request, string $taskId): JsonResponse
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

            $validated = $request->validate([
                'content' => 'required|string',
                'attachments' => 'nullable|array',
                'attachments.*.filename' => 'required|string',
                'attachments.*.path' => 'required|string',
                'attachments.*.size' => 'nullable|integer',
                'attachments.*.mime_type' => 'nullable|string',
            ]);

            $comment = DB::transaction(function () use ($validated, $task, $user, $companyId, $taskId) {
                $comment = TaskComment::create([
                    'task_id' => $taskId,
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'content' => $validated['content'],
                ]);

                // Handle attachments
                if (!empty($validated['attachments'])) {
                    foreach ($validated['attachments'] as $attachment) {
                        TaskAttachment::create([
                            'task_id' => $taskId,
                            'comment_id' => $comment->id,
                            'company_id' => $companyId,
                            'uploaded_by' => $user->id,
                            'filename' => $attachment['filename'] ?? 'Unnamed file',
                            'bunny_filename' => basename($attachment['path'] ?? ''),
                            'path' => $attachment['path'] ?? '',
                            'mime_type' => $attachment['mime_type'] ?? '',
                            'size' => $attachment['size'] ?? 0,
                            'status' => 'completed',
                        ]);
                    }
                }

                TaskActivityLog::log(
                    'comment',
                    "Added comment to task: {$task->title}",
                    $task,
                    null,
                    ['comment_id' => $comment->id],
                    $user->id,
                    $companyId
                );

                // Notify task assignees and project members
                $this->createCommentNotifications($task, $comment, $user);

                return $comment;
            });

            $comment->load(['user', 'attachments']);

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->first_name . ' ' . ($comment->user->last_name ?? ''),
                        'avatar' => $comment->user->avatar_url,
                    ],
                    'attachments' => $comment->attachments->map(fn($a) => [
                        'id' => $a->id,
                        'filename' => $a->filename,
                        'mime_type' => $a->mime_type,
                        'size' => $a->size,
                        'path' => $a->path,
                    ]),
                    'time' => $comment->created_at->diffForHumans(),
                    'created_at' => $comment->created_at,
                ],
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
                'message' => 'Failed to add comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $comment = TaskComment::forCompany($companyId)
                ->with(['task.project'])
                ->find($id);

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Only the comment author can edit
            if ($comment->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit your own comments',
                ], 403);
            }

            $validated = $request->validate([
                'content' => 'required|string',
            ]);

            $comment->update($validated);
            $comment->load(['user', 'attachments']);

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->first_name . ' ' . ($comment->user->last_name ?? ''),
                        'avatar' => $comment->user->avatar_url,
                    ],
                    'attachments' => $comment->attachments->map(fn($a) => [
                        'id' => $a->id,
                        'filename' => $a->filename,
                        'mime_type' => $a->mime_type,
                        'size' => $a->size,
                        'path' => $a->path,
                    ]),
                    'time' => $comment->created_at->diffForHumans(),
                    'created_at' => $comment->created_at,
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
                'message' => 'Failed to update comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $comment = TaskComment::forCompany($companyId)
                ->with(['task.project'])
                ->find($id);

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Only the comment author or project admin can delete
            $isAuthor = $comment->user_id === $user->id;
            $isAdmin = $comment->task->project->hasAdminAccess($user);

            if (!$isAuthor && !$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this comment',
                ], 403);
            }

            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create notifications for new comments.
     */
    protected function createCommentNotifications(Task $task, TaskComment $comment, $actor): void
    {
        $notifiedUsers = collect();

        // Notify task assignees
        foreach ($task->assignedUsers as $assignee) {
            if ($assignee->id !== $actor->id) {
                TaskNotificationEvent::create([
                    'project_id' => $task->project_id,
                    'task_id' => $task->id,
                    'company_id' => $task->company_id,
                    'recipient_id' => $assignee->id,
                    'actor_id' => $actor->id,
                    'action' => 'new_comment',
                    'payload' => [
                        'task_id' => $task->id,
                        'task_title' => $task->title,
                        'comment_id' => $comment->id,
                        'comment_preview' => substr($comment->content, 0, 100),
                    ],
                ]);
                $notifiedUsers->push($assignee->id);
            }
        }
    }
}

