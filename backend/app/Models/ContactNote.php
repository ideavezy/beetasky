<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ContactNote Model
 * 
 * Notes/comments attached to a contact for CRM tracking.
 */
class ContactNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'contact_id',
        'created_by',
        'content',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => PostgresBoolean::class,
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the company this note belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact this note is for.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user who created this note.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get pinned notes first.
     */
    public function scopePinnedFirst($query)
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('created_at');
    }

    /**
     * Scope to filter by contact.
     */
    public function scopeForContact($query, string $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}


