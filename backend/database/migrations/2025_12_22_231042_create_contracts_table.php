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
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('template_id')->nullable();
            
            // Relationships
            $table->uuid('contact_id')->nullable();
            $table->uuid('project_id')->nullable();
            
            // Contract metadata
            $table->string('title');
            $table->string('contract_number', 100)->unique()->nullable();
            
            // Contract type and pricing
            $table->string('contract_type', 50)->default('fixed_price');
            $table->jsonb('pricing_data')->default('{}');
            
            // Merged content (after variables replaced)
            $table->jsonb('rendered_sections');
            $table->jsonb('merge_field_values')->default('{}');
            
            // Status workflow
            $table->string('status', 50)->default('draft');
            
            // Clickwrap signing
            $table->text('clickwrap_text')->nullable();
            $table->timestampTz('client_signed_at')->nullable();
            $table->string('client_signed_by')->nullable();
            $table->string('client_ip_address', 45)->nullable();
            $table->text('client_user_agent')->nullable();
            
            $table->timestampTz('provider_signed_at')->nullable();
            $table->uuid('provider_signed_by')->nullable();
            
            // PDF storage
            $table->string('pdf_path', 500)->nullable();
            $table->timestampTz('pdf_generated_at')->nullable();
            
            // Delivery
            $table->timestampTz('sent_at')->nullable();
            $table->uuid('sent_by')->nullable();
            $table->timestampTz('expires_at')->nullable();
            
            // Public link
            $table->string('token', 100)->unique()->nullable();
            
            $table->text('notes')->nullable();
            
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('contract_templates')->onDelete('set null');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('provider_signed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('sent_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['company_id']);
            $table->index(['contact_id']);
            $table->index(['project_id']);
            $table->index(['company_id', 'status']);
            $table->index(['token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
