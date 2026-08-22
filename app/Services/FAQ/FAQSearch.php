<?php

declare(strict_types=1);

namespace App\Services\FAQ;

use App\Services\Retrieval\RetrievalClient;
use Illuminate\Database\Eloquent\Collection;

class FAQSearch
{
    public function __construct(
        private readonly RetrievalClient $retrievalClient,
    ) {}

    /**
     * Run knowledge retrieval via the Python Retrieval Service client.
     *
     * @param string   $query       The customer query.
     * @param int      $perPage     Results per page / top-k.
     * @param int|null $workspaceId Optional workspace filter.
     * @return Collection<int, FAQSearchResult>
     */
    public function search(
        string $query,
        int $perPage = 10,
        ?int $workspaceId = null,
    ): Collection {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return new Collection();
        }

        return $this->retrievalClient->search(
            query: $trimmed,
            workspaceId: $workspaceId,
            topK: $perPage,
        );
    }
}

