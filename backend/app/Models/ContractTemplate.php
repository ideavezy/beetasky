<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'sections',
        'merge_fields',
        'clickwrap_text',
        'default_contract_type',
        'default_pricing_data',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'merge_fields' => 'array',
        'default_pricing_data' => 'array',
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
     * Get the company that owns the contract template.
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
     * Get the contracts for the template.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'template_id');
    }
}
