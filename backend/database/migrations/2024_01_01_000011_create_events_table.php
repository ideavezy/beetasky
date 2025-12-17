<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('entity_type', 50); // contact, project, task, conversation, company
            $table->uuid('entity_id');
            
            // Who did it
            $table->string('actor_type', 20)->default('system'); // user, contact, ai_agent, system
            $table->uuid('actor_id')->nullable();
            
            // What happened
            $table->string('event_type', 50); // task_created, status_changed, etc.
            $table->jsonb('data')->default('{}'); // Diffs, values, context
            
            $table->timestampTz('occurred_at')->useCurrent();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index(['company_id', 'occurred_at']);
            $table->index(['entity_type', 'entity_id', 'occurred_at']);
            $table->index(['actor_type', 'actor_id']);
            $table->index(['company_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

