<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_task_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('topic_id')->nullable();
            $table->uuid('company_id');
            $table->uuid('ai_run_id')->nullable(); // Link to AI run for tracing
            $table->string('type', 30); // task, summary, reorganization, priority, deadline
            $table->text('content');
            $table->jsonb('metadata')->default('{}'); // Additional AI context
            $table->boolean('applied')->default(false);
            $table->timestampTz('applied_at')->nullable();
            $table->uuid('applied_by')->nullable();
            $table->timestampsTz();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('topics')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');
            $table->foreign('applied_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index('project_id');
            $table->index('company_id');
            $table->index(['project_id', 'applied']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_task_suggestions');
    }
};

