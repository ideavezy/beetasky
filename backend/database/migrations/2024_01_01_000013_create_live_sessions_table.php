<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('session_type', 30)->nullable(); // project_board, meeting, document, presentation
            $table->string('entity_type', 50);
            $table->uuid('entity_id');
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index('company_id');
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('live_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->uuid('user_id')->nullable();
            $table->jsonb('content')->default('{}');
            $table->jsonb('position')->nullable();
            $table->string('color', 20)->nullable();
            $table->timestampsTz();
            
            $table->foreign('session_id')->references('id')->on('live_sessions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_notes');
        Schema::dropIfExists('live_sessions');
    }
};

