<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\CompanyContact;
use App\Models\ContactNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRM Service
 * 
 * Handles all CRM operations for contacts, leads, and customer management.
 * Used by AI skills and API controllers.
 */
class CRMService
{
    /**
     * Create a new contact with company relationship.
     */
    public function createContact(User $user, ?string $companyId, array $data): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            DB::beginTransaction();

            // Check if contact with this email already exists
            $existingContact = null;
            if (!empty($data['email'])) {
                $existingContact = Contact::where('email', $data['email'])->first();
            }

            if ($existingContact) {
                // Check if already linked to this company
                $existingRelation = CompanyContact::where('company_id', $companyId)
                    ->where('contact_id', $existingContact->id)
                    ->first();

                if ($existingRelation) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'error' => 'Contact with this email already exists in your company',
                    ];
                }

                // Link existing contact to this company
                $companyContact = CompanyContact::create([
                    'company_id' => $companyId,
                    'contact_id' => $existingContact->id,
                    'relation_type' => $data['relation_type'] ?? 'lead',
                    'status' => 'active',
                    'source' => $data['source'] ?? 'ai_chat',
                    'assigned_to' => $data['assigned_to'] ?? $user->id,
                    'first_seen_at' => now(),
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => "Linked existing contact '{$existingContact->full_name}' to your company as {$companyContact->relation_type}",
                    'data' => [
                        'id' => $existingContact->id,
                        'full_name' => $existingContact->full_name,
                        'email' => $existingContact->email,
                        'relation_type' => $companyContact->relation_type,
                        'is_new' => false,
                    ],
                ];
            }

            // Create new contact
            $contact = Contact::create([
                'full_name' => $data['full_name'] ?? $data['name'] ?? 'Unknown',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'organization' => $data['organization'] ?? $data['company'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'tags' => $data['tags'] ?? [],
            ]);

            // Create company relationship
            $companyContact = CompanyContact::create([
                'company_id' => $companyId,
                'contact_id' => $contact->id,
                'relation_type' => $data['relation_type'] ?? 'lead',
                'status' => 'active',
                'source' => $data['source'] ?? 'ai_chat',
                'assigned_to' => $data['assigned_to'] ?? $user->id,
                'first_seen_at' => now(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Created new {$companyContact->relation_type} '{$contact->full_name}'",
                'data' => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'organization' => $contact->organization,
                    'relation_type' => $companyContact->relation_type,
                    'is_new' => true,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CRM createContact failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * List contacts with filters.
     */
    public function listContacts(User $user, ?string $companyId, array $filters = []): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $query = CompanyContact::where('company_id', $companyId)
                ->with(['contact', 'assignedUser']);

            // Apply filters
            if (!empty($filters['relation_type']) || !empty($filters['type'])) {
                $type = $filters['relation_type'] ?? $filters['type'];
                $query->where('relation_type', $type);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->whereHas('contact', function ($q) use ($search) {
                    $q->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('organization', 'ilike', "%{$search}%");
                });
            }

            if (!empty($filters['assigned_to'])) {
                if ($filters['assigned_to'] === 'me') {
                    $query->where('assigned_to', $user->id);
                } else {
                    $query->where('assigned_to', $filters['assigned_to']);
                }
            }

            $limit = $filters['limit'] ?? 10;
            $contacts = $query->orderBy('last_activity_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return [
                'success' => true,
                'data' => $contacts->map(fn($cc) => [
                    'id' => $cc->contact->id,
                    'full_name' => $cc->contact->full_name,
                    'email' => $cc->contact->email,
                    'phone' => $cc->contact->phone,
                    'organization' => $cc->contact->organization,
                    'relation_type' => $cc->relation_type,
                    'status' => $cc->status,
                    'assigned_to' => $cc->assignedUser?->first_name,
                    'last_activity' => $cc->last_activity_at?->diffForHumans(),
                ])->toArray(),
                'total' => $contacts->count(),
            ];
        } catch (\Exception $e) {
            Log::error('CRM listContacts failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get contact details.
     */
    public function getContact(User $user, ?string $companyId, string $contactId): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->with(['contact', 'assignedUser'])
                ->first();

            if (!$companyContact) {
                return ['success' => false, 'error' => 'Contact not found'];
            }

            $contact = $companyContact->contact;

            // Get recent notes
            $notes = ContactNote::where('contact_id', $contactId)
                ->where('company_id', $companyId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return [
                'success' => true,
                'data' => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'organization' => $contact->organization,
                    'job_title' => $contact->job_title,
                    'relation_type' => $companyContact->relation_type,
                    'status' => $companyContact->status,
                    'source' => $companyContact->source,
                    'lead_score' => $companyContact->lead_score ?? 0,
                    'assigned_to' => $companyContact->assignedUser ? [
                        'id' => $companyContact->assignedUser->id,
                        'name' => $companyContact->assignedUser->first_name,
                    ] : null,
                    'first_seen_at' => $companyContact->first_seen_at?->format('M j, Y'),
                    'last_activity_at' => $companyContact->last_activity_at?->diffForHumans(),
                    'recent_notes' => $notes->map(fn($n) => [
                        'content' => substr($n->content, 0, 100),
                        'type' => $n->type ?? 'note',
                        'created_at' => $n->created_at->diffForHumans(),
                    ])->toArray(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('CRM getContact failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update a contact.
     */
    public function updateContact(User $user, ?string $companyId, string $contactId, array $data): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->with('contact')
                ->first();

            if (!$companyContact) {
                return ['success' => false, 'error' => 'Contact not found'];
            }

            $contact = $companyContact->contact;

            // Update contact fields
            $contactFields = ['full_name', 'email', 'phone', 'organization', 'job_title', 'tags'];
            $contactUpdates = array_filter(
                array_intersect_key($data, array_flip($contactFields)),
                fn($v) => $v !== null
            );

            if (!empty($contactUpdates)) {
                $contact->update($contactUpdates);
            }

            // Update relationship fields
            $relationFields = ['relation_type', 'status', 'assigned_to'];
            $relationUpdates = array_filter(
                array_intersect_key($data, array_flip($relationFields)),
                fn($v) => $v !== null
            );

            if (!empty($relationUpdates)) {
                $companyContact->update($relationUpdates);
            }

            // Touch activity
            $companyContact->touchActivity();

            return [
                'success' => true,
                'message' => "Updated contact '{$contact->full_name}'",
                'data' => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'relation_type' => $companyContact->relation_type,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('CRM updateContact failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convert a lead to customer.
     */
    public function convertLead(User $user, ?string $companyId, string $contactId): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->with('contact')
                ->first();

            if (!$companyContact) {
                return ['success' => false, 'error' => 'Contact not found'];
            }

            if ($companyContact->relation_type === 'customer') {
                return [
                    'success' => false,
                    'error' => 'Contact is already a customer',
                ];
            }

            $previousType = $companyContact->relation_type;
            $companyContact->convertToCustomer();

            return [
                'success' => true,
                'message' => "Converted '{$companyContact->contact->full_name}' from {$previousType} to customer",
                'data' => [
                    'id' => $companyContact->contact->id,
                    'full_name' => $companyContact->contact->full_name,
                    'previous_type' => $previousType,
                    'new_type' => 'customer',
                    'converted_at' => $companyContact->converted_at?->format('M j, Y'),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('CRM convertLead failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add a note to a contact.
     */
    public function addNote(User $user, ?string $companyId, string $contactId, array $data): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            // Verify contact belongs to company
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->with('contact')
                ->first();

            if (!$companyContact) {
                return ['success' => false, 'error' => 'Contact not found'];
            }

            $note = ContactNote::create([
                'contact_id' => $contactId,
                'company_id' => $companyId,
                'user_id' => $user->id,
                'content' => $data['content'] ?? $data['note'] ?? '',
                'type' => $data['type'] ?? 'note',
                'is_pinned' => $data['is_pinned'] ?? false,
            ]);

            // Update last activity
            $companyContact->touchActivity();

            return [
                'success' => true,
                'message' => "Added note to '{$companyContact->contact->full_name}'",
                'data' => [
                    'id' => $note->id,
                    'contact_id' => $contactId,
                    'contact_name' => $companyContact->contact->full_name,
                    'content' => $note->content,
                    'type' => $note->type,
                    'created_at' => $note->created_at->format('M j, Y g:i A'),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('CRM addNote failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Assign a contact to a user.
     */
    public function assignContact(User $user, ?string $companyId, string $contactId, ?string $assigneeId): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->with('contact')
                ->first();

            if (!$companyContact) {
                return ['success' => false, 'error' => 'Contact not found'];
            }

            // If no assignee provided, assign to current user
            $assigneeId = $assigneeId ?? $user->id;

            // Verify assignee exists
            $assignee = User::find($assigneeId);
            if (!$assignee) {
                return ['success' => false, 'error' => 'Assignee not found'];
            }

            $companyContact->update(['assigned_to' => $assigneeId]);
            $companyContact->touchActivity();

            return [
                'success' => true,
                'message' => "Assigned '{$companyContact->contact->full_name}' to {$assignee->first_name}",
                'data' => [
                    'id' => $companyContact->contact->id,
                    'full_name' => $companyContact->contact->full_name,
                    'assigned_to' => [
                        'id' => $assignee->id,
                        'name' => $assignee->first_name,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('CRM assignContact failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Search for a contact by name or email.
     * Returns the first matching contact.
     */
    public function findContact(User $user, ?string $companyId, string $search): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->whereHas('contact', function ($q) use ($search) {
                    $q->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                })
                ->with(['contact', 'assignedUser'])
                ->first();

            if (!$companyContact) {
                return [
                    'success' => false,
                    'error' => "No contact found matching '{$search}'",
                ];
            }

            $contact = $companyContact->contact;

            return [
                'success' => true,
                'data' => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'organization' => $contact->organization,
                    'relation_type' => $companyContact->relation_type,
                    'status' => $companyContact->status,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('CRM findContact failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get CRM statistics for a company.
     */
    public function getStats(?string $companyId): array
    {
        if (!$companyId) {
            return [
                'total' => 0,
                'leads' => 0,
                'customers' => 0,
                'prospects' => 0,
                'stale_leads' => 0,
            ];
        }

        try {
            $query = CompanyContact::where('company_id', $companyId);

            $total = (clone $query)->count();
            $leads = (clone $query)->where('relation_type', 'lead')->count();
            $customers = (clone $query)->where('relation_type', 'customer')->count();
            $prospects = (clone $query)->where('relation_type', 'prospect')->count();
            
            // Stale leads: no activity in 14+ days
            $staleLeads = (clone $query)
                ->where('relation_type', 'lead')
                ->where(function ($q) {
                    $q->where('last_activity_at', '<', now()->subDays(14))
                        ->orWhereNull('last_activity_at');
                })
                ->count();

            // Conversions this month
            $conversionsThisMonth = (clone $query)
                ->where('relation_type', 'customer')
                ->whereNotNull('converted_at')
                ->where('converted_at', '>=', now()->startOfMonth())
                ->count();

            return [
                'total' => $total,
                'leads' => $leads,
                'customers' => $customers,
                'prospects' => $prospects,
                'stale_leads' => $staleLeads,
                'conversions_this_month' => $conversionsThisMonth,
            ];
        } catch (\Exception $e) {
            Log::error('CRM getStats failed', ['error' => $e->getMessage()]);
            return [
                'total' => 0,
                'leads' => 0,
                'customers' => 0,
                'prospects' => 0,
                'stale_leads' => 0,
            ];
        }
    }
}

