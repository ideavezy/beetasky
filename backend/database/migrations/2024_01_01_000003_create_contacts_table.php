<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('type', 20)->default('lead');
            $table->string('status', 50)->default('new');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->jsonb('address')->nullable();
            $table->jsonb('custom_fields')->default('{}');
            $table->jsonb('tags')->default('[]');
            $table->uuid('assigned_to')->nullable();
            $table->string('source', 100)->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->unique(['company_id', 'email'], 'uniq_contact_email_company');
            $table->index('company_id');
            $table->index(['company_id', 'type']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

