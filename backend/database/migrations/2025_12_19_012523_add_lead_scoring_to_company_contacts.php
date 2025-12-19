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
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->integer('lead_score')->default(0)->after('metadata');
            $table->jsonb('score_factors')->default('{}')->after('lead_score');
            $table->timestampTz('score_updated_at')->nullable()->after('score_factors');
        });

        // Add index for querying by lead score
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->index(['company_id', 'lead_score'], 'idx_company_contacts_lead_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->dropIndex('idx_company_contacts_lead_score');
            $table->dropColumn(['lead_score', 'score_factors', 'score_updated_at']);
        });
    }
};
