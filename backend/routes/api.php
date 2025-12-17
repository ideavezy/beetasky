<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Broadcasting auth endpoint for Soketi/Pusher
Route::middleware('supabase.auth')->post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});

// Authentication routes using Supabase JWT auth
Route::middleware('supabase.auth')->group(function () {
    // Get authenticated user with profile data formatted for frontend
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->load(['companies', 'activeCompanies']);
        
        // Get the first active company as the default
        $company = $user->activeCompanies->first();
        
        // Format user data for frontend (map global_role to role)
        $userData = $user->toArray();
        $userData['role'] = $user->global_role ?? 'user';
        
        return response()->json([
            'user' => $userData,
            'company' => $company,
        ]);
    });
    
    // Update user profile
    Route::put('/user/profile', [App\Http\Controllers\Api\UserController::class, 'updateProfile']);
    
    // Get Supabase configuration for frontend
    Route::get('/config/supabase', function () {
        return response()->json([
            'url' => config('supabase.url'),
            'anon_key' => config('supabase.key'),
        ]);
    });
    
    // Get Pusher/Soketi configuration for frontend
    Route::get('/config/pusher', function () {
        return response()->json([
            'key' => config('broadcasting.connections.pusher.key'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'host' => config('broadcasting.connections.pusher.options.host'),
            'port' => config('broadcasting.connections.pusher.options.port'),
            'scheme' => config('broadcasting.connections.pusher.options.scheme'),
        ]);
    });
});

// API routes for Phase 2+ will be added here:
// - Companies routes
// - Contacts routes
// - Conversations routes
// - Messages routes

// ============================================================
// Project Management Routes (v1)
// ============================================================
Route::prefix('v1')->middleware('supabase.auth')->group(function () {
    
    // Companies
    Route::get('/companies', [App\Http\Controllers\Api\CompanyController::class, 'index']);
    Route::post('/companies', [App\Http\Controllers\Api\CompanyController::class, 'store']);
    Route::get('/companies/{id}', [App\Http\Controllers\Api\CompanyController::class, 'show']);
    Route::put('/companies/{id}', [App\Http\Controllers\Api\CompanyController::class, 'update']);
    
    // Projects
    Route::get('/projects', [App\Http\Controllers\Api\ProjectController::class, 'index']);
    Route::post('/projects', [App\Http\Controllers\Api\ProjectController::class, 'store']);
    Route::get('/projects/{id}', [App\Http\Controllers\Api\ProjectController::class, 'show']);
    Route::put('/projects/{id}', [App\Http\Controllers\Api\ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [App\Http\Controllers\Api\ProjectController::class, 'destroy']);
    
    // Project Members
    Route::get('/projects/{id}/members', [App\Http\Controllers\Api\ProjectController::class, 'members']);
    Route::post('/projects/{id}/members', [App\Http\Controllers\Api\ProjectController::class, 'addMember']);
    Route::put('/projects/{projectId}/members/{userId}', [App\Http\Controllers\Api\ProjectController::class, 'updateMember']);
    Route::delete('/projects/{projectId}/members/{userId}', [App\Http\Controllers\Api\ProjectController::class, 'removeMember']);
    
    // Project Settings
    Route::get('/projects/{id}/settings', [App\Http\Controllers\Api\ProjectController::class, 'settings']);
    Route::put('/projects/{id}/settings', [App\Http\Controllers\Api\ProjectController::class, 'updateSettings']);
    
    // Project Invitations
    Route::get('/projects/{projectId}/invitations', [App\Http\Controllers\Api\ProjectInvitationController::class, 'index']);
    Route::post('/projects/{projectId}/invitations', [App\Http\Controllers\Api\ProjectInvitationController::class, 'store']);
    Route::delete('/invitations/{id}', [App\Http\Controllers\Api\ProjectInvitationController::class, 'destroy']);
    Route::post('/invitations/{id}/resend', [App\Http\Controllers\Api\ProjectInvitationController::class, 'resend']);
    Route::post('/invitations/{token}/accept', [App\Http\Controllers\Api\ProjectInvitationController::class, 'accept']);
    
    // Project Activity
    Route::get('/projects/{id}/activity', [App\Http\Controllers\Api\TaskActivityController::class, 'projectActivity']);
    Route::get('/projects/{id}/activity/stats', [App\Http\Controllers\Api\TaskActivityController::class, 'projectStats']);
    Route::get('/projects/{id}/activity/filters', [App\Http\Controllers\Api\TaskActivityController::class, 'filters']);
    
    // Topics
    Route::get('/projects/{projectId}/topics', [App\Http\Controllers\Api\TopicController::class, 'index']);
    Route::post('/projects/{projectId}/topics', [App\Http\Controllers\Api\TopicController::class, 'store']);
    Route::put('/projects/{projectId}/topics/positions', [App\Http\Controllers\Api\TopicController::class, 'updatePositions']);
    Route::get('/topics/{id}', [App\Http\Controllers\Api\TopicController::class, 'show']);
    Route::put('/topics/{id}', [App\Http\Controllers\Api\TopicController::class, 'update']);
    Route::delete('/topics/{id}', [App\Http\Controllers\Api\TopicController::class, 'destroy']);
    
    // Topic Assignments
    Route::post('/topics/{id}/assign', [App\Http\Controllers\Api\TopicController::class, 'assignUser']);
    Route::delete('/topics/{topicId}/assign/{userId}', [App\Http\Controllers\Api\TopicController::class, 'unassignUser']);
    
    // Tasks - Dashboard (must be before {id} routes)
    Route::get('/tasks/dashboard', [App\Http\Controllers\Api\TaskController::class, 'dashboard']);
    
    // Tasks
    Route::get('/topics/{topicId}/tasks', [App\Http\Controllers\Api\TaskController::class, 'index']);
    Route::post('/topics/{topicId}/tasks', [App\Http\Controllers\Api\TaskController::class, 'store']);
    Route::put('/tasks/positions', [App\Http\Controllers\Api\TaskController::class, 'updatePositions']);
    Route::get('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'show']);
    Route::put('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'destroy']);
    
    // Task Assignments
    Route::post('/tasks/{id}/assign', [App\Http\Controllers\Api\TaskController::class, 'assignUser']);
    Route::delete('/tasks/{taskId}/assign/{userId}', [App\Http\Controllers\Api\TaskController::class, 'unassignUser']);
    
    // Task Comments
    Route::get('/tasks/{taskId}/comments', [App\Http\Controllers\Api\TaskCommentController::class, 'index']);
    Route::post('/tasks/{taskId}/comments', [App\Http\Controllers\Api\TaskCommentController::class, 'store']);
    Route::put('/comments/{id}', [App\Http\Controllers\Api\TaskCommentController::class, 'update']);
    Route::delete('/comments/{id}', [App\Http\Controllers\Api\TaskCommentController::class, 'destroy']);
    
    // Task Activity
    Route::get('/tasks/{id}/activity', [App\Http\Controllers\Api\TaskActivityController::class, 'taskActivity']);
    
    // Smart Import
    Route::get('/smart-import', [App\Http\Controllers\Api\SmartImportController::class, 'index']);
    Route::post('/smart-import', [App\Http\Controllers\Api\SmartImportController::class, 'store']);
    Route::get('/smart-import/{id}', [App\Http\Controllers\Api\SmartImportController::class, 'show']);
    
    // Notifications
    Route::get('/notifications', [App\Http\Controllers\Api\TaskNotificationController::class, 'index']);
    Route::post('/notifications/read', [App\Http\Controllers\Api\TaskNotificationController::class, 'markAsRead']);
    Route::get('/notifications/unread-count', [App\Http\Controllers\Api\TaskNotificationController::class, 'unreadCount']);
    Route::get('/projects/{projectId}/notification-settings', [App\Http\Controllers\Api\TaskNotificationController::class, 'settings']);
    Route::put('/projects/{projectId}/notification-settings', [App\Http\Controllers\Api\TaskNotificationController::class, 'updateSettings']);

    // Contacts
    Route::get('/contacts', [App\Http\Controllers\Api\ContactController::class, 'index']);
    Route::get('/contacts/stats', [App\Http\Controllers\Api\ContactController::class, 'stats']);
    Route::post('/contacts', [App\Http\Controllers\Api\ContactController::class, 'store']);
    Route::get('/contacts/{id}', [App\Http\Controllers\Api\ContactController::class, 'show']);
    Route::put('/contacts/{id}', [App\Http\Controllers\Api\ContactController::class, 'update']);
    Route::delete('/contacts/{id}', [App\Http\Controllers\Api\ContactController::class, 'destroy']);

    // AI Suggestions
    Route::get('/ai/dashboard-suggestions', [App\Http\Controllers\Api\AISuggestionController::class, 'dashboardSuggestions']);
    Route::post('/ai/suggestions', [App\Http\Controllers\Api\AISuggestionController::class, 'suggestions']); // Legacy
    Route::post('/ai/execute-action', [App\Http\Controllers\Api\AISuggestionController::class, 'executeAction']);

    // User Presets
    Route::get('/user-presets', [App\Http\Controllers\Api\UserPresetController::class, 'show']);
    Route::put('/user-presets', [App\Http\Controllers\Api\UserPresetController::class, 'update']);

    // API Keys Management
    Route::get('/api-keys', [App\Http\Controllers\Api\ApiKeyController::class, 'index']);
    Route::post('/api-keys', [App\Http\Controllers\Api\ApiKeyController::class, 'store']);
    Route::get('/api-keys/{id}', [App\Http\Controllers\Api\ApiKeyController::class, 'show']);
    Route::put('/api-keys/{id}', [App\Http\Controllers\Api\ApiKeyController::class, 'update']);
    Route::delete('/api-keys/{id}', [App\Http\Controllers\Api\ApiKeyController::class, 'destroy']);
    Route::post('/api-keys/{id}/regenerate', [App\Http\Controllers\Api\ApiKeyController::class, 'regenerate']);
});

