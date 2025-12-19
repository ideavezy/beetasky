<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactNoteController extends Controller
{
    /**
     * Display a listing of notes for a contact.
     */
    public function index(Request $request, string $contactId): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        try {
            // Verify contact belongs to this company using raw query to avoid prepared statement issues
            $exists = DB::table('company_contacts')
                ->where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                ], 404);
            }

            $notes = DB::table('contact_notes')
                ->leftJoin('users', 'contact_notes.created_by', '=', 'users.id')
                ->where('contact_notes.company_id', $companyId)
                ->where('contact_notes.contact_id', $contactId)
                ->whereNull('contact_notes.deleted_at')
                ->select([
                    'contact_notes.id',
                    'contact_notes.content',
                    'contact_notes.is_pinned',
                    'contact_notes.created_at',
                    'contact_notes.updated_at',
                    'users.id as creator_id',
                    'users.first_name as creator_first_name',
                    'users.last_name as creator_last_name',
                    'users.avatar_url as creator_avatar',
                ])
                ->orderByDesc('contact_notes.is_pinned')
                ->orderByDesc('contact_notes.created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notes->map(function ($note) {
                    return [
                        'id' => $note->id,
                        'content' => $note->content,
                        'is_pinned' => (bool) $note->is_pinned,
                        'created_by' => $note->creator_id ? [
                            'id' => $note->creator_id,
                            'name' => trim(($note->creator_first_name ?? '') . ' ' . ($note->creator_last_name ?? '')),
                            'avatar' => $note->creator_avatar,
                        ] : null,
                        'created_at' => $note->created_at,
                        'updated_at' => $note->updated_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created note.
     */
    public function store(Request $request, string $contactId): JsonResponse
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
                'is_pinned' => 'nullable|boolean',
            ]);

            // Verify contact belongs to this company
            $exists = DB::table('company_contacts')
                ->where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                ], 404);
            }

            // Create note using the model
            $note = ContactNote::create([
                'company_id' => $companyId,
                'contact_id' => $contactId,
                'created_by' => $user->id,
                'content' => $validated['content'],
                'is_pinned' => $validated['is_pinned'] ?? false,
            ]);

            // Update last activity on the contact relationship
            DB::table('company_contacts')
                ->where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->update(['last_activity_at' => now()]);

            // Get creator info
            $creator = DB::table('users')
                ->where('id', $user->id)
                ->select('id', 'first_name', 'last_name', 'avatar_url')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'data' => [
                    'id' => $note->id,
                    'content' => $note->content,
                    'is_pinned' => (bool) $note->is_pinned,
                    'created_by' => $creator ? [
                        'id' => $creator->id,
                        'name' => trim(($creator->first_name ?? '') . ' ' . ($creator->last_name ?? '')),
                        'avatar' => $creator->avatar_url,
                    ] : null,
                    'created_at' => $note->created_at,
                    'updated_at' => $note->updated_at,
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
                'message' => 'Failed to create note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified note.
     */
    public function update(Request $request, string $contactId, string $noteId): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        try {
            $validated = $request->validate([
                'content' => 'sometimes|string|max:10000',
                'is_pinned' => 'nullable|boolean',
            ]);

            // Find note using Eloquent to apply casts
            $note = ContactNote::where('id', $noteId)
                ->where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->first();

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found',
                ], 404);
            }

            // Update note using Eloquent to apply casts
            $note->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Note updated successfully',
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
                'message' => 'Failed to update note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified note.
     */
    public function destroy(Request $request, string $contactId, string $noteId): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        try {
            // Check if note exists
            $exists = DB::table('contact_notes')
                ->where('id', $noteId)
                ->where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found',
                ], 404);
            }

            // Soft delete note
            DB::table('contact_notes')
                ->where('id', $noteId)
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

