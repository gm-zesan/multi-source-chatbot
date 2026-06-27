<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the `embeddings` table that stores 384-dimension vector embeddings
     * for databases, tables, and aliases used by the ContextRouter.
     *
     * Entity types:
     *   - 'database' : Represents a database connection (db_01, db_02, etc.)
     *   - 'table'    : Represents a table within a database (customers, products, etc.)
     *   - 'alias'    : An alternative name referring to a table (client → customers)
     *
     * The `embedding` column stores a JSON array of 384 float values generated
     * by the sentence-transformers/all-MiniLM-L6-v2 model.
     *
     * Indexes:
     *   - idx_embeddings_entity_type  : Fast filtering by entity type
     *   - idx_embeddings_source_id   : Fast filtering by source connection
     *   - unq_embeddings_entity      : Prevents duplicate embeddings for same entity
     */
    public function up(): void
    {
        Schema::create('embeddings', function (Blueprint $table) {

            // ── Primary Key ──
            $table->id();

            // ── Entity Identification ──
            // Type discriminator: 'database', 'table', or 'alias'
            // Using string column for maximum compatibility across MySQL/MariaDB/SQLite
            $table->string('entity_type', 50)
                  ->comment('Entity type discriminator: database, table, or alias');

            // Human-readable name (e.g., "Customer Database", "Products Table")
            $table->string('entity_name', 255)
                  ->comment('Human-readable entity name for display purposes');

            // Unique key for lookups (e.g., database name "db_01", table name "customers")
            $table->string('entity_key', 255)
                  ->comment('Unique identifier for the entity within its type');

            // Database connection name this entity belongs to (nullable for query_type entities)
            $table->string('source_id', 50)
                  ->nullable()
                  ->comment('Database connection name this entity belongs to (db_01, db_02, etc.)');

            // ── Vector Data ──
            // 384-dimension embedding vector normalized to unit length
            // Stored as JSON array: [0.1234, -0.5678, ..., 0.9012]
            $table->json('embedding')
                  ->comment('384-dimension normalized embedding vector as JSON array');

            // Extra context: description, column list, aliases, etc.
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional context: descriptions, column lists, aliases, timestamps');

            // ── Timestamps ──
            $table->timestamps();

            // ── Indexes ──
            // Fast lookup by entity type (e.g., all database embeddings)
            $table->index('entity_type', 'idx_embeddings_entity_type');

            // Fast lookup by source connection (e.g., all tables in db_01)
            $table->index('source_id', 'idx_embeddings_source_id');

            // Unique constraint: one embedding per entity type + key combination
            $table->unique(['entity_type', 'entity_key'], 'unq_embeddings_entity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the embeddings table and all associated indexes.
     */
    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};
