<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('contact_id')->nullable();
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('planning');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->jsonb('tags')->default('[]');
            $table->softDeletesTz();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->index('company_id');
            $table->index('contact_id');
            $table->unique(['company_id', 'code'], 'idx_projects_company_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

