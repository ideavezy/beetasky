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
        Schema::create('deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('contact_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('stage', 50)->default('qualification');
            $table->integer('probability')->default(10);
            $table->date('expected_close_date')->nullable();
            $table->text('lost_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['company_id', 'stage'], 'idx_deals_company_stage');
            $table->index(['company_id', 'expected_close_date'], 'idx_deals_close_date');
            $table->index(['company_id', 'assigned_to'], 'idx_deals_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
