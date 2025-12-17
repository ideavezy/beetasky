<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_presentations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('conversation_id')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->string('layout', 50)->default('board'); // board, timeline, insights, dashboard
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index('company_id');
            $table->index('conversation_id');
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('ai_presentation_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('presentation_id');
            $table->string('card_type', 50); // metric, timeline, note, recommendation, risk, action
            $table->string('title')->nullable();
            $table->jsonb('body')->default('{}');
            $table->integer('order')->default(0);
            $table->jsonb('metadata')->default('{}');
            $table->uuid('ai_run_id')->nullable();
            $table->timestampsTz();
            
            $table->foreign('presentation_id')->references('id')->on('ai_presentations')->onDelete('cascade');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');
            $table->index(['presentation_id', 'order']);
            $table->index('card_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_presentation_cards');
        Schema::dropIfExists('ai_presentations');
    }
};

