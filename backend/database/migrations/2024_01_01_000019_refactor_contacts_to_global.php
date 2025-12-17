<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Refactor contacts table from company-scoped to global
 * 
 * The new design:
 * - contacts: Global identity (one person across all companies)
 * - company_contacts: Relationship pivot (lead/customer/prospect/vendor per company)
 * 
 * This allows:
 * - Same person = lead of Company A, customer of Company B, staff of Company C
 * - Contact can optionally have a login (auth_user_id -> users)
 * - No data duplication when the same person interacts with multiple companies
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Drop foreign key constraints that reference contacts table
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });
        
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        // Step 2: Create new contacts table structure
        Schema::create('contacts_new', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Contact identity (global, not company-scoped)
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->jsonb('address')->nullable();
            
            // Optional link to user account (for customer portal login)
            $table->uuid('auth_user_id')->nullable();
            
            // Additional info
            $table->string('organization')->nullable(); // Contact's own company/org
            $table->string('job_title')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->jsonb('custom_fields')->default('{}');
            $table->jsonb('tags')->default('[]');
            
            $table->softDeletesTz();
            $table->timestampsTz();
            
            $table->foreign('auth_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            $table->index('email');
            $table->index('auth_user_id');
        });

        // Step 3: Create company_contacts pivot table
        Schema::create('company_contacts', function (Blueprint $table) {
            $table->id(); // bigserial for pivot tables is fine
            $table->uuid('company_id');
            $table->uuid('contact_id');
            
            // Relationship type
            $table->string('relation_type', 30)->default('lead');
            
            // Status within this company relationship
            $table->string('status', 50)->default('active');
            
            // Lead/customer source tracking
            $table->string('source', 100)->nullable(); // e.g., 'web_form', 'import', 'manual'
            
            // Assignment within company
            $table->uuid('assigned_to')->nullable();
            
            // Conversion tracking
            $table->timestampTz('converted_at')->nullable();
            
            // Activity tracking
            $table->timestampTz('first_seen_at')->useCurrent();
            $table->timestampTz('last_activity_at')->nullable();
            
            // Additional metadata per company relationship
            $table->jsonb('metadata')->default('{}');
            
            $table->timestampsTz();
            
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
            
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts_new')
                ->onDelete('cascade');
            
            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            // A contact can have multiple relation types with same company
            // e.g., start as 'lead', become 'customer' (historical tracking)
            // Use unique on company + contact + relation_type for current state
            $table->unique(['company_id', 'contact_id', 'relation_type'], 'uniq_company_contact_relation');
            
            $table->index('company_id');
            $table->index('contact_id');
            $table->index(['company_id', 'relation_type']);
            $table->index(['company_id', 'status']);
            $table->index('assigned_to');
        });

        // Step 4: Add check constraint for relation_type (PostgreSQL)
        DB::statement("
            ALTER TABLE company_contacts 
            ADD CONSTRAINT chk_company_contacts_relation_type 
            CHECK (relation_type IN ('lead', 'customer', 'prospect', 'vendor', 'partner'))
        ");

        // Step 5: Migrate existing data from old contacts table
        // Group by email to deduplicate contacts that might exist across companies
        DB::statement("
            INSERT INTO contacts_new (id, full_name, email, phone, address, custom_fields, tags, deleted_at, created_at, updated_at)
            SELECT DISTINCT ON (COALESCE(email, gen_random_uuid()::text))
                id,
                CONCAT(first_name, ' ', COALESCE(last_name, '')) as full_name,
                email,
                phone,
                address,
                custom_fields,
                tags,
                deleted_at,
                created_at,
                updated_at
            FROM contacts
            ORDER BY COALESCE(email, gen_random_uuid()::text), created_at ASC
        ");

        // Step 6: Create company_contacts relationships from old contacts
        DB::statement("
            INSERT INTO company_contacts (company_id, contact_id, relation_type, status, source, assigned_to, converted_at, first_seen_at, created_at, updated_at)
            SELECT 
                c.company_id,
                c.id as contact_id,
                c.type as relation_type,
                c.status,
                c.source,
                c.assigned_to,
                c.converted_at,
                c.created_at as first_seen_at,
                c.created_at,
                c.updated_at
            FROM contacts c
            INNER JOIN contacts_new cn ON cn.id = c.id
        ");

        // Step 7: Drop old contacts table and rename new one
        Schema::dropIfExists('contacts');
        Schema::rename('contacts_new', 'contacts');

        // Step 8: Add unique index on email (only for non-null emails)
        DB::statement("
            CREATE UNIQUE INDEX idx_contacts_email_unique 
            ON contacts (email) 
            WHERE email IS NOT NULL AND deleted_at IS NULL
        ");

        // Step 9: Re-add foreign key constraints to projects and conversations
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');
        });
        
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Drop foreign keys from projects and conversations first
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });
        
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        // Recreate old contacts table structure
        Schema::create('contacts_old', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('type', 20)->default('lead');
            $table->string('status', 50)->default('new');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->jsonb('address')->nullable();
            $table->jsonb('custom_fields')->default('{}');
            $table->jsonb('tags')->default('[]');
            $table->uuid('assigned_to')->nullable();
            $table->string('source', 100)->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->unique(['company_id', 'email'], 'uniq_contact_email_company_old');
            $table->index('company_id');
            $table->index(['company_id', 'type']);
            $table->index('assigned_to');
        });

        // Migrate data back (with some data loss for cross-company contacts)
        DB::statement("
            INSERT INTO contacts_old (id, company_id, type, status, first_name, last_name, email, phone, address, custom_fields, tags, assigned_to, source, converted_at, deleted_at, created_at, updated_at)
            SELECT 
                c.id,
                cc.company_id,
                cc.relation_type as type,
                cc.status,
                SPLIT_PART(c.full_name, ' ', 1) as first_name,
                NULLIF(TRIM(SUBSTRING(c.full_name FROM POSITION(' ' IN c.full_name))), '') as last_name,
                c.email,
                c.phone,
                c.address,
                c.custom_fields,
                c.tags,
                cc.assigned_to,
                cc.source,
                cc.converted_at,
                c.deleted_at,
                c.created_at,
                c.updated_at
            FROM contacts c
            INNER JOIN company_contacts cc ON cc.contact_id = c.id
        ");

        // Drop new structure
        Schema::dropIfExists('company_contacts');
        Schema::dropIfExists('contacts');
        
        // Rename old back to contacts
        Schema::rename('contacts_old', 'contacts');

        // Re-add foreign keys to projects and conversations
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');
        });
        
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');
        });
    }
};

