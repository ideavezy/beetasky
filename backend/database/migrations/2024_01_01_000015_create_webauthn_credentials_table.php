<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('authenticatable_id');
            $table->string('authenticatable_type');
            
            // WebAuthn credential data
            $table->string('name')->nullable();
            $table->string('credential_id')->unique();
            $table->text('public_key');
            $table->text('attestation_format')->nullable();
            $table->text('certificate')->nullable();
            $table->unsignedBigInteger('counter')->default(0);
            $table->json('transports')->nullable();
            
            // Discoverable credential
            $table->string('user_handle')->nullable();
            
            // Timestamps
            $table->timestampsTz();
            $table->timestampTz('disabled_at')->nullable();
            
            $table->index(['authenticatable_type', 'authenticatable_id'], 'webauthn_auth_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};

