<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('type', 20)->default('internal');
            $table->string('status', 20)->default('open');
            $table->string('channel', 20)->default('widget');
            $table->uuid('contact_id')->nullable();
            $table->string('name')->nullable();
            $table->timestampTz('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->index('company_id');
            $table->index('contact_id');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->string('participant_type', 20); // user, contact, ai_agent, visitor
            $table->uuid('participant_id');
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('joined_at')->useCurrent();
            
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->unique(['conversation_id', 'participant_type', 'participant_id'], 'conv_participant_unique');
            $table->index('conversation_id');
            $table->index(['participant_type', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};

