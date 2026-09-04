<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FaqLifecycleStatus;
use App\Enums\Permissions\FAQPermission;
use App\Enums\RoleEnum;
use App\Jobs\FAQIndexJob;
use App\Models\FAQ;
use App\Models\FaqLexicon;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FAQ\FaqLexiconGeneratorService;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FAQLifecyclePipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->workspace = Workspace::create([
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);

        $role = Role::create([
            'name'         => RoleEnum::SUPERADMIN->value,
            'guard_name'   => 'web',
            'workspace_id' => $this->workspace->id,
        ]);
        $role->givePermissionTo(Permission::all());

        $this->adminUser = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->adminUser->assignRole($role);
    }

    public function test_admin_created_faq_starts_in_validating_and_is_not_searchable(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->adminUser)
            ->post(route('faqs.store'), [
                'question'      => 'What is the return policy for damaged items?',
                'answer'        => 'Damaged items can be returned within 7 days with invoice.',
                'document_type' => 'return_policy',
                'priority'      => 80,
                'is_active'     => 1,
            ]);

        $response->assertRedirect(route('faqs.index'));

        $faq = FAQ::where('question', 'What is the return policy for damaged items?')->first();
        $this->assertNotNull($faq);

        // Core Invariant Check: Not active or searchable upon DB creation
        $this->assertEquals(FaqLifecycleStatus::VALIDATING, $faq->lifecycle_status);
        $this->assertFalse($faq->is_active);
        $this->assertFalse($faq->shouldBeSearchable());
        $this->assertFalse($faq->isReadyForRetrieval());

        Queue::assertPushed(FAQIndexJob::class, function (FAQIndexJob $job) use ($faq) {
            return $job->faq->id === $faq->id && $job->action === 'index';
        });
    }

    public function test_successful_validation_and_sync_activates_faq(): void
    {
        $faq = FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'question'         => 'What is your refund timeframe?',
            'answer'           => 'Refunds are processed within 5 to 7 working days to your original payment method.',
            'document_type'    => 'refund_policy',
            'lifecycle_status' => FaqLifecycleStatus::VALIDATING,
            'is_active'        => false,
        ]);

        $this->assertFalse($faq->shouldBeSearchable());

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('syncFaq')
            ->with($this->callback(fn (FAQ $f) => $f->id === $faq->id))
            ->willReturn(true);

        $job = new FAQIndexJob($faq, 'index');
        $job->handle($mockClient);

        $faq->refresh();

        // Must now be ACTIVE and searchable
        $this->assertEquals(FaqLifecycleStatus::ACTIVE, $faq->lifecycle_status);
        $this->assertTrue($faq->is_active);
        $this->assertTrue($faq->shouldBeSearchable());
        $this->assertTrue($faq->isReadyForRetrieval());
        $this->assertNull($faq->sync_error);
    }

    public function test_lexicon_validation_failure_transitions_to_validation_failed_and_withholds_document(): void
    {
        $faq = FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'question'         => 'Broken policy question',
            'answer'           => 'Broken policy answer',
            'document_type'    => 'custom_policy',
            'lifecycle_status' => FaqLifecycleStatus::VALIDATING,
            'is_active'        => false,
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);
        // Should delete from Typesense if existed, and NEVER sync
        $mockClient->expects($this->once())
            ->method('deleteFaq')
            ->with($faq->id, $this->workspace->id);
        $mockClient->expects($this->never())->method('syncFaq');

        // Mock generator to return unvalidated / null lexicon
        $mockGenerator = $this->createMock(FaqLexiconGeneratorService::class);
        $mockGenerator->expects($this->once())
            ->method('generateAndStore')
            ->willReturn(null);

        $job = new FAQIndexJob($faq, 'index');
        $job->handle($mockClient, $mockGenerator);

        $faq->refresh();

        $this->assertEquals(FaqLifecycleStatus::VALIDATION_FAILED, $faq->lifecycle_status);
        $this->assertFalse($faq->is_active);
        $this->assertFalse($faq->shouldBeSearchable());
        $this->assertTrue($faq->hasFailed());
        $this->assertNotNull($faq->sync_error);
    }

    public function test_typesense_sync_failure_transitions_to_sync_failed(): void
    {
        $faq = FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'question'         => 'Exchange period policy',
            'answer'           => 'You can exchange within 7 days.',
            'document_type'    => 'exchange_policy',
            'lifecycle_status' => FaqLifecycleStatus::VALIDATING,
            'is_active'        => false,
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('syncFaq')
            ->willReturn(false); // Typesense failure

        $job = new FAQIndexJob($faq, 'index');
        $job->handle($mockClient);

        $faq->refresh();

        $this->assertEquals(FaqLifecycleStatus::SYNC_FAILED, $faq->lifecycle_status);
        $this->assertFalse($faq->is_active);
        $this->assertFalse($faq->shouldBeSearchable());
        $this->assertTrue($faq->hasFailed());
        $this->assertNotNull($faq->sync_error);
    }

    public function test_failed_faq_can_be_retried_via_resync_endpoint(): void
    {
        Queue::fake();

        $faq = FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'question'         => 'Payment methods available',
            'answer'           => 'We accept bKash, Nagad, Visa and Mastercard.',
            'document_type'    => 'payment_policy',
            'lifecycle_status' => FaqLifecycleStatus::SYNC_FAILED,
            'sync_error'       => 'Connection timeout',
            'is_active'        => false,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('faqs.resync', $faq->id));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $faq->refresh();

        // Resets to validating and clears previous error
        $this->assertEquals(FaqLifecycleStatus::VALIDATING, $faq->lifecycle_status);
        $this->assertNull($faq->sync_error);

        Queue::assertPushed(FAQIndexJob::class, function (FAQIndexJob $job) use ($faq) {
            return $job->faq->id === $faq->id && $job->action === 'index';
        });
    }

    public function test_draft_faq_stays_draft_and_is_not_indexed(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->adminUser)
            ->post(route('faqs.store'), [
                'question'      => 'Unpublished secret policy',
                'answer'        => 'Internal draft only.',
                'document_type' => 'terms',
                'priority'      => 10,
                'is_active'     => 0, // Admin unchecks active
            ]);

        $response->assertRedirect(route('faqs.index'));

        $faq = FAQ::where('question', 'Unpublished secret policy')->first();
        $this->assertNotNull($faq);

        $this->assertEquals(FaqLifecycleStatus::DRAFT, $faq->lifecycle_status);
        $this->assertFalse($faq->is_active);
        $this->assertFalse($faq->shouldBeSearchable());

        // Should NOT push index job to publish
        Queue::assertNotPushed(FAQIndexJob::class);
    }
}
