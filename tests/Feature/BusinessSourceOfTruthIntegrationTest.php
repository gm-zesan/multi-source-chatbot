<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\KnowledgeSupportAgent;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\CRMContact;
use App\Models\CRMContactPhone;
use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Business\BusinessSourceOfTruthService;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BusinessSourceOfTruthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Conversation $conversation;
    private BusinessSourceOfTruthService $businessService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessService = new BusinessSourceOfTruthService();
        BusinessSourceOfTruthService::resetMockOrders();

        $this->workspace = Workspace::create([
            'name' => 'Source of Truth Workspace',
            'slug' => 'sot-workspace',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget',
            'external_id'  => 'web_widget_sot',
            'access_token' => 'token_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_sot_999',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $contact = CRMContact::create([
            'workspace_id'    => $this->workspace->id,
            'conversation_id' => $this->conversation->id,
            'name'            => 'Tanvir Rahman',
        ]);

        CRMContactPhone::create([
            'crm_contact_id' => $contact->id,
            'phone'          => '+8801712345678',
            'is_primary'     => true,
        ]);
    }

    public function test_lookup_order_retrieves_live_state(): void
    {
        $order = $this->businessService->lookupOrder('1042');

        $this->assertNotNull($order);
        $this->assertSame('Dispatched', $order['status']);
        $this->assertSame('Pathao Express', $order['carrier']);
        $this->assertSame('PATH-1042-BD', $order['tracking_code']);
    }

    public function test_business_context_builder_extracts_order_from_query(): void
    {
        $context = $this->businessService->buildBusinessContext(
            query: 'আমার অর্ডার #1042 এর বর্তমান অবস্থা কী?',
            conversation: $this->conversation,
            workspaceId: $this->workspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringContainsString('Live Order #1042', $context);
        $this->assertStringContainsString('Status: Dispatched', $context);
        $this->assertStringContainsString('Pathao Express', $context);
        $this->assertStringContainsString('Tanvir Rahman', $context);
        $this->assertStringContainsString('+8801712345678', $context);
    }

    public function test_3_layer_context_hierarchy_in_agent_instructions(): void
    {
        $faq = new FAQ([
            'question' => 'How can I track my parcel?',
            'answer'   => 'Use the tracking code sent to your SMS on the courier website.',
        ]);
        $hit = new FAQSearchResult($faq, 0.95, 0.98, 0.92, 'hybrid');
        $hits = new \Illuminate\Database\Eloquent\Collection([$hit]);

        $memoryContext = "Customer Historical Preferences:\n- Preferred Size: XL\n- Preferred Payment: bKash";
        $businessContext = "- Live Order #1042:\n  * Status: Dispatched\n  * Carrier: Pathao Express";

        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievedKnowledge: $hits,
            memoryContext: $memoryContext,
            businessContext: $businessContext,
        );

        $instructions = (string) $agent->instructions();

        // Must contain all 3 distinct layers
        $this->assertStringContainsString('[Layer 3: Live Business Data / Authoritative Source of Truth]', $instructions);
        $this->assertStringContainsString('[Layer 1: Official Knowledge Base Documents]', $instructions);
        $this->assertStringContainsString('[Layer 2: Customer Conversation Graph Memory (Historical Preferences)]', $instructions);

        // Must explicitly enforce precedence: Live Business Data overrides past conversational memory
        $this->assertStringContainsString('Live Business Data (Layer 3): Absolute source of truth for live order status', $instructions);
        $this->assertStringContainsString('Overrides past conversational memory', $instructions);
    }

    public function test_live_business_truth_integrated_in_customer_support_service(): void
    {
        Http::fake([
            '*/memory/search' => Http::response([
                'success'                  => true,
                'customer_id'              => 'user_sot_999',
                'has_memories'             => true,
                'memories_count'           => 1,
                'memories'                 => [
                    [
                        'type'       => 'historical_action',
                        'subject'    => 'Customer',
                        'relation'   => 'DISCUSSED',
                        'object'     => '1042',
                        'status'     => 'past',
                        'confidence' => 1.0,
                    ]
                ],
                'formatted_memory_context' => "Customer Historical Preferences:\n- Previously Discussed Order: #1042",
                'latency_ms'               => 1.4,
            ], 200),
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Apnar order #1042 Pathao Express-e dispatched hoyeche! Tracking ID: PATH-1042-BD.',
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = app(CustomerSupportService::class);
        $result = $service->handleQuery(
            query: 'Where is my order #1042?',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
        );

        $this->assertNotNull($result['memory_context']);
        $this->assertNotNull($result['business_context']);
        $this->assertStringContainsString('Live Order #1042', $result['business_context']);
        $this->assertStringContainsString('Status: Dispatched', $result['business_context']);
    }
}
