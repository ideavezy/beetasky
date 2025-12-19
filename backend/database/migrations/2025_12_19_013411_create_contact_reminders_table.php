<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('contact_id');
            $table->uuid('user_id');
            $table->string('type', 50)->default('follow_up');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestampTz('remind_at');
            $table->boolean('is_completed')->default(false);
            $table->timestampTz('completed_at')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestampsTz();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['company_id', 'user_id', 'remind_at'], 'idx_reminders_user_date');
            $table->index(['company_id', 'is_completed'], 'idx_reminders_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_reminders');
    }
};
