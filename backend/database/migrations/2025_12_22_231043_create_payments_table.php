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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('invoice_id')->nullable();
            
            // Payment details
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            
            $table->string('payment_method', 50)->default('stripe');
            $table->string('status', 50)->default('pending');
            
            // Stripe data
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_payment_method_id')->nullable();
            
            // Receipt
            $table->string('receipt_url', 500)->nullable();
            $table->string('receipt_number', 100)->nullable();
            
            // Metadata
            $table->text('notes')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->decimal('refund_amount', 15, 2)->nullable();
            
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            
            $table->index(['company_id']);
            $table->index(['invoice_id']);
            $table->index(['status']);
            $table->index(['stripe_payment_intent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
