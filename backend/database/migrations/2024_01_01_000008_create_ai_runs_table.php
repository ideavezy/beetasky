<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->uuid('ai_agent_id')->nullable();
            $table->uuid('user_id')->nullable(); // Who triggered
            $table->uuid('conversation_id')->nullable();
            $table->string('run_type', 30); // chat_reply, summary, automation, analysis, tool_call, embedding
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 4)->nullable();
            $table->integer('latency_ms')->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->string('status', 20)->default('success');
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('ai_agent_id')->references('id')->on('ai_agents')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
            $table->index(['company_id', 'created_at']);
            $table->index('conversation_id');
            $table->index('ai_agent_id');
            $table->index('status');
        });

        Schema::create('ai_tool_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ai_run_id');
            $table->uuid('ai_tool_id')->nullable();
            $table->jsonb('arguments')->nullable();
            $table->jsonb('result')->nullable();
            $table->string('status', 20)->default('success');
            $table->text('error_message')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('cascade');
            $table->foreign('ai_tool_id')->references('id')->on('ai_tools')->onDelete('set null');
            $table->index('ai_run_id');
            $table->index('ai_tool_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_logs');
        Schema::dropIfExists('ai_runs');
    }
};

