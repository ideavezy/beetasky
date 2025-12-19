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
        // Main Flow Queue Container
        Schema::create('ai_flow_queues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->uuid('conversation_id')->nullable();

            // Flow metadata
            $table->string('title', 255);
            $table->text('original_request');

            // Flow status
            $table->string('status', 30)->default('planning');

            // Progress tracking
            $table->integer('total_steps')->default(0);
            $table->integer('completed_steps')->default(0);
            $table->uuid('current_step_id')->nullable();

            // Context passed between steps
            $table->jsonb('flow_context')->default('{}');

            // AI planning metadata
            $table->uuid('ai_run_id')->nullable();
            $table->text('planning_prompt')->nullable();

            // Error handling
            $table->text('last_error')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);

            // Timing
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');

            // Indexes
            $table->index('company_id');
            $table->index('user_id');
            $table->index('conversation_id');
            $table->index('status');
        });

        // Individual Flow Steps
        Schema::create('ai_flow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('flow_id');

            // Step position & ordering
            $table->integer('position');
            $table->uuid('parent_step_id')->nullable();

            // Step type & action
            $table->string('step_type', 30);
            $table->string('skill_slug', 100)->nullable();

            // Step description
            $table->string('title', 255);
            $table->text('description')->nullable();

            // Input parameters
            $table->jsonb('input_params')->default('{}');
            $table->jsonb('param_mappings')->default('{}');

            // Status tracking
            $table->string('status', 30)->default('pending');

            // Execution result
            $table->jsonb('result')->nullable();
            $table->text('error_message')->nullable();

            // User prompt config
            $table->string('prompt_type', 30)->nullable();
            $table->text('prompt_message')->nullable();
            $table->jsonb('prompt_options')->nullable();
            $table->jsonb('user_response')->nullable();

            // Conditional execution
            $table->jsonb('condition')->nullable();
            $table->integer('on_success_goto')->nullable();
            $table->integer('on_fail_goto')->nullable();

            // AI decision config
            $table->text('ai_decision_prompt')->nullable();
            $table->jsonb('ai_decision_result')->nullable();

            // Timing
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // Foreign keys (self-reference added separately)
            $table->foreign('flow_id')->references('id')->on('ai_flow_queues')->onDelete('cascade');

            // Indexes
            $table->index(['flow_id', 'position']);
            $table->index('status');
            $table->index('skill_slug');
            $table->index('parent_step_id');
        });

        // Add self-referencing foreign key after steps table exists
        Schema::table('ai_flow_steps', function (Blueprint $table) {
            $table->foreign('parent_step_id')->references('id')->on('ai_flow_steps')->onDelete('set null');
        });

        // Add current_step_id foreign key after steps table exists
        Schema::table('ai_flow_queues', function (Blueprint $table) {
            $table->foreign('current_step_id')->references('id')->on('ai_flow_steps')->onDelete('set null');
        });

        // Flow Audit Logs
        Schema::create('ai_flow_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('flow_id');
            $table->uuid('step_id')->nullable();

            // Log type
            $table->string('log_type', 30);

            // Log details
            $table->text('message')->nullable();
            $table->jsonb('data')->default('{}');

            // Actor
            $table->string('actor_type', 20)->nullable();
            $table->uuid('actor_id')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('flow_id')->references('id')->on('ai_flow_queues')->onDelete('cascade');
            $table->foreign('step_id')->references('id')->on('ai_flow_steps')->onDelete('cascade');

            // Indexes
            $table->index(['flow_id', 'created_at']);
            $table->index('step_id');
            $table->index('log_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_flow_queues', function (Blueprint $table) {
            $table->dropForeign(['current_step_id']);
        });

        Schema::table('ai_flow_steps', function (Blueprint $table) {
            $table->dropForeign(['parent_step_id']);
        });

        Schema::dropIfExists('ai_flow_logs');
        Schema::dropIfExists('ai_flow_steps');
        Schema::dropIfExists('ai_flow_queues');
    }
};

