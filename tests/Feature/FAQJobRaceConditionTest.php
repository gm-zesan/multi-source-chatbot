<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FaqLifecycleStatus;
use App\Jobs\FAQIndexJob;
use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\FAQ\FAQIndexer;
use App\Services\FAQ\FAQService;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FAQJobRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private FAQ $faq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Race Condition Workspace',
            'slug' => 'rc-workspace',
        ]);

        $this->faq = FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'question'         => 'What is the standard warranty period?',
            'answer'           => 'Standard warranty is 12 months from purchase.',
            'document_type'    => 'warranty_policy',
            'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
            'is_active'        => true,
            'priority'         => 10,
        ]);
    }

    /**
     * Scenario 1: An FAQ update job is queued, the FAQ is deactivated, and the stale
     * update job executes afterward. The FAQ must remain inactive and must not be
     * resurrected in Typesense or database.
     */
    public function test_stale_update_job_after_deactivation_does_not_resurrect_faq_in_typesense_or_db(): void
    {
        // An update job was queued while FAQ was active
        $staleUpdateJob = new FAQIndexJob($this->faq, 'update');

        // Admin deactivates FAQ before the queued update job runs
        $this->faq->update([
            'lifecycle_status' => FaqLifecycleStatus::DRAFT,
            'is_active'        => false,
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);

        // Invariant: Must NEVER call syncFaq on deactivated FAQ
        $mockClient->expects($this->never())
            ->method('syncFaq');

        // It must call deleteFaq to ensure Typesense index does not hold the deactivated FAQ
        $mockClient->expects($this->atLeastOnce())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(true);

        $staleUpdateJob->handle($mockClient);

        // Verify MySQL state remains inactive and not resurrected
        $this->faq->refresh();
        $this->assertFalse($this->faq->is_active, 'Deactivated FAQ was incorrectly reactivated in database.');
        $this->assertNotEquals(FaqLifecycleStatus::ACTIVE, $this->faq->lifecycle_status, 'Deactivated FAQ was marked ACTIVE.');
    }

    /**
     * Scenario 2: An older job executes after a newer FAQ update.
     * The old job must not overwrite the newer content or lifecycle state.
     */
    public function test_older_stale_job_executing_after_newer_update_does_not_overwrite_newer_content_or_lifecycle(): void
    {
        // Job 1 is dispatched for the initial state
        $oldJob = new FAQIndexJob($this->faq, 'update', now()->subSeconds(10)->toIso8601String());

        // A newer update occurs: content is updated to V2 and successfully synced to ACTIVE
        $this->faq->update([
            'question'         => 'What is the extended warranty period? (V2)',
            'answer'           => 'Extended warranty covers up to 36 months. (V2)',
            'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
            'is_active'        => true,
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);

        // Even if old job fails or encounters an error, it must NOT degrade newer FAQ state to SYNC_FAILED
        $mockClient->method('syncFaq')->willReturn(false);

        $oldJob->handle($mockClient);

        $this->faq->refresh();
        $this->assertEquals('What is the extended warranty period? (V2)', $this->faq->question);
        $this->assertEquals(FaqLifecycleStatus::ACTIVE, $this->faq->lifecycle_status, 'Stale job overwrote newer lifecycle state.');
        $this->assertTrue($this->faq->is_active, 'Stale job deactivated newer active FAQ.');
    }

    /**
     * Scenario 3: A deleted/soft-deleted FAQ cannot be recreated by a stale index/update job.
     */
    public function test_soft_deleted_faq_cannot_be_recreated_by_stale_index_or_update_job(): void
    {
        $staleJob = new FAQIndexJob($this->faq, 'update');

        // FAQ is soft-deleted
        $this->faq->delete();

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->never())->method('syncFaq');
        $mockClient->expects($this->atLeastOnce())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(true);

        $staleJob->handle($mockClient);

        $this->faq->refresh();
        $this->assertTrue($this->faq->trashed(), 'Soft-deleted FAQ was untrashed by stale job.');
    }

    /**
     * Scenario 4: A delete job executes successfully, followed by a stale update job.
     * The deleted FAQ must remain deleted and absent from the index.
     */
    public function test_delete_job_followed_by_stale_update_job_leaves_faq_deleted(): void
    {
        $staleUpdateJob = new FAQIndexJob($this->faq, 'update');

        // Delete FAQ and run delete job
        $this->faq->delete();
        $deleteJob = new FAQIndexJob($this->faq, 'delete');

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->never())->method('syncFaq');
        $mockClient->expects($this->atLeastOnce())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(true);

        $deleteJob->handle($mockClient);
        $staleUpdateJob->handle($mockClient);

        $this->faq->refresh();
        $this->assertTrue($this->faq->trashed());
    }

    /**
     * Scenario 5: A current, valid indexing job still indexes an active FAQ successfully.
     */
    public function test_current_valid_indexing_job_indexes_active_faq_successfully(): void
    {
        $this->faq->update([
            'lifecycle_status' => FaqLifecycleStatus::SYNCING,
            'is_active'        => false,
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('syncFaq')
            ->with($this->callback(fn (FAQ $f) => $f->id === $this->faq->id))
            ->willReturn(true);

        $job = new FAQIndexJob($this->faq, 'index');
        $job->handle($mockClient);

        $this->faq->refresh();
        $this->assertEquals(FaqLifecycleStatus::ACTIVE, $this->faq->lifecycle_status);
        $this->assertTrue($this->faq->is_active);
    }

    /**
     * Scenario 6: A failed indexing attempt follows the existing retry/failure policy
     * without incorrectly activating the FAQ.
     */
    public function test_failed_indexing_attempt_follows_failure_policy_without_activating_faq(): void
    {
        $this->faq->update([
            'lifecycle_status' => FaqLifecycleStatus::SYNCING,
            'is_active'        => false,
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('syncFaq')
            ->willReturn(false);

        $job = new FAQIndexJob($this->faq, 'index');
        $job->handle($mockClient);

        $this->faq->refresh();
        $this->assertEquals(FaqLifecycleStatus::SYNC_FAILED, $this->faq->lifecycle_status);
        $this->assertFalse($this->faq->is_active);
        $this->assertNotNull($this->faq->sync_error);
    }

    /**
     * Priority 3: When FAQ creation commits successfully but dispatching the indexing job fails,
     * the record must NOT be activated and should record the failure status.
     */
    public function test_faq_service_handles_queue_dispatch_failure_without_activating_faq(): void
    {
        $mockIndexer = $this->createMock(FAQIndexer::class);
        $mockIndexer->expects($this->once())
            ->method('dispatchIndex')
            ->willThrowException(new \RuntimeException('Queue connection failed (Redis timeout).'));

        $service = new FAQService($mockIndexer);

        // Set authenticated user context
        $user = \App\Models\User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        \Illuminate\Support\Facades\Auth::login($user);

        $faq = $service->create([
            'question'  => 'Will this be active if queue fails?',
            'answer'    => 'It must never be active if queue dispatch fails.',
            'is_active' => true,
        ]);

        $this->assertNotNull($faq);
        $this->assertFalse($faq->is_active, 'FAQ was activated despite queue dispatch failure.');
        $this->assertNotEquals(FaqLifecycleStatus::ACTIVE, $faq->lifecycle_status);
        // It must either be SYNC_FAILED or SYNCING (with error tracked), never ACTIVE
        $this->assertTrue(
            in_array($faq->lifecycle_status, [FaqLifecycleStatus::SYNC_FAILED, FaqLifecycleStatus::SYNCING], true)
        );
    }

    /**
     * Interleaving 4: A queue-dispatch exception from an older operation occurs after a newer
     * operation has already changed and updated the same FAQ.
     */
    public function test_queue_dispatch_exception_does_not_clobber_newer_update_state(): void
    {
        $mockIndexer = $this->createMock(FAQIndexer::class);
        $mockIndexer->expects($this->once())
            ->method('dispatchIndex')
            ->willReturnCallback(function (FAQ $faq) {
                // While dispatch is throwing/failing, Thread 2 completes a newer update to ACTIVE
                FAQ::where('id', $faq->id)->update([
                    'question'         => 'Newer Content V2 from Thread 2',
                    'lifecycle_status' => FaqLifecycleStatus::ACTIVE->value,
                    'is_active'        => true,
                ]);

                throw new \RuntimeException('Queue server packet dropped.');
            });

        $service = new FAQService($mockIndexer);

        $user = \App\Models\User::factory()->create(['workspace_id' => $this->workspace->id]);
        \Illuminate\Support\Facades\Auth::login($user);

        $faq = $service->create([
            'question'  => 'Old Content V1',
            'answer'    => 'Old Answer V1',
            'is_active' => true,
        ]);

        $faq->refresh();
        // The newer state set by Thread 2 must NOT be clobbered to SYNC_FAILED
        $this->assertEquals(FaqLifecycleStatus::ACTIVE, $faq->lifecycle_status, 'Older queue dispatch failure clobbered newer update state.');
        $this->assertTrue($faq->is_active);
        $this->assertEquals('Newer Content V2 from Thread 2', $faq->question);
    }

    /**
     * Interleaving 2: While Job 1 is waiting on remote syncFaq, a newer update arrives
     * and sets lifecycle_status to SYNCING. Job 1 must NOT mark the record ACTIVE.
     */
    public function test_in_flight_upsert_does_not_activate_faq_if_newer_update_is_syncing(): void
    {
        $job1 = new FAQIndexJob($this->faq, 'update');

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->method('syncFaq')->willReturnCallback(function (FAQ $faq) {
            // While Job 1 is in-flight with remote HTTP, Update 2 commits to DB!
            FAQ::where('id', $faq->id)->update([
                'question'         => 'Updated Question by Thread 2',
                'lifecycle_status' => FaqLifecycleStatus::SYNCING->value,
                'is_active'        => false,
            ]);

            return true;
        });

        $job1->handle($mockClient);

        $this->faq->refresh();
        // Core invariant: Job 1 must not mark the FAQ active because Update 2 is still SYNCING
        $this->assertFalse($this->faq->is_active, 'Job 1 prematurely marked FAQ active while Update 2 was in SYNCING.');
        $this->assertEquals(FaqLifecycleStatus::SYNCING, $this->faq->lifecycle_status);
    }

    /**
     * Interleaving 5: When Job 1's upsert completes after a newer update has already
     * modified and activated the FAQ in DB, Job 1 must abort without touching authoritative DB state
     * and must NOT fire an uncoordinated secondary upsert that would race with subsequent versions.
     */
    public function test_stale_in_flight_upsert_does_not_mutate_authoritative_newer_db_state_or_fire_unfenced_retry(): void
    {
        $job1 = new FAQIndexJob($this->faq, 'update');

        $syncFaqCallCount = 0;

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->method('syncFaq')->willReturnCallback(function (FAQ $faq) use (&$syncFaqCallCount) {
            $syncFaqCallCount++;

            // While Job 1 is in-flight syncing V1, a newer operation updates and activates V2 in DB
            FAQ::where('id', $faq->id)->update([
                'question'         => 'Warranty V2 (Newer)',
                'lifecycle_status' => FaqLifecycleStatus::ACTIVE->value,
                'is_active'        => true,
            ]);

            return true;
        });

        $job1->handle($mockClient);

        $this->faq->refresh();
        // Authoritative MySQL state must remain V2 and ACTIVE
        $this->assertEquals('Warranty V2 (Newer)', $this->faq->question);
        $this->assertEquals(FaqLifecycleStatus::ACTIVE, $this->faq->lifecycle_status);
        $this->assertTrue($this->faq->is_active);

        // Job 1 must NOT have fired a secondary un-fenced corrective upsert that would race with V3/deletions
        $this->assertEquals(1, $syncFaqCallCount, 'Stale job fired an uncoordinated secondary upsert.');
    }

    /**
     * Interleaving 6: If FAQ is deactivated while remote upsert is in flight,
     * the stale job must immediately issue deleteFaq to purge Typesense and not resurrect.
     */
    public function test_deactivation_during_in_flight_upsert_purges_typesense_and_does_not_resurrect(): void
    {
        $job = new FAQIndexJob($this->faq, 'update');

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->method('syncFaq')->willReturnCallback(function (FAQ $faq) {
            // While syncFaq is in flight, admin deactivates FAQ
            FAQ::where('id', $faq->id)->update([
                'lifecycle_status' => FaqLifecycleStatus::DRAFT->value,
                'is_active'        => false,
            ]);

            return true;
        });

        // Invariant: deleteFaq MUST be called to purge the stale document that was just upserted
        $mockClient->expects($this->once())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(true);

        $job->handle($mockClient);

        $this->faq->refresh();
        $this->assertFalse($this->faq->is_active);
        $this->assertEquals(FaqLifecycleStatus::DRAFT, $this->faq->lifecycle_status);
    }

    /**
     * Interleaving 3: While Job 1 is waiting on remote syncFaq, Update 2 sets DB to SYNCING.
     * Job 1 fails. Job 1 must NOT set Update 2 to SYNC_FAILED.
     */
    public function test_in_flight_failure_does_not_set_sync_failed_on_newer_syncing_update(): void
    {
        $job1 = new FAQIndexJob($this->faq, 'update');

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->method('syncFaq')->willReturnCallback(function (FAQ $faq) {
            // While Job 1 is in-flight, Update 2 commits to DB with SYNCING
            FAQ::where('id', $faq->id)->update([
                'question'         => 'Updated Question V2 in SYNCING',
                'lifecycle_status' => FaqLifecycleStatus::SYNCING->value,
                'is_active'        => false,
            ]);

            return false; // Job 1 fails
        });

        $job1->handle($mockClient);

        $this->faq->refresh();
        // Update 2 must NOT be degraded to SYNC_FAILED by Job 1's failure
        $this->assertNotEquals(FaqLifecycleStatus::SYNC_FAILED, $this->faq->lifecycle_status, 'Job 1 marked newer SYNCING update as SYNC_FAILED.');
        $this->assertEquals(FaqLifecycleStatus::SYNCING, $this->faq->lifecycle_status);
    }

    /**
     * Requirement 6: CLI reindex acquires the per-FAQ lock and syncs active searchable FAQs.
     */
    public function test_cli_reindex_acquires_per_faq_lock_and_syncs_searchable_faq(): void
    {
        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('syncFaq')
            ->with($this->callback(fn (FAQ $f) => $f->id === $this->faq->id))
            ->willReturn(true);
        $mockClient->expects($this->never())->method('deleteFaq');

        $this->app->instance(RetrievalClient::class, $mockClient);

        $this->artisan('faq:reindex', ['--force' => true])
            ->assertSuccessful();
    }

    /**
     * Requirement 6: CLI reindex acquires the per-FAQ lock and purges inactive or deleted FAQs.
     */
    public function test_cli_reindex_acquires_per_faq_lock_and_deletes_inactive_or_trashed_faq(): void
    {
        $this->faq->update([
            'is_active'        => false,
            'lifecycle_status' => FaqLifecycleStatus::DRAFT,
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->never())->method('syncFaq');
        $mockClient->expects($this->once())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(true);

        $this->app->instance(RetrievalClient::class, $mockClient);

        $this->artisan('faq:reindex', ['--force' => true])
            ->assertSuccessful();
    }

    /**
     * Requirement 4 & 6: CLI reindex fails closed without remote mutation when lock cannot be acquired.
     */
    public function test_cli_reindex_fails_closed_and_skips_mutation_when_lock_acquisition_times_out(): void
    {
        // Simulate another active worker holding the per-FAQ lock
        $heldLock = Cache::lock("faq_indexing:{$this->faq->id}", 60);
        $this->assertTrue($heldLock->acquire(), 'Precondition failed: unable to acquire test lock.');

        $mockClient = $this->createMock(RetrievalClient::class);
        // Fail-closed invariant: If lock cannot be acquired, NEVER perform remote sync or delete
        $mockClient->expects($this->never())->method('syncFaq');
        $mockClient->expects($this->never())->method('deleteFaq');

        $this->app->instance(RetrievalClient::class, $mockClient);

        try {
            $this->artisan('faq:reindex', ['--force' => true, '--lock-timeout' => 0])
                ->assertSuccessful();
        } finally {
            $heldLock->release();
        }
    }

    /**
     * Requirement 3 & 4: Queue job fails closed without remote mutation when lock cannot be acquired.
     */
    public function test_queue_job_fails_closed_when_lock_cannot_be_acquired(): void
    {
        $heldLock = Cache::lock("faq_indexing:{$this->faq->id}", 60);
        $this->assertTrue($heldLock->acquire(), 'Precondition failed: unable to acquire test lock.');

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->never())->method('syncFaq');
        $mockClient->expects($this->never())->method('deleteFaq');

        $job = new FAQIndexJob($this->faq, 'update', null, 0);

        try {
            $this->expectException(LockTimeoutException::class);
            $job->handle($mockClient);
        } finally {
            $heldLock->release();
        }
    }

    /**
     * Requirement 5: CLI reindex detects in-flight deactivation and purges Typesense to prevent resurrection.
     */
    public function test_cli_reindex_detects_in_flight_deactivation_and_purges_typesense(): void
    {
        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->method('syncFaq')->willReturnCallback(function (FAQ $faq) {
            // While syncFaq HTTP request is in-flight, FAQ is deactivated in database
            FAQ::where('id', $faq->id)->update([
                'is_active'        => false,
                'lifecycle_status' => FaqLifecycleStatus::DRAFT->value,
            ]);

            return true;
        });

        // Invariant: Because FAQ was deactivated in-flight, ReindexFaqs must call deleteFaq to purge it
        $mockClient->expects($this->once())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(true);

        $this->app->instance(RetrievalClient::class, $mockClient);

        $this->artisan('faq:reindex', ['--force' => true])
            ->assertSuccessful();
    }

    /**
     * Serialization: Queue job runs cleanly after CLI reindex lock is released.
     */
    public function test_queue_job_serializes_cleanly_after_cli_reindex_lock_released(): void
    {
        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->exactly(2))
            ->method('syncFaq')
            ->willReturn(true);

        $this->app->instance(RetrievalClient::class, $mockClient);

        // 1. Run CLI reindex (acquires and releases lock)
        $this->artisan('faq:reindex', ['--force' => true])
            ->assertSuccessful();

        // 2. Queue job for the same FAQ runs afterward
        $job = new FAQIndexJob($this->faq, 'update');
        $job->handle($mockClient);

        $this->faq->refresh();
        $this->assertEquals(FaqLifecycleStatus::ACTIVE, $this->faq->lifecycle_status);
        $this->assertTrue($this->faq->is_active);
    }

    /**
     * Requirement: If post-sync cleanup deleteFaq fails, ReindexFaqs must track it as an error.
     */
    public function test_cli_reindex_tracks_error_when_post_sync_cleanup_delete_fails(): void
    {
        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->method('syncFaq')->willReturnCallback(function (FAQ $faq) {
            FAQ::where('id', $faq->id)->update([
                'is_active'        => false,
                'lifecycle_status' => FaqLifecycleStatus::DRAFT->value,
            ]);

            return true;
        });

        // Cleanup delete fails (e.g. Typesense network drop)
        $mockClient->expects($this->once())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(false);

        $this->app->instance(RetrievalClient::class, $mockClient);

        // Command should warn and report error in summary
        $this->artisan('faq:reindex', ['--force' => true])
            ->expectsOutputToContain('Post-sync cleanup delete failed for FAQ')
            ->expectsOutputToContain('Sync completed with 1 errors')
            ->assertSuccessful();
    }
}
