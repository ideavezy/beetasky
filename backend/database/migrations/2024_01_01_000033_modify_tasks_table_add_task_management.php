<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add topic_id for kanban column organization
            $table->uuid('topic_id')->nullable()->after('project_id');
            
            // Rich text content
            $table->text('content')->nullable()->after('description');
            
            // Completion tracking
            $table->boolean('completed')->default(false)->after('status');
            $table->uuid('completed_by')->nullable()->after('completed_at');
            
            // Locking for concurrent editing
            $table->boolean('is_locked')->default(false)->after('tags');
            $table->uuid('locked_by')->nullable()->after('is_locked');
            $table->timestampTz('locked_at')->nullable()->after('locked_by');
            
            // AI integration
            $table->boolean('ai_generated')->default(false)->after('locked_at');
            $table->uuid('ai_run_id')->nullable()->after('ai_generated');
            
            // Foreign keys
            $table->foreign('topic_id')->references('id')->on('topics')->onDelete('set null');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->onDelete('set null');
            
            // Indexes
            $table->index('topic_id');
            $table->index(['topic_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['topic_id']);
            $table->dropForeign(['completed_by']);
            $table->dropForeign(['locked_by']);
            $table->dropForeign(['ai_run_id']);
            $table->dropIndex(['topic_id']);
            $table->dropIndex(['topic_id', 'order']);
            $table->dropColumn([
                'topic_id',
                'content',
                'completed',
                'completed_by',
                'is_locked',
                'locked_by',
                'locked_at',
                'ai_generated',
                'ai_run_id'
            ]);
        });
    }
};

