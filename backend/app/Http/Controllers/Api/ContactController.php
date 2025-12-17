<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\CompanyContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    /**
     * Display a listing of contacts for the company.
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

        $query = CompanyContact::where('company_id', $companyId)
            ->with(['contact', 'assignedUser'])
            ->orderBy('created_at', 'desc');

        // Filter by relation type
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('relation_type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assigned user
        if ($request->has('assigned_to')) {
            if ($request->assigned_to === 'me') {
                $query->where('assigned_to', $user->id);
            } elseif ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        // Search by name or email
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contacts = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $contacts->map(function ($cc) {
                $contact = $cc->contact;
                return [
                    'id' => $contact->id,
                    'company_contact_id' => $cc->id,
                    'full_name' => $contact->full_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'organization' => $contact->organization,
                    'job_title' => $contact->job_title,
                    'avatar_url' => $contact->avatar_url,
                    'relation_type' => $cc->relation_type,
                    'status' => $cc->status,
                    'source' => $cc->source,
                    'assigned_to' => $cc->assignedUser ? [
                        'id' => $cc->assignedUser->id,
                        'name' => $cc->assignedUser->first_name . ' ' . ($cc->assignedUser->last_name ?? ''),
                        'avatar' => $cc->assignedUser->avatar_url,
                    ] : null,
                    'first_seen_at' => $cc->first_seen_at,
                    'last_activity_at' => $cc->last_activity_at,
                    'converted_at' => $cc->converted_at,
                    'created_at' => $cc->created_at,
                ];
            }),
            'pagination' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
                'has_more' => $contacts->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get contact statistics for dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        $stats = [
            'total' => CompanyContact::where('company_id', $companyId)->count(),
            'leads' => CompanyContact::where('company_id', $companyId)->leads()->count(),
            'customers' => CompanyContact::where('company_id', $companyId)->customers()->count(),
            'prospects' => CompanyContact::where('company_id', $companyId)->ofType('prospect')->count(),
            'vendors' => CompanyContact::where('company_id', $companyId)->ofType('vendor')->count(),
            'partners' => CompanyContact::where('company_id', $companyId)->ofType('partner')->count(),
            'active' => CompanyContact::where('company_id', $companyId)->active()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created contact.
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
                'full_name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'organization' => 'nullable|string|max:255',
                'job_title' => 'nullable|string|max:255',
                'address' => 'nullable|array',
                'relation_type' => 'required|string|in:lead,customer,prospect,vendor,partner',
                'status' => 'nullable|string|in:active,inactive',
                'source' => 'nullable|string|max:100',
                'tags' => 'nullable|array',
                'custom_fields' => 'nullable|array',
            ]);

            $result = DB::transaction(function () use ($validated, $companyId, $user) {
                // Check if contact with same email exists
                $contact = null;
                if (!empty($validated['email'])) {
                    $contact = Contact::where('email', $validated['email'])->first();
                }

                if (!$contact) {
                    // Create new contact
                    $contact = Contact::create([
                        'full_name' => $validated['full_name'],
                        'email' => $validated['email'] ?? null,
                        'phone' => $validated['phone'] ?? null,
                        'organization' => $validated['organization'] ?? null,
                        'job_title' => $validated['job_title'] ?? null,
                        'address' => $validated['address'] ?? null,
                        'tags' => $validated['tags'] ?? [],
                        'custom_fields' => $validated['custom_fields'] ?? [],
                    ]);
                }

                // Check if relationship already exists
                $existingRelation = CompanyContact::where('company_id', $companyId)
                    ->where('contact_id', $contact->id)
                    ->where('relation_type', $validated['relation_type'])
                    ->first();

                if ($existingRelation) {
                    throw ValidationException::withMessages([
                        'email' => ['This contact already exists with this relationship type.'],
                    ]);
                }

                // Create company-contact relationship
                $companyContact = CompanyContact::create([
                    'company_id' => $companyId,
                    'contact_id' => $contact->id,
                    'relation_type' => $validated['relation_type'],
                    'status' => $validated['status'] ?? 'active',
                    'source' => $validated['source'] ?? 'manual',
                    'assigned_to' => $user->id,
                    'first_seen_at' => now(),
                ]);

                return [
                    'contact' => $contact,
                    'company_contact' => $companyContact,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Contact created successfully',
                'data' => [
                    'id' => $result['contact']->id,
                    'company_contact_id' => $result['company_contact']->id,
                    'full_name' => $result['contact']->full_name,
                    'email' => $result['contact']->email,
                    'relation_type' => $result['company_contact']->relation_type,
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
                'message' => 'Failed to create contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified contact.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $companyContact = CompanyContact::where('company_id', $companyId)
            ->where('contact_id', $id)
            ->with(['contact', 'assignedUser'])
            ->first();

        if (!$companyContact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
            ], 404);
        }

        $contact = $companyContact->contact;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $contact->id,
                'company_contact_id' => $companyContact->id,
                'full_name' => $contact->full_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'organization' => $contact->organization,
                'job_title' => $contact->job_title,
                'address' => $contact->address,
                'avatar_url' => $contact->avatar_url,
                'custom_fields' => $contact->custom_fields,
                'tags' => $contact->tags,
                'relation_type' => $companyContact->relation_type,
                'status' => $companyContact->status,
                'source' => $companyContact->source,
                'assigned_to' => $companyContact->assignedUser ? [
                    'id' => $companyContact->assignedUser->id,
                    'name' => $companyContact->assignedUser->first_name . ' ' . ($companyContact->assignedUser->last_name ?? ''),
                    'avatar' => $companyContact->assignedUser->avatar_url,
                ] : null,
                'first_seen_at' => $companyContact->first_seen_at,
                'last_activity_at' => $companyContact->last_activity_at,
                'converted_at' => $companyContact->converted_at,
                'metadata' => $companyContact->metadata,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified contact.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        try {
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->where('contact_id', $id)
                ->with('contact')
                ->first();

            if (!$companyContact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                ], 404);
            }

            $validated = $request->validate([
                // Contact fields
                'full_name' => 'sometimes|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'organization' => 'nullable|string|max:255',
                'job_title' => 'nullable|string|max:255',
                'address' => 'nullable|array',
                'tags' => 'nullable|array',
                'custom_fields' => 'nullable|array',
                // Relationship fields
                'relation_type' => 'sometimes|string|in:lead,customer,prospect,vendor,partner',
                'status' => 'nullable|string|in:active,inactive,converted,lost',
                'assigned_to' => 'nullable|uuid|exists:users,id',
            ]);

            DB::transaction(function () use ($validated, $companyContact) {
                // Update contact info
                $contactFields = array_intersect_key($validated, array_flip([
                    'full_name', 'email', 'phone', 'organization', 
                    'job_title', 'address', 'tags', 'custom_fields'
                ]));
                
                if (!empty($contactFields)) {
                    $companyContact->contact->update($contactFields);
                }

                // Update relationship info
                $relationFields = array_intersect_key($validated, array_flip([
                    'relation_type', 'status', 'assigned_to'
                ]));
                
                if (!empty($relationFields)) {
                    $companyContact->update($relationFields);
                }

                // Update last activity
                $companyContact->touchActivity();
            });

            return response()->json([
                'success' => true,
                'message' => 'Contact updated successfully',
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
                'message' => 'Failed to update contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified contact from the company.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        try {
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->where('contact_id', $id)
                ->first();

            if (!$companyContact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                ], 404);
            }

            // Only remove the relationship, not the global contact
            $companyContact->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contact removed from company',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

