<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'template_id',
        'contact_id',
        'project_id',
        'contract_id',
        'invoice_number',
        'title',
        'issue_date',
        'due_date',
        'status',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_rate',
        'discount_amount',
        'total',
        'amount_paid',
        'amount_due',
        'currency',
        'payment_terms',
        'notes',
        'pdf_path',
        'pdf_generated_at',
        'sent_at',
        'sent_by',
        'token',
        'stripe_payment_intent_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'pdf_generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->token)) {
                $invoice->token = Str::random(40);
            }
        });
    }

    /**
     * Get the company that owns the invoice.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the template for the invoice.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'template_id');
    }

    /**
     * Get the contact for the invoice.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the project for the invoice.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the contract for the invoice.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the user who sent the invoice.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Get the line items for the invoice.
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('order');
    }

    /**
     * Get the payments for the invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the events for the invoice.
     */
    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date < now() && $this->status !== 'paid' && $this->status !== 'cancelled';
    }

    /**
     * Check if the invoice is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->amount_paid >= $this->total;
    }

    /**
     * Calculate totals from line items.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->lineItems->sum('amount');
        
        // Calculate tax
        if ($this->tax_rate > 0) {
            $this->tax_amount = ($this->subtotal * $this->tax_rate) / 100;
        }
        
        // Calculate discount
        if ($this->discount_rate > 0) {
            $this->discount_amount = ($this->subtotal * $this->discount_rate) / 100;
        }
        
        // Calculate total
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->amount_due = $this->total - $this->amount_paid;
    }
}
