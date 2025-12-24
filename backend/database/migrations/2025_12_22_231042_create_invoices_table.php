<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('template_id')->nullable();
            
            // Relationships
            $table->uuid('contact_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('contract_id')->nullable();
            
            // Invoice metadata
            $table->string('invoice_number', 100)->unique();
            $table->string('title')->nullable();
            
            // Dates
            $table->date('issue_date');
            $table->date('due_date');
            
            // Status
            $table->string('status', 50)->default('draft');
            
            // Amounts
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            
            // Payment terms
            $table->text('payment_terms')->nullable();
            $table->text('notes')->nullable();
            
            // PDF storage
            $table->string('pdf_path', 500)->nullable();
            $table->timestampTz('pdf_generated_at')->nullable();
            
            // Delivery
            $table->timestampTz('sent_at')->nullable();
            $table->uuid('sent_by')->nullable();
            
            // Public link
            $table->string('token', 100)->unique()->nullable();
            
            // Stripe integration
            $table->string('stripe_payment_intent_id')->nullable();
            
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('invoice_templates')->onDelete('set null');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('set null');
            $table->foreign('sent_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['company_id']);
            $table->index(['contact_id']);
            $table->index(['project_id']);
            $table->index(['contract_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
            $table->index(['token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
