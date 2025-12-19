<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private user channel
Broadcast::channel('App.Models.User.{id}', function (User $user, string $id) {
    return $user->id === $id;
});

// Company channel - user must belong to the company
Broadcast::channel('company.{companyId}', function (User $user, string $companyId) {
    return $user->companies()->where('companies.id', $companyId)->exists();
});

// Conversation channel - user must be a participant
Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    // Check if user is a participant in this conversation
    return \App\Models\ConversationParticipant::where('conversation_id', $conversationId)
        ->where('participant_type', 'user')
        ->where('participant_id', $user->id)
        ->exists();
});

// Presence channel for company - shows who's online
Broadcast::channel('presence.company.{companyId}', function (User $user, string $companyId) {
    if ($user->companies()->where('companies.id', $companyId)->exists()) {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'avatar' => $user->avatar_url,
        ];
    }
    return false;
});

// Live session channel for real-time collaboration
Broadcast::channel('live.{sessionId}', function (User $user, string $sessionId) {
    $session = \App\Models\LiveSession::find($sessionId);
    if (!$session) return false;
    
    // User must belong to the company that owns the session
    return $user->companies()->where('companies.id', $session->company_id)->exists();
});

// Project channel - user must be a project member
Broadcast::channel('project.{projectId}', function (User $user, string $projectId) {
    return \App\Models\ProjectMember::where('project_id', $projectId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});

// Presence channel for project - shows who's viewing the project
Broadcast::channel('presence.project.{projectId}', function (User $user, string $projectId) {
    $isMember = \App\Models\ProjectMember::where('project_id', $projectId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
    
    if ($isMember) {
        return [
            'id' => $user->id,
            'name' => $user->full_name ?? $user->first_name,
            'avatar' => $user->avatar_url,
        ];
    }
    return false;
});

// User private channel for flow events and notifications
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});

// Flow channel - user must own the flow
Broadcast::channel('flow.{flowId}', function (User $user, string $flowId) {
    return \App\Models\AiFlowQueue::where('id', $flowId)
        ->where('user_id', $user->id)
        ->exists();
});
