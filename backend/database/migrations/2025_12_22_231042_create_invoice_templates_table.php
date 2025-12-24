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
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            
            $table->string('name');
            $table->text('description')->nullable();
            
            // Template settings
            $table->jsonb('layout')->default('{}');
            
            // Payment terms
            $table->text('default_terms')->default('Net 30');
            $table->text('default_notes')->nullable();
            
            // Tax/discount defaults
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->string('default_tax_label', 100)->default('Tax');
            
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->uuid('created_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['company_id']);
            $table->index(['company_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
