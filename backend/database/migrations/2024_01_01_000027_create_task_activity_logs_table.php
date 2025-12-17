<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 50); // create, update, delete, complete, assign, comment, etc.
            $table->string('loggable_type', 100); // projects, topics, tasks, task_comments
            $table->uuid('loggable_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('description');
            $table->uuid('ai_run_id')->nullable(); // For AI tracing
            $table->timestampsTz();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');
            
            $table->index('company_id');
            $table->index(['loggable_type', 'loggable_id']);
            $table->index(['company_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activity_logs');
    }
};

