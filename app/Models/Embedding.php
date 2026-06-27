<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Embedding Model
 *
 * Stores 384-dimension vector embeddings for databases, tables, and aliases
 * used by the ContextRouter for semantic routing via cosine similarity.
 *
 * @property int         $id
 * @property string      $entity_type   Entity type: 'database', 'table', 'alias'
 * @property string      $entity_name   Human-readable name
 * @property string      $entity_key    Unique identifier within its type
 * @property string|null $source_id     Database connection name (db_01, db_02, etc.)
 * @property array       $embedding     384-dimension normalized vector as array
 * @property array|null  $metadata      Extra context (descriptions, column lists, aliases)
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @method static Builder|static byType(string $type)
 * @method static Builder|static bySource(string $sourceId)
 * @method static Builder|static byEntityKey(string $key)
 */
class Embedding extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'entity_type',
        'entity_name',
        'entity_key',
        'source_id',
        'embedding',
        'metadata',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'embedding' => 'array',
        'metadata'  => 'array',
    ];

    // ── Entity Type Constants ──

    public const ENTITY_TYPE_DATABASE = 'database';
    public const ENTITY_TYPE_TABLE    = 'table';
    public const ENTITY_TYPE_ALIAS    = 'alias';
    public const ENTITY_TYPE_COLUMN   = 'column';
    public const ENTITY_TYPE_QUERY    = 'query_type';

    /**
     * All valid entity types.
     *
     * @var array<string, string>
     */
    public const ALL_TYPES = [
        self::ENTITY_TYPE_DATABASE,
        self::ENTITY_TYPE_TABLE,
        self::ENTITY_TYPE_ALIAS,
        self::ENTITY_TYPE_COLUMN,
        self::ENTITY_TYPE_QUERY,
    ];

    // ── Scopes ──

    /**
     * Scope query to a specific entity type.
     *
     * Usage: Embedding::byType('database')->get();
     *
     * @param  Builder $query
     * @param  string  $type  One of: 'database', 'table', 'alias', 'column', 'query_type'
     * @return Builder
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        if (!in_array($type, self::ALL_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid entity type '{$type}'. Valid types: " . implode(', ', self::ALL_TYPES)
            );
        }

        return $query->where('entity_type', $type);
    }

    /**
     * Scope query to a specific database source.
     *
     * Usage: Embedding::bySource('db_01')->get();
     *
     * @param  Builder $query
     * @param  string  $sourceId  Database connection name (db_01, db_02, etc.)
     * @return Builder
     */
    public function scopeBySource(Builder $query, string $sourceId): Builder
    {
        return $query->where('source_id', $sourceId);
    }

    /**
     * Scope query to a specific entity key.
     *
     * Usage: Embedding::byEntityKey('customers')->get();
     *
     * @param  Builder $query
     * @param  string  $key  The entity key to filter by
     * @return Builder
     */
    public function scopeByEntityKey(Builder $query, string $key): Builder
    {
        return $query->where('entity_key', $key);
    }

    // ── Legacy Scope Aliases (for backward compatibility) ──

    /**
     * Alias for byType(). Use byType() instead.
     *
     * @deprecated Use byType() instead
     */
    public function scopeOfType($query, string $type): Builder
    {
        return $this->scopeByType($query, $type);
    }

    /**
     * Alias for bySource(). Use bySource() instead.
     *
     * @deprecated Use bySource() instead
     */
    public function scopeForSource($query, string $sourceId): Builder
    {
        return $this->scopeBySource($query, $sourceId);
    }

    // ── Relationships ──

    /**
     * Relationship to the source table this embedding may describe.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sourceTable()
    {
        return $this->belongsTo(SourceTable::class, 'entity_key', 'table_name');
    }

    // ── Helpers ──

    /**
     * Get the embedding vector as an array.
     * Returns an empty array if no embedding is stored.
     *
     * @return array<int, float>
     */
    public function getVector(): array
    {
        return $this->embedding ?? [];
    }

    /**
     * Get the dimensionality of the embedding vector.
     *
     * @return int
     */
    public function dimensions(): int
    {
        return count($this->getVector());
    }

    /**
     * Check if this entity has a valid embedding vector.
     *
     * @return bool
     */
    public function hasValidEmbedding(): bool
    {
        return !empty($this->embedding) && is_array($this->embedding);
    }
}
