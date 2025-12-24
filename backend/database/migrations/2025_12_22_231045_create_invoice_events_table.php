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
        Schema::create('invoice_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            
            $table->string('event_type', 50);
            $table->jsonb('event_data')->default('{}');
            
            $table->string('actor_type', 20)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestampTz('created_at');
            
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            
            $table->index(['invoice_id', 'created_at']);
            $table->index(['invoice_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_events');
    }
};
