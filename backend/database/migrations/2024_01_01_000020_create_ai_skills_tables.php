<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. AI Skills - Skill definitions (global and company-specific)
        Schema::create('ai_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable(); // NULL = global/system skill
            $table->string('category', 50); // 'project_management', 'crm', 'integration', 'custom'
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable(); // Heroicon name

            // Skill Type & Configuration
            $table->string('skill_type', 30); // 'mcp_tool', 'api_call', 'composite', 'webhook'

            // For MCP tools: references existing MCP tool class
            $table->string('mcp_tool_class', 255)->nullable();

            // For API skills: HTTP configuration
            // Example: {"method": "POST", "url": "https://api.example.com/action", "headers": {...}, "body_template": {...}}
            $table->jsonb('api_config')->default('{}');

            // For composite skills: chain of skill slugs
            $table->jsonb('composite_steps')->default('[]');

            // JSON Schema for input parameters (similar to MCP tools)
            $table->jsonb('input_schema')->default('{}');

            // LLM function calling definition
            $table->jsonb('function_definition')->default('{}');

            // Secret fields that users need to provide (e.g., ['api_key', 'webhook_secret'])
            $table->jsonb('secret_fields')->default('[]');

            // Access control
            $table->boolean('is_system')->default(false); // System skills can't be deleted
            $table->boolean('is_active')->default(true);
            $table->string('requires_permission', 100)->nullable(); // Optional permission check

            $table->timestampsTz();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index('company_id');
            $table->index('category');
            $table->index('skill_type');
            $table->index(['is_active', 'company_id']);
        });

        // 2. User Skill Settings - Per-user configuration
        Schema::create('user_skill_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('skill_id');

            $table->boolean('is_enabled')->default(true); // User can disable skills
            $table->jsonb('custom_config')->default('{}'); // User-specific config (API keys, preferences)

            // Usage tracking
            $table->integer('usage_count')->default(0);
            $table->timestampTz('last_used_at')->nullable();

            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('skill_id')->references('id')->on('ai_skills')->onDelete('cascade');
            $table->unique(['user_id', 'skill_id']);
            $table->index('user_id');
            $table->index(['user_id', 'is_enabled']);
        });

        // 3. AI Skill Executions - Execution logs for debugging and analytics
        Schema::create('ai_skill_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('skill_id')->nullable();
            $table->uuid('conversation_id')->nullable();
            $table->uuid('ai_run_id')->nullable();

            // Execution details
            $table->jsonb('input_params')->nullable();
            $table->jsonb('output_result')->nullable();
            $table->string('status', 20)->default('pending'); // 'pending', 'success', 'error', 'timeout'
            $table->text('error_message')->nullable();
            $table->integer('latency_ms')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('skill_id')->references('id')->on('ai_skills')->onDelete('set null');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');

            $table->index(['user_id', 'created_at']);
            $table->index('skill_id');
            $table->index('conversation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_skill_executions');
        Schema::dropIfExists('user_skill_settings');
        Schema::dropIfExists('ai_skills');
    }
};

