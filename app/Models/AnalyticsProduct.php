<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalyticsProduct extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'analytics_products';

    protected $fillable = [
        'workspace_id',
        'document_id',
        'generation_id',
        'source_row_key',
        'source_type',
        'name',
        'sku',
        'barcode',
        'category',
        'item_type',
        'unit_price',
        'cost_price',
        'description',
        'source_metadata',
        'is_active',
    ];

    protected $casts = [
        'unit_price'      => 'decimal:2',
        'cost_price'      => 'decimal:2',
        'is_active'       => 'boolean',
        'source_metadata' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(AnalyticsOrderItem::class, 'product_id');
    }

    /**
     * Scope query to only include active catalog products:
     * - Preserves legacy/manual products (generation_id is null or document_id is null).
     * - Restricts document-backed products strictly to the document's active_generation_id.
     * - Ensures soft-deleted rows are excluded.
     */
    public function scopeActiveCatalog(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('document_id')
                    ->orWhereNull('generation_id')
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw(1)
                            ->from('knowledge_documents')
                            ->whereColumn('knowledge_documents.id', 'analytics_products.document_id')
                            ->whereColumn('knowledge_documents.active_generation_id', 'analytics_products.generation_id')
                            ->where('knowledge_documents.is_active', true)
                            ->whereNull('knowledge_documents.deleted_at');
                    });
            });
    }
}
