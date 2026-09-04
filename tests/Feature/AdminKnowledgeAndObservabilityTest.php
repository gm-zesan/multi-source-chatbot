<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\CustomerSupportAgent;
use App\Enums\Permissions\FAQPermission;
use App\Enums\RoleEnum;
use App\Jobs\FAQIndexJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminKnowledgeAndObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Workspace $workspace;
    protected ChannelAccount $channelAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

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

        $channel = Channel::create(['slug' => 'messenger', 'name' => 'Facebook Messenger', 'is_active' => true]);
        $this->channelAccount = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace->id,
            'name'         => 'Test Account',
            'external_id'  => 'page_123',
            'access_token' => 'dummy_token',
        ]);
    }

    public function test_admin_can_create_faq_with_document_type_and_triggers_sync(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->adminUser)->post(route('faqs.store'), [
            'question'      => 'How do I exchange a defective product?',
            'answer'        => 'You can exchange defective items within 7 days with invoice.',
            'document_type' => 'exchange_policy',
            'priority'      => 5,
            'is_active'     => 1,
        ]);

        $response->assertRedirect(route('faqs.index'));

        $faq = FAQ::where('question', 'How do I exchange a defective product?')->first();
        $this->assertNotNull($faq);
        $this->assertEquals('exchange_policy', $faq->document_type);
        $this->assertTrue($faq->isPolicy());
        $this->assertEquals('Exchange Policy', $faq->documentTypeLabel());

        Queue::assertPushed(FAQIndexJob::class);
    }

    public function test_admin_can_trigger_manual_faq_resync(): void
    {
        Queue::fake();

        $faq = FAQ::create([
            'workspace_id'  => $this->workspace->id,
            'question'      => 'What is the refund timeline?',
            'answer'        => 'Refunds are credited in 7-10 business days.',
            'document_type' => 'refund_policy',
            'is_active'     => true,
        ]);

        $response = $this->actingAs($this->adminUser)->postJson(route('faqs.resync', $faq->id));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        Queue::assertPushed(FAQIndexJob::class);
    }

    public function test_chat_simulator_returns_answerability_gate_diagnostics(): void
    {
        CustomerSupportAgent::fake([
            'Sure, we can help you with returns.'
        ]);

        $response = $this->actingAs($this->adminUser)->postJson(route('simulator.send'), [
            'message' => 'আমি কি পণ্য ফেরত দিতে পারব?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'query',
            'reply',
            'route',
            'answerability_decision' => [
                'status',
                'confidence_score',
                'grounded_count',
                'reasons',
            ],
            'pipeline_diagnostics' => [
                'total_time_ms',
                'answerability_decision',
                'routing_telemetry',
            ],
        ]);
    }

    public function test_admin_can_access_observability_dashboard(): void
    {
        // Seed conversation & outbound messages with telemetry
        $conversation = Conversation::create([
            'workspace_id'       => $this->workspace->id,
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'ext_usr_999',
            'customer_name'      => 'Rahim Uddin',
            'customer_handle'    => 'rahim_uddin',
            'status'             => 'open',
            'last_direction'     => 'outbound',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'You can return unused products within 7 days.',
            'metadata'        => [
                'source'                 => 'customer_support_agent',
                'route'                  => 'knowledge',
                'confidence'             => 0.88,
                'total_time_ms'          => 320.5,
                'answerability_decision' => [
                    'status'           => 'confident',
                    'confidence_score' => 0.88,
                    'reasons'          => ['rule' => 'evidence_sufficient'],
                ],
            ],
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('observability.index'));

        $response->assertOk();
        $response->assertSee('Observability & Live Telemetry', false);
        $response->assertSee('Intent Routing Distribution', false);
        $response->assertSee('Semantic Answerability Gate Distribution', false);
        $response->assertSee('Rahim Uddin', false);
        $response->assertSee('Confident', false);
    }
}
