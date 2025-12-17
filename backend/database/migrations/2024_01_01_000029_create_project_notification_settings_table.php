<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_notification_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('user_id');
            $table->uuid('company_id');
            $table->boolean('notify_new_tasks')->default(true);
            $table->boolean('notify_task_assignments')->default(true);
            $table->boolean('notify_task_status_changes')->default(true);
            $table->boolean('notify_comments')->default(true);
            $table->boolean('notify_mentions')->default(true);
            $table->boolean('email_digest')->default(false);
            $table->string('digest_frequency', 20)->default('daily'); // instant, daily, weekly
            $table->jsonb('additional_settings')->default('{}');
            $table->timestampsTz();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            
            $table->unique(['project_id', 'user_id']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_notification_settings');
    }
};

