<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\SyncLexiconToEmbeddingServiceJob;
use App\Models\ActionIntentMapping;
use App\Models\ConceptPhrasePattern;
use App\Models\LexiconDomainEntry;
use App\Models\PolicyIntentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LexiconObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_domain_entry_dispatches_sync_job(): void
    {
        Queue::fake();

        $entry = LexiconDomainEntry::create([
            'workspace_id' => 1,
            'concept_key'  => 'OBSERVER_TEST',
            'pattern'      => 'obs pattern',
            'expansion'    => 'obs expansion',
            'language'     => 'bn',
            'status'       => 'ACTIVE',
            'version'      => 1,
        ]);

        Queue::assertPushed(SyncLexiconToEmbeddingServiceJob::class, function ($job) {
            return $job->workspaceId === 1;
        });

        // Test version bump on update
        Queue::fake();
        $entry->update(['expansion' => 'obs expansion updated']);

        $this->assertEquals(2, $entry->fresh()->version);
        Queue::assertPushed(SyncLexiconToEmbeddingServiceJob::class);
    }

    public function test_deleting_concept_pattern_dispatches_sync_job(): void
    {
        Queue::fake();

        $pattern = ConceptPhrasePattern::create([
            'workspace_id' => 0,
            'concept_key'  => 'OBS_CONCEPT',
            'pattern_type' => 'POSITIVE',
            'phrase'       => 'obs phrase',
            'status'       => 'ACTIVE',
            'version'      => 1,
        ]);

        Queue::assertPushed(SyncLexiconToEmbeddingServiceJob::class);

        Queue::fake();
        $pattern->delete();

        Queue::assertPushed(SyncLexiconToEmbeddingServiceJob::class, function ($job) {
            return $job->workspaceId === 0;
        });
    }
}
