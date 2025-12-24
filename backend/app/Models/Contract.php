<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contract extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'template_id',
        'contact_id',
        'project_id',
        'title',
        'contract_number',
        'contract_type',
        'pricing_data',
        'rendered_sections',
        'merge_field_values',
        'status',
        'clickwrap_text',
        'client_signed_at',
        'client_signed_by',
        'client_ip_address',
        'client_user_agent',
        'provider_signed_at',
        'provider_signed_by',
        'pdf_path',
        'pdf_generated_at',
        'sent_at',
        'sent_by',
        'expires_at',
        'token',
        'notes',
    ];

    protected $casts = [
        'pricing_data' => 'array',
        'rendered_sections' => 'array',
        'merge_field_values' => 'array',
        'client_signed_at' => 'datetime',
        'provider_signed_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (empty($contract->token)) {
                $contract->token = Str::random(40);
            }
        });
    }

    /**
     * Get the company that owns the contract.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the template for the contract.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    /**
     * Get the contact for the contract.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the project for the contract.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who signed as provider.
     */
    public function providerSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_signed_by');
    }

    /**
     * Get the user who sent the contract.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Get the events for the contract.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ContractEvent::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the invoices for the contract.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if the contract is signed by both parties.
     */
    public function isFullySigned(): bool
    {
        return !is_null($this->client_signed_at) && !is_null($this->provider_signed_at);
    }

    /**
     * Check if the contract is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
