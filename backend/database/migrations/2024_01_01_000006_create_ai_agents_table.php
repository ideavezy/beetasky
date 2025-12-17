<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable(); // NULL for global agents
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('model', 100);
            $table->decimal('temperature', 3, 2)->default(0.3);
            $table->text('system_prompt')->nullable();
            $table->jsonb('settings')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index('company_id');
            $table->index('slug');
        });

        Schema::create('ai_tools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->jsonb('schema'); // JSON Schema for arguments
            $table->string('handler')->nullable(); // Backend handler class/method
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tools');
        Schema::dropIfExists('ai_agents');
    }
};

