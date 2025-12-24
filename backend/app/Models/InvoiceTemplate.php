<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'layout',
        'default_terms',
        'default_notes',
        'default_tax_rate',
        'default_tax_label',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'layout' => 'array',
        'default_tax_rate' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Set the is_active attribute - ensures proper boolean for PostgreSQL.
     */
    public function setIsActiveAttribute($value): void
    {
        // Postgres does NOT accept integer 1/0 for boolean columns.
        // Ensure we always persist a boolean literal that Postgres can cast.
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->attributes['is_active'] = $bool ? 'true' : 'false';
    }

    /**
     * Set the is_default attribute - ensures proper boolean for PostgreSQL.
     */
    public function setIsDefaultAttribute($value): void
    {
        // Same rationale as is_active: avoid 1/0 bindings.
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->attributes['is_default'] = $bool ? 'true' : 'false';
    }

    /**
     * Get the company that owns the invoice template.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the invoices for the template.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'template_id');
    }
}
