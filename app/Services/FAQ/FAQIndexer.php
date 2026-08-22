<?php

declare(strict_types=1);

namespace App\Services\FAQ;

use App\Jobs\FAQIndexJob;
use App\Models\FAQ;

class FAQIndexer
{
    /**
     * Dispatch an asynchronous indexing/sync job for a single FAQ.
     */
    public function dispatchIndex(FAQ $faq, string $action = 'index'): void
    {
        FAQIndexJob::dispatch($faq, $action);
    }

    /**
     * Dispatch asynchronous indexing/sync jobs for a batch of FAQs.
     */
    public function dispatchBatch(iterable $faqs, string $action = 'index'): void
    {
        foreach ($faqs as $faq) {
            FAQIndexJob::dispatch($faq, $action);
        }
    }
}
