<?php

declare(strict_types=1);

namespace App\Services\FAQ;

use App\Models\Conversation;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Database\Eloquent\Collection;

class FAQSearch
{
    public function __construct(
        private readonly RetrievalClient $retrievalClient,
        private readonly ?ContextualQueryBuilder $contextualQueryBuilder = null,
    ) {}

    /**
     * Run knowledge retrieval via the Python Retrieval Service client.
     *
     * @param string             $query            The customer query (100% immutable).
     * @param int                $perPage          Results per page / top-k.
     * @param int|null           $workspaceId      Optional workspace filter.
     * @param Conversation|null  $conversation     Optional conversation for multi-turn context.
     * @param string|null        $contextualSignal Optional pre-resolved auxiliary signal.
     * @return Collection<int, FAQSearchResult>
     */
    public function search(
        string $query,
        int $perPage = 10,
        ?int $workspaceId = null,
        ?Conversation $conversation = null,
        ?string $contextualSignal = null,
    ): Collection {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return new Collection();
        }

        if ($contextualSignal === null && $conversation !== null) {
            $builder = $this->contextualQueryBuilder ?? app(ContextualQueryBuilder::class);
            $contextualSignal = $builder->resolveContextualSignal($trimmed, $conversation);
        }

        return $this->retrievalClient->search(
            query: $trimmed,
            workspaceId: $workspaceId,
            topK: $perPage,
            contextualSignal: $contextualSignal,
        );
    }
}

