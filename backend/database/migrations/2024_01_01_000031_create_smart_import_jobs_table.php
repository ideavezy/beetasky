<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('company_id');
            $table->uuid('ai_run_id')->nullable(); // Link to AI run for tracing
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->integer('progress')->default(0); // 0-100
            $table->text('message')->nullable();
            $table->jsonb('results')->nullable(); // Imported tasks/topics
            $table->jsonb('source_files')->default('[]'); // Original file references
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');
            
            $table->index('company_id');
            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_import_jobs');
    }
};

