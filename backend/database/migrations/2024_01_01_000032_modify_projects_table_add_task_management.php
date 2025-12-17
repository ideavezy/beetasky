<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('contact_id');
            $table->boolean('ai_enabled')->default(false)->after('tags');
            $table->jsonb('ai_settings')->nullable()->after('ai_enabled');
            $table->jsonb('settings')->default('{}')->after('ai_settings');
            $table->integer('import_count')->default(0)->after('settings');
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'ai_enabled', 'ai_settings', 'settings', 'import_count']);
        });
    }
};

