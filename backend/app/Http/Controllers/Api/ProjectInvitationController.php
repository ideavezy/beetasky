<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectInvitationController extends Controller
{
    /**
     * Get invitations for a project.
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $project = Project::forCompany($companyId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereIn('role', ['owner', 'admin']);
            })
            ->find($projectId);

        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found or access denied'], 404);
        }

        $invitations = ProjectInvitation::where('project_id', $projectId)
            ->with('inviter')
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $invitations]);
    }

    /**
     * Create a new invitation.
     */
    public function store(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $project = Project::forCompany($companyId)
                ->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->whereIn('role', ['owner', 'admin']);
                })
                ->find($projectId);

            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Project not found or access denied'], 404);
            }

            $validated = $request->validate([
                'email' => 'required|email',
                'role' => 'nullable|string|in:admin,member',
                'scope' => 'nullable|string|in:project,topic,task',
                'scope_id' => 'nullable|uuid',
            ]);

            // Check if already invited
            if (ProjectInvitation::where('project_id', $projectId)->where('email', $validated['email'])->pending()->exists()) {
                return response()->json(['success' => false, 'message' => 'This email has already been invited'], 422);
            }

            // Check if user is already a member
            $existingUser = User::where('email', $validated['email'])->first();
            if ($existingUser && ProjectMember::where('project_id', $projectId)->where('user_id', $existingUser->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'This user is already a member'], 422);
            }

            $invitation = ProjectInvitation::create([
                'project_id' => $projectId,
                'company_id' => $companyId,
                'inviter_id' => $user->id,
                'email' => $validated['email'],
                'token' => Str::random(64),
                'role' => $validated['role'] ?? 'member',
                'scope' => $validated['scope'] ?? 'project',
                'scope_id' => $validated['scope_id'],
                'expires_at' => now()->addDays(7),
            ]);

            return response()->json(['success' => true, 'message' => 'Invitation sent successfully', 'data' => $invitation], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Accept an invitation.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        try {
            $invitation = ProjectInvitation::where('token', $token)->first();

            if (!$invitation) {
                return response()->json(['success' => false, 'message' => 'Invitation not found'], 404);
            }

            if ($invitation->isExpired()) {
                return response()->json(['success' => false, 'message' => 'Invitation has expired'], 410);
            }

            if ($invitation->isAccepted()) {
                return response()->json(['success' => false, 'message' => 'Invitation already accepted'], 410);
            }

            DB::transaction(function () use ($invitation, $user) {
                ProjectMember::create([
                    'project_id' => $invitation->project_id,
                    'user_id' => $user->id,
                    'company_id' => $invitation->company_id,
                    'role' => $invitation->role,
                    'status' => 'active',
                    'invited_by' => $invitation->inviter_id,
                    'joined_at' => now(),
                ]);

                $invitation->markAsAccepted();
            });

            return response()->json(['success' => true, 'message' => 'Invitation accepted', 'data' => ['project_id' => $invitation->project_id]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to accept invitation'], 500);
        }
    }

    /**
     * Cancel an invitation.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $invitation = ProjectInvitation::forCompany($companyId)
            ->whereHas('project.members', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereIn('role', ['owner', 'admin']);
            })
            ->find($id);

        if (!$invitation) {
            return response()->json(['success' => false, 'message' => 'Invitation not found'], 404);
        }

        $invitation->delete();

        return response()->json(['success' => true, 'message' => 'Invitation cancelled']);
    }

    /**
     * Resend an invitation.
     */
    public function resend(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $invitation = ProjectInvitation::forCompany($companyId)
            ->whereHas('project.members', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereIn('role', ['owner', 'admin']);
            })
            ->find($id);

        if (!$invitation) {
            return response()->json(['success' => false, 'message' => 'Invitation not found'], 404);
        }

        $invitation->update([
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json(['success' => true, 'message' => 'Invitation resent']);
    }
}

