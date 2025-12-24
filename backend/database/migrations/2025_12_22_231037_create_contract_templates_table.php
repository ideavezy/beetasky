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
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name');
            $table->text('description')->nullable();
            
            // Template builder structure
            $table->jsonb('sections')->default('[]');
            
            // Available merge fields for this template
            $table->jsonb('merge_fields')->default('[]');
            
            // Clickwrap agreement text
            $table->text('clickwrap_text')->default('I agree to the terms and conditions outlined above');
            
            // Default settings
            $table->string('default_contract_type', 50)->default('fixed_price');
            $table->jsonb('default_pricing_data')->default('{}');
            
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['company_id']);
            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
