<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CompanyContact Pivot Model
 * 
 * Represents the relationship between a company and a contact.
 * This is the key table for flexibility:
 * - Same contact can be lead of Company A, customer of Company B
 * - Tracks relationship type, status, assignment, and conversion
 */
class CompanyContact extends Model
{
    protected $table = 'company_contacts';

    protected $fillable = [
        'company_id',
        'contact_id',
        'relation_type',
        'status',
        'source',
        'assigned_to',
        'converted_at',
        'first_seen_at',
        'last_activity_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Relation types enum values.
     */
    public const RELATION_LEAD = 'lead';
    public const RELATION_CUSTOMER = 'customer';
    public const RELATION_PROSPECT = 'prospect';
    public const RELATION_VENDOR = 'vendor';
    public const RELATION_PARTNER = 'partner';

    /**
     * Valid relation types.
     */
    public static function relationTypes(): array
    {
        return [
            self::RELATION_LEAD,
            self::RELATION_CUSTOMER,
            self::RELATION_PROSPECT,
            self::RELATION_VENDOR,
            self::RELATION_PARTNER,
        ];
    }

    /**
     * Status values.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_LOST = 'lost';

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the assigned user.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Check if this is a lead relationship.
     */
    public function isLead(): bool
    {
        return $this->relation_type === self::RELATION_LEAD;
    }

    /**
     * Check if this is a customer relationship.
     */
    public function isCustomer(): bool
    {
        return $this->relation_type === self::RELATION_CUSTOMER;
    }

    /**
     * Check if the lead has been converted.
     */
    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }

    /**
     * Convert lead to customer.
     */
    public function convertToCustomer(): bool
    {
        if ($this->relation_type !== self::RELATION_LEAD) {
            return false;
        }

        $this->update([
            'relation_type' => self::RELATION_CUSTOMER,
            'status' => self::STATUS_CONVERTED,
            'converted_at' => now(),
        ]);

        return true;
    }

    /**
     * Update last activity timestamp.
     */
    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Assign to a user.
     */
    public function assignTo(User $user): void
    {
        $this->update(['assigned_to' => $user->id]);
    }

    /**
     * Scope by relation type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('relation_type', $type);
    }

    /**
     * Scope for leads only.
     */
    public function scopeLeads($query)
    {
        return $query->ofType(self::RELATION_LEAD);
    }

    /**
     * Scope for customers only.
     */
    public function scopeCustomers($query)
    {
        return $query->ofType(self::RELATION_CUSTOMER);
    }

    /**
     * Scope for active relationships.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for assigned to a user.
     */
    public function scopeAssignedTo($query, User $user)
    {
        return $query->where('assigned_to', $user->id);
    }

    /**
     * Scope for unassigned relationships.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }
}

