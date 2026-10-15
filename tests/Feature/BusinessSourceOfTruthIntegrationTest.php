<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\KnowledgeSupportAgent;
use App\Models\AnalyticsProduct;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\CRMContact;
use App\Models\CRMContactEmail;
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
        $order = $this->businessService->lookupOrder('1042', $this->workspace->id, $this->conversation);

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

    public function test_unknown_numeric_order_id_does_not_generate_fabricated_details(): void
    {
        $order = $this->businessService->lookupOrder('9999', $this->workspace->id, $this->conversation);
        $this->assertNull($order, 'Unknown numeric order ID must not return any synthetic order data.');

        $context = $this->businessService->buildBusinessContext(
            query: 'Where is my order #9999?',
            conversation: $this->conversation,
            workspaceId: $this->workspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringNotContainsString('Paperfly', $context);
        $this->assertStringNotContainsString('1200 BDT', $context);
        $this->assertStringNotContainsString('Tracking ID: PL-', $context);
        $this->assertStringContainsString('Order #9999 Inquiry Status:', $context);
        $this->assertStringContainsString('Verification Status: Order not found.', $context);
        $this->assertStringContainsString('Do NOT guess, invent, or assume any status', $context);
    }

    public function test_mock_fixtures_unavailable_outside_testing_environment(): void
    {
        app()['env'] = 'production';

        try {
            $order = $this->businessService->lookupOrder('1042', $this->workspace->id, $this->conversation);
            $this->assertNull($order, 'Mock fixtures must never be disclosed in non-testing environments.');

            $context = $this->businessService->buildBusinessContext(
                query: 'Status of order #1042',
                conversation: $this->conversation,
                workspaceId: $this->workspace->id,
            );

            $this->assertNotNull($context);
            $this->assertStringNotContainsString('Live Order #1042:', $context);
            $this->assertStringNotContainsString('Pathao Express', $context);
            $this->assertStringContainsString('Order status could not be verified', $context);
        } finally {
            app()['env'] = 'testing';
        }
    }

    public function test_customer_cannot_receive_another_customers_order_details(): void
    {
        // Create another customer/conversation with distinct identity
        $channelAccount = ChannelAccount::where('workspace_id', $this->workspace->id)->firstOrFail();
        $unauthorizedConversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'unauthorized_customer_888',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $unauthorizedContact = CRMContact::create([
            'workspace_id'    => $this->workspace->id,
            'conversation_id' => $unauthorizedConversation->id,
            'name'            => 'Other Customer',
        ]);

        CRMContactPhone::create([
            'crm_contact_id' => $unauthorizedContact->id,
            'phone'          => '+8801999999999',
            'is_primary'     => true,
        ]);

        // Attempt to access Order #1042 belonging to Tanvir Rahman / user_sot_999
        $order = $this->businessService->lookupOrder('1042', $this->workspace->id, $unauthorizedConversation);
        $this->assertNull($order, 'Order lookup must fail closed when requesting customer does not match order owner.');

        $context = $this->businessService->buildBusinessContext(
            query: 'Can you check status for order #1042?',
            conversation: $unauthorizedConversation,
            workspaceId: $this->workspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringNotContainsString('Live Order #1042:', $context);
        $this->assertStringNotContainsString('Pathao Express', $context);
        $this->assertStringNotContainsString('PATH-1042-BD', $context);
        $this->assertStringContainsString('Order status could not be verified for this customer account.', $context);
        $this->assertStringContainsString('Do NOT guess, invent, or assume any status', $context);
    }

    public function test_cross_workspace_order_lookup_cannot_disclose_order_information(): void
    {
        $otherWorkspace = Workspace::create([
            'name' => 'Foreign Workspace',
            'slug' => 'foreign-ws',
        ]);

        $foreignChannelAccount = ChannelAccount::create([
            'workspace_id' => $otherWorkspace->id,
            'channel_id'   => Channel::where('slug', 'web')->firstOrFail()->id,
            'name'         => 'Foreign Web',
            'external_id'  => 'web_widget_foreign',
            'access_token' => 'token_foreign',
            'is_active'    => true,
        ]);

        $foreignConversation = Conversation::create([
            'channel_account_id' => $foreignChannelAccount->id,
            'external_user_id'   => 'user_sot_999',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Order 1042 belongs to workspace 1; foreign workspace lookup must fail closed
        $order = $this->businessService->lookupOrder('1042', $otherWorkspace->id, $foreignConversation);
        $this->assertNull($order, 'Cross-workspace order lookup must fail closed.');

        $context = $this->businessService->buildBusinessContext(
            query: 'Status for #1042',
            conversation: $foreignConversation,
            workspaceId: $otherWorkspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringNotContainsString('Live Order #1042:', $context);
        $this->assertStringNotContainsString('Pathao Express', $context);
    }

    public function test_missing_ownership_evidence_fails_closed(): void
    {
        // Subcase A: Order in store has no ownership metadata (no external_user_id, phone, or name)
        BusinessSourceOfTruthService::setMockOrder('7777', [
            'order_id'     => '7777',
            'workspace_id' => $this->workspace->id,
            'status'       => 'Processing',
            'carrier'      => 'RedX',
        ]);

        $orderWithoutOwner = $this->businessService->lookupOrder('7777', $this->workspace->id, $this->conversation);
        $this->assertNull($orderWithoutOwner, 'Order lacking ownership evidence must fail closed.');

        // Subcase B: Conversation has unverified customer identity metadata (guest session without owner link or CRM contact)
        $channelAccount = ChannelAccount::where('workspace_id', $this->workspace->id)->firstOrFail();
        $anonymousConversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'guest_unauthenticated_session',
            'customer_name'      => null,
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $orderForAnon = $this->businessService->lookupOrder('1042', $this->workspace->id, $anonymousConversation);
        $this->assertNull($orderForAnon, 'Anonymous conversation lacking customer identity must fail closed.');

        // Subcase C: Direct invocation with null conversation fails closed
        $orderWithoutConversation = $this->businessService->lookupOrder('1042', $this->workspace->id, null);
        $this->assertNull($orderWithoutConversation, 'Order lookup without verified conversation context must fail closed.');
    }

    public function test_active_catalog_status_does_not_imply_physical_stock_availability(): void
    {
        AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Premium Silk Panjabi',
            'sku'          => 'PSP-101',
            'category'     => 'Men Ethnic',
            'unit_price'   => 4500.00,
            'is_active'    => true,
        ]);

        $context = $this->businessService->buildBusinessContext(
            query: 'Do you have Premium Silk Panjabi in stock?',
            conversation: $this->conversation,
            workspaceId: $this->workspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringContainsString('Product: Premium Silk Panjabi', $context);
        $this->assertStringContainsString('Catalog Status: Active in catalog', $context);
        $this->assertStringContainsString('Price: ৳4,500.00', $context);

        // Truthfulness guard: Must never claim physical inventory or "Available in Stock"
        $this->assertStringNotContainsString('Available in Stock', $context);
        $this->assertStringNotContainsString('Stock Status: Available in Stock', $context);
    }

    public function test_same_name_different_customer_cannot_access_order(): void
    {
        // Customer A owns Order #1042 (Tanvir Rahman, user_sot_999, +8801712345678)
        $channelAccount = ChannelAccount::where('workspace_id', $this->workspace->id)->firstOrFail();

        // Customer B has the EXACT same name "Tanvir Rahman", but different external ID, phone, and email
        $sameNameConversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'different_customer_tanvir_999',
            'customer_name'      => 'Tanvir Rahman',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $sameNameContact = CRMContact::create([
            'workspace_id'    => $this->workspace->id,
            'conversation_id' => $sameNameConversation->id,
            'name'            => 'Tanvir Rahman',
        ]);

        CRMContactPhone::create([
            'crm_contact_id' => $sameNameContact->id,
            'phone'          => '+8801899999999', // Different from +8801712345678
            'is_primary'     => true,
        ]);

        CRMContactEmail::create([
            'crm_contact_id' => $sameNameContact->id,
            'email'          => 'tanvir.different@example.com',
            'is_primary'     => true,
        ]);

        // Attempting to access order #1042 must fail closed
        $order = $this->businessService->lookupOrder('1042', $this->workspace->id, $sameNameConversation);
        $this->assertNull($order, 'Identical customer name alone must never authorize order access when unique identifiers do not match.');

        $context = $this->businessService->buildBusinessContext(
            query: 'Please give me updates on order #1042',
            conversation: $sameNameConversation,
            workspaceId: $this->workspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringNotContainsString('Live Order #1042:', $context);
        $this->assertStringNotContainsString('Pathao Express', $context);
        $this->assertStringNotContainsString('PATH-1042-BD', $context);
        $this->assertStringContainsString('Order status could not be verified for this customer account.', $context);
        $this->assertStringContainsString('Do NOT guess, invent, or assume any status', $context);
    }
}
