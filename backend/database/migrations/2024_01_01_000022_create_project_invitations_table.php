<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('company_id');
            $table->uuid('inviter_id');
            $table->string('email');
            $table->string('token', 100)->unique();
            $table->string('role', 20)->default('member');
            $table->string('scope', 20)->default('project'); // project, topic, task
            $table->uuid('scope_id')->nullable(); // ID of the topic or task if scope is not project
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('inviter_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index('project_id');
            $table->index('company_id');
            $table->index('email');
            $table->index(['project_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_invitations');
    }
};

