<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->nullable();
            $table->uuid('company_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo');
            $table->string('priority', 20)->default('medium');
            $table->uuid('assigned_to')->nullable();
            $table->timestampTz('due_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->integer('order')->default(0);
            $table->jsonb('tags')->default('[]');
            $table->softDeletesTz();
            $table->timestampsTz();
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index('project_id');
            $table->index('company_id');
            $table->index('assigned_to');
            $table->index(['company_id', 'status', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

