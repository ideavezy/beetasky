<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_notification_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('task_id')->nullable();
            $table->uuid('company_id');
            $table->uuid('recipient_id');
            $table->uuid('actor_id')->nullable();
            $table->string('action', 64); // new_task, assigned_task, task_status_change, new_comment
            $table->jsonb('payload')->nullable();
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('recipient_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['recipient_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_notification_events');
    }
};

