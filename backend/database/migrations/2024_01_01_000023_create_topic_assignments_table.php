<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('topic_id');
            $table->uuid('user_id');
            $table->uuid('company_id');
            $table->uuid('assigned_by')->nullable();
            $table->timestampsTz();

            $table->foreign('topic_id')->references('id')->on('topics')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['topic_id', 'user_id']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_assignments');
    }
};

