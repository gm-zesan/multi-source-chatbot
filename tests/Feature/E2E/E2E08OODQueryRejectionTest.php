<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\AI\Orchestration\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E-08: Out-Of-Domain (OOD) Query Rejection
 *
 * Pathway: Completely Unrelated Query -> UnifiedOrchestrator -> UnifiedDecisionEngine -> Deterministic Rejection -> Zero Unnecessary Retrieval or Analytics
 */
class E2E08OODQueryRejectionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E OOD Store', 'slug' => 'e2e-ood-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront OOD Widget',
            'external_id'  => 'e2e_ood_01',
            'access_token' => 'token_ood_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_ood_user',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_e2e_08_ood_query_returns_safe_deterministic_rejection(): void
    {
        $query = 'How do I build a nuclear fusion reactor in my backyard?';

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: $query,
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়', $reply);
        $this->assertStringNotContainsString('nuclear', $reply);
    }
}
