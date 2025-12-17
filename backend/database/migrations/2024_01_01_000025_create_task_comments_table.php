<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('company_id');
            $table->text('content');
            $table->boolean('ai_generated')->default(false);
            $table->uuid('ai_run_id')->nullable(); // For AI tracing
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');
            
            $table->index('task_id');
            $table->index('company_id');
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};

