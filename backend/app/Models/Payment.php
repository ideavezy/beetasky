<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'receipt_url',
        'receipt_number',
        'notes',
        'processed_at',
        'failed_reason',
        'refunded_at',
        'refund_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Get the company that owns the payment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the invoice for the payment.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Check if the payment is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }

    /**
     * Check if the payment is refunded.
     */
    public function isRefunded(): bool
    {
        return !is_null($this->refunded_at);
    }
}
