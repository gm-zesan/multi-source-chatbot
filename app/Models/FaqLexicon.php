<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqLexicon extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'faq_lexicons';

    protected $fillable = [
        'faq_id',
        'workspace_id',
        'domain',
        'intent',
        'canonical_terms',
        'bangla_terms',
        'commerce_terms',
        'generated_by',
        'is_validated',
    ];

    protected function casts(): array
    {
        return [
            'canonical_terms' => 'array',
            'bangla_terms'    => 'array',
            'commerce_terms'  => 'array',
            'is_validated'    => 'boolean',
        ];
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(FAQ::class, 'faq_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Get all flattened unique search terms across all categories.
     *
     * @return string[]
     */
    public function allTerms(): array
    {
        $merged = array_merge(
            $this->canonical_terms ?? [],
            $this->bangla_terms ?? [],
            $this->commerce_terms ?? []
        );

        return array_values(array_unique(array_filter(array_map('trim', $merged))));
    }
}
