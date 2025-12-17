<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->uuid('comment_id')->nullable(); // Can be attached to task or comment
            $table->uuid('company_id');
            $table->uuid('uploaded_by');
            $table->string('filename');
            $table->string('bunny_filename')->nullable();
            $table->string('path', 500);
            $table->string('mime_type', 100);
            $table->bigInteger('size'); // Size in bytes
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestampsTz();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('comment_id')->references('id')->on('task_comments')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->index('task_id');
            $table->index('comment_id');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};

