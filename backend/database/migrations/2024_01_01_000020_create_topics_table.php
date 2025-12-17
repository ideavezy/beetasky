<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('company_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->string('color', 20)->nullable();
            $table->boolean('is_locked')->default(false);
            $table->uuid('locked_by')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index('project_id');
            $table->index('company_id');
            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};

