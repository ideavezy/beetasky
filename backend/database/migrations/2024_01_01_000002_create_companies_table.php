<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->uuid('owner_id')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('billing_status', 20)->default('trial');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->jsonb('settings')->default(json_encode([
                'ai' => [
                    'default_agent' => 'crm_assistant',
                    'tone' => 'professional',
                    'language' => 'en',
                    'blocked_actions' => []
                ]
            ]));
            $table->softDeletesTz();
            $table->timestampsTz();
            
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
            $table->index('slug');
            $table->index('owner_id');
        });

        // Pivot table for users and companies
        Schema::create('company_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->string('role_in_company', 20)->default('staff');
            $table->jsonb('permissions')->default('{}');
            $table->timestampTz('joined_at')->useCurrent();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['company_id', 'user_id']);
            $table->index('company_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};

