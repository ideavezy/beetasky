<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ActivityController
 * 
 * Handles company-wide activity feed for CRM.
 */
class ActivityController extends Controller
{
    /**
     * Get recent activities for the company.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        try {
            $limit = min($request->get('limit', 10), 50);
            $type = $request->get('type', 'all'); // all, note, call, email, meeting, task

            // Get contact notes as activities
            $query = DB::table('contact_notes')
                ->leftJoin('users', 'contact_notes.created_by', '=', 'users.id')
                ->leftJoin('contacts', 'contact_notes.contact_id', '=', 'contacts.id')
                ->where('contact_notes.company_id', $companyId)
                ->whereNull('contact_notes.deleted_at')
                ->select([
                    'contact_notes.id',
                    'contact_notes.content',
                    'contact_notes.is_pinned',
                    'contact_notes.contact_id',
                    'contact_notes.created_at',
                    DB::raw("'note' as activity_type"),
                    'contacts.full_name as contact_name',
                    'contacts.email as contact_email',
                    'users.id as creator_id',
                    'users.first_name as creator_first_name',
                    'users.last_name as creator_last_name',
                    'users.avatar_url as creator_avatar',
                ]);

            // Filter by type
            if ($type !== 'all') {
                // For now, we only have notes
                // Can expand later with call logs, emails, meetings, etc.
                if ($type !== 'note') {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                    ]);
                }
            }

            $activities = $query
                ->orderByDesc('contact_notes.created_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'type' => $activity->activity_type,
                        'content' => $activity->content,
                        'is_pinned' => (bool) $activity->is_pinned,
                        'contact' => [
                            'id' => $activity->contact_id,
                            'name' => $activity->contact_name,
                            'email' => $activity->contact_email,
                        ],
                        'created_by' => $activity->creator_id ? [
                            'id' => $activity->creator_id,
                            'name' => trim(($activity->creator_first_name ?? '') . ' ' . ($activity->creator_last_name ?? '')),
                            'avatar' => $activity->creator_avatar,
                        ] : null,
                        'created_at' => $activity->created_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activities',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Store a new activity (quick note without specifying a contact).
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
                'content' => 'required|string|max:10000',
                'type' => 'required|string|in:note,call,email,meeting,task',
                'contact_id' => 'required|uuid|exists:contacts,id',
            ]);

            // Verify contact belongs to this company
            $exists = DB::table('company_contacts')
                ->where('company_id', $companyId)
                ->where('contact_id', $validated['contact_id'])
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found in this company',
                ], 404);
            }

            // Create contact note
            $note = ContactNote::create([
                'company_id' => $companyId,
                'contact_id' => $validated['contact_id'],
                'created_by' => $user->id,
                'content' => $validated['content'],
                'is_pinned' => false,
            ]);

            // Update last activity on the contact relationship
            DB::table('company_contacts')
                ->where('company_id', $companyId)
                ->where('contact_id', $validated['contact_id'])
                ->update(['last_activity_at' => now()]);

            // Get creator and contact info
            $creator = DB::table('users')
                ->where('id', $user->id)
                ->select('id', 'first_name', 'last_name', 'avatar_url')
                ->first();

            $contact = DB::table('contacts')
                ->where('id', $validated['contact_id'])
                ->select('id', 'full_name', 'email')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Activity added successfully',
                'data' => [
                    'id' => $note->id,
                    'type' => $validated['type'],
                    'content' => $note->content,
                    'is_pinned' => false,
                    'contact' => [
                        'id' => $contact->id,
                        'name' => $contact->full_name,
                        'email' => $contact->email,
                    ],
                    'created_by' => $creator ? [
                        'id' => $creator->id,
                        'name' => trim(($creator->first_name ?? '') . ' ' . ($creator->last_name ?? '')),
                        'avatar' => $creator->avatar_url,
                    ] : null,
                    'created_at' => $note->created_at,
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
                'message' => 'Failed to create activity',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

