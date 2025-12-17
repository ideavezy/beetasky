<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable pgvector extension for embeddings
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('source_type', 30); // contact, project, task, conversation, file, manual, other
            $table->uuid('source_id')->nullable();
            $table->string('title')->nullable();
            $table->text('raw_text');
            $table->integer('chunk_index')->default(0);
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index('company_id');
            $table->index(['source_type', 'source_id']);
        });
        
        // Add vector column for embeddings (1536 dimensions for OpenAI ada-002)
        DB::statement('ALTER TABLE knowledge_documents ADD COLUMN embedding vector(1536)');
        
        // Create index for vector similarity search
        DB::statement('CREATE INDEX idx_knowledge_docs_embedding ON knowledge_documents USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};

