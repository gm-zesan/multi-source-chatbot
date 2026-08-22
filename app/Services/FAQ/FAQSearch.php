<?php

declare(strict_types=1);

namespace App\Services\FAQ;

use App\Services\NLP\TextPreprocessor;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Database\Eloquent\Collection;

class FAQSearch
{
    public function __construct(
        private readonly RetrievalClient $retrievalClient,
        private readonly ?TextPreprocessor $preprocessor = null,
    ) {}

    /**
     * Run knowledge retrieval via the Python Retrieval Service client.
     *
     * @param string   $query           The customer query.
     * @param int      $perPage         Results per page / top-k.
     * @param int|null $workspaceId     Optional workspace filter.
     * @param bool     $isPreprocessed  Set to true if query has already been preprocessed upstream.
     * @return Collection<int, FAQSearchResult>
     */
    public function search(
        string $query,
        int $perPage = 10,
        ?int $workspaceId = null,
        bool $isPreprocessed = false,
    ): Collection {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return new Collection();
        }

        $processedQuery = $trimmed;
        if (! $isPreprocessed && $this->preprocessor !== null) {
            $processed = $this->preprocessor->process($trimmed, language: 'en');
            $processedQuery = $processed->normalized;
        }

        return $this->retrievalClient->search(
            query: $processedQuery,
            workspaceId: $workspaceId,
            topK: $perPage,
        );
    }
}
