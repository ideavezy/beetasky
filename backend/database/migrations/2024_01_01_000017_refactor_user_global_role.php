<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Refactor users table: change 'role' to 'global_role'
 * 
 * Global role distinguishes platform-level access:
 * - admin: Beemud platform administrators (super admin)
 * - user: Regular users (their company-level roles defined in company_user)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add new global_role column
        Schema::table('users', function (Blueprint $table) {
            $table->string('global_role', 20)->default('user')->after('avatar_url');
        });

        // Step 2: Migrate existing roles
        // 'admin' stays 'admin', everything else -> 'user'
        DB::statement("
            UPDATE users 
            SET global_role = CASE 
                WHEN role = 'admin' THEN 'admin' 
                ELSE 'user' 
            END
        ");

        // Step 3: Drop old role column and index
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });

        // Step 4: Add constraint and index on new column
        Schema::table('users', function (Blueprint $table) {
            $table->index('global_role');
        });

        // Add check constraint (PostgreSQL)
        DB::statement("
            ALTER TABLE users 
            ADD CONSTRAINT chk_users_global_role 
            CHECK (global_role IN ('admin', 'user'))
        ");
    }

    public function down(): void
    {
        // Remove check constraint
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS chk_users_global_role");

        // Add back old role column
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('avatar_url');
        });

        // Migrate data back
        DB::statement("
            UPDATE users 
            SET role = CASE 
                WHEN global_role = 'admin' THEN 'admin' 
                ELSE 'staff' 
            END
        ");

        // Drop new column and index
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['global_role']);
            $table->dropColumn('global_role');
        });

        // Add index back
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });
    }
};

