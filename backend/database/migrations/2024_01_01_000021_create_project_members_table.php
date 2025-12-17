<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('user_id');
            $table->uuid('company_id');
            $table->string('role', 20)->default('member'); // owner, admin, member
            $table->string('status', 20)->default('active'); // active, pending, inactive
            $table->string('invitation_token', 100)->nullable();
            $table->uuid('invited_by')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->boolean('is_customer')->default(false);
            $table->jsonb('permissions')->default('{}');
            $table->timestampsTz();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['project_id', 'user_id']);
            $table->index('company_id');
            $table->index(['project_id', 'status']);
            $table->index('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};

