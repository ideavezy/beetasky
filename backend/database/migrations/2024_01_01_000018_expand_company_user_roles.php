<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Expand company_user roles for more granular company-level permissions
 * 
 * Roles:
 * - owner: Full control, billing access
 * - manager: Can manage staff and most settings
 * - staff: Standard employee access
 * - agent: Limited access (e.g., support agents)
 * 
 * Also adds is_active flag for soft-disabling membership
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add is_active column
        Schema::table('company_user', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('permissions');
        });

        // Update constraint to allow more roles (PostgreSQL)
        // First remove any existing constraint if present
        DB::statement("
            DO $$ 
            BEGIN
                ALTER TABLE company_user DROP CONSTRAINT IF EXISTS chk_company_user_role;
            EXCEPTION WHEN others THEN NULL;
            END $$
        ");

        // Add new constraint with expanded roles
        DB::statement("
            ALTER TABLE company_user 
            ADD CONSTRAINT chk_company_user_role 
            CHECK (role_in_company IN ('owner', 'manager', 'staff', 'agent'))
        ");

        // Add index for active members
        Schema::table('company_user', function (Blueprint $table) {
            $table->index(['company_id', 'is_active'], 'idx_company_user_active');
        });
    }

    public function down(): void
    {
        // Remove index
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropIndex('idx_company_user_active');
        });

        // Remove expanded constraint
        DB::statement("ALTER TABLE company_user DROP CONSTRAINT IF EXISTS chk_company_user_role");

        // Remove is_active column
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};

