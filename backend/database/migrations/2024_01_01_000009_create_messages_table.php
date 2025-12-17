<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            
            // Polymorphic sender
            $table->string('sender_type', 20); // user, contact, ai_agent, system, tool
            $table->uuid('sender_id')->nullable();
            
            // LLM role mapping
            $table->string('role', 20)->default('user'); // user, assistant, system, tool
            
            // Message classification
            $table->string('message_type', 20)->default('chat'); // chat, note, event, command, error
            
            $table->text('content');
            $table->jsonb('metadata')->default('{}');
            $table->jsonb('attachments')->nullable();
            $table->jsonb('read_by')->default('[]');
            
            // Threading (self-reference added after table creation)
            $table->uuid('reply_to_message_id')->nullable();
            
            // AI tracing
            $table->uuid('ai_run_id')->nullable();
            
            $table->softDeletesTz();
            $table->timestampsTz();
            
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');
            $table->index(['conversation_id', 'created_at']);
            $table->index('ai_run_id');
            $table->index(['sender_type', 'sender_id']);
        });
        
        // Add self-referential foreign key after table is created
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('reply_to_message_id')->references('id')->on('messages')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
