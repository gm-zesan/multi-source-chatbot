<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\AI\Tools\BusinessAnalyticsTool;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P0 Security Barrier Acceptance Test Suite.
 *
 * Verifies AC-14, AC-20, AC-21, AC-22, AC-38, AC-39:
 * 1. Dual-layer boundary denies customer-channel requests for RouteType::ANALYTICS.
 * 2. Pre-routing workforce barrier blocks inquiries into employee sales, salary, commissions, or targets.
 * 3. Graceful Bengali refusal issued with zero data disclosed:
 *    "আমি শুধুমাত্র আমাদের পণ্য ও কাস্টমার সার্ভিস সংক্রান্ত তথ্য দিতে পারি। অভ্যন্তরীণ ব্যবসায়িক হিসাব প্রকাশের অনুমতি নেই।"
 * 4. Layer 2 execution boundary enforces fail-closed authorization.
 * 5. Intra-tenant RBAC prevents cross-tenant access to workforce and analytics records.
 * 6. Authenticated owner/manager access in dashboard/simulator remains authorized.
 */
class CustomerChannelAnalyticsSecurityBarrierTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Workspace $otherWorkspace;
    private Channel $whatsappChannel;
    private Channel $messengerChannel;
    private Channel $widgetChannel;
    private Channel $simulatorChannel;
    private ChannelAccount $whatsappAccount;
    private ChannelAccount $messengerAccount;
    private ChannelAccount $widgetAccount;
    private ChannelAccount $simulatorAccount;
    private CustomerSupportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for Spatie
        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'view_analytics', 'guard_name' => 'web'],
            ['display_name' => 'View Analytics', 'module' => 'analytics']
        );

        $this->workspace = Workspace::create([
            'name' => 'Acme Retail',
            'slug' => 'acme-retail',
            'is_active' => true,
        ]);

        $this->otherWorkspace = Workspace::create([
            'name' => 'Rival Merchant',
            'slug' => 'rival-merchant',
            'is_active' => true,
        ]);

        $this->whatsappChannel = Channel::create(['name' => 'WhatsApp', 'slug' => 'whatsapp', 'driver' => 'whatsapp']);
        $this->messengerChannel = Channel::create(['name' => 'Facebook Messenger', 'slug' => 'messenger', 'driver' => 'facebook']);
        $this->widgetChannel = Channel::create(['name' => 'Web Widget', 'slug' => 'web_widget', 'driver' => 'web']);
        $this->simulatorChannel = Channel::create(['name' => 'Admin Simulator', 'slug' => 'simulator', 'driver' => 'web']);

        $this->whatsappAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->whatsappChannel->id,
            'name' => 'Acme WhatsApp Account',
            'external_id' => 'wa_acme_1',
            'access_token' => 'wa_tok_1',
            'is_active' => true,
        ]);

        $this->messengerAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->messengerChannel->id,
            'name' => 'Acme Messenger Account',
            'external_id' => 'msg_acme_1',
            'access_token' => 'msg_tok_1',
            'is_active' => true,
        ]);

        $this->widgetAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->widgetChannel->id,
            'name' => 'Acme Widget Account',
            'external_id' => 'wid_acme_1',
            'access_token' => 'wid_tok_1',
            'is_active' => true,
        ]);

        $this->simulatorAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->simulatorChannel->id,
            'name' => 'Acme Simulator Account',
            'external_id' => 'sim_acme_1',
            'access_token' => 'sim_tok_1',
            'is_active' => true,
        ]);

        $this->service = app(CustomerSupportService::class);
    }

    /**
     * AC-21, AC-14 (Layer 1):
     * Customer query classified as ANALYTICS via WhatsApp is intercepted before execution.
     * Must return the standard refusal message and execute zero analytics queries.
     */
    public function test_customer_channel_analytics_query_is_intercepted_and_denied_with_standard_bangla_refusal(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id' => 'wa_cust_001',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        $mockRouter = Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->twice() // once for handleQuery, once for generateReply
            ->andReturn(new RoutingResult(
                route: RouteType::ANALYTICS,
                confidence: 0.95,
                intent: 'analytics_summary_requested'
            ));

        $mockAnalyticsTool = Mockery::mock(BusinessAnalyticsTool::class);
        $mockAnalyticsTool->shouldNotReceive('execute'); // Zero DB aggregation authorized

        $service = new CustomerSupportService(
            faqSearch: app(\App\Services\FAQ\FAQSearch::class),
            conversationService: app(\App\Services\Chat\ConversationService::class),
            router: $mockRouter,
            businessAnalyticsTool: $mockAnalyticsTool,
        );

        $query = 'analytics summary data';
        $result = $service->handleQuery($query, $this->workspace->id, $conversation);

        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $result['reply']);
        $this->assertSame('analytics_denied', $result['route']);
        $this->assertTrue($result['answered']);

        // Verify generateReply() also strictly denies
        $reply = $service->generateReply($conversation, $query, $this->workspace->id);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $reply);
    }

    /**
     * AC-38:
     * Customer query inquiring into employee sales, performance, or salesman metrics
     * is safely blocked pre-routing, REGARDLESS of router intent prediction.
     */
    public function test_customer_channel_employee_workforce_sales_inquiry_denied_regardless_of_router_classification(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->messengerAccount->id,
            'external_user_id' => 'fb_cust_002',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        $mockRouter = Mockery::mock(HybridRouter::class);
        $mockRouter->shouldNotReceive('route'); // Pre-routing block must fire before router

        $service = new CustomerSupportService(
            faqSearch: app(\App\Services\FAQ\FAQSearch::class),
            conversationService: app(\App\Services\Chat\ConversationService::class),
            router: $mockRouter,
        );

        $queries = [
            'কোন employee সবচেয়ে বেশি sales করেছে?',
            'Rahim এই মাসে কত টাকার sales করেছে?',
            'কোন salesman-এর কাছে কতগুলো order আছে?',
            'Who is your top performing salesman?',
            'What is the employee commission report for this month?',
            'কর্মচারীদের বিক্রির হিসাব দেখান',
        ];

        foreach ($queries as $query) {
            $result = $service->handleQuery($query, $this->workspace->id, $conversation);
            $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $result['reply'], "Failed to block: {$query}");
            $this->assertSame('analytics_denied', $result['route']);

            $directReply = $service->generateReply($conversation, $query, $this->workspace->id);
            $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $directReply);
        }
    }

    /**
     * AC-14 (Layer 2 Execution Boundary):
     * If executeAnalyticsRoute() is invoked directly or by an unauthorized requester,
     * it fails closed immediately and returns the refusal message.
     */
    public function test_layer_2_execution_boundary_fail_closed_for_unauthorized_user(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->widgetAccount->id,
            'external_user_id' => 'web_cust_003',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        $routingResult = new RoutingResult(route: RouteType::ANALYTICS, confidence: 1.0);

        // Reflection to invoke private executeAnalyticsRoute
        $reflector = new \ReflectionClass(CustomerSupportService::class);
        $method = $reflector->getMethod('executeAnalyticsRoute');
        $method->setAccessible(true);

        // 1. Invocation with customer channel conversation -> BLOCKED
        $response = $method->invoke($this->service, $conversation, 'total revenue', $this->workspace->id, $routingResult);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $response);

        // 2. Invocation with unauthenticated user -> BLOCKED
        $simConversation = Conversation::create([
            'channel_account_id' => $this->simulatorAccount->id,
            'external_user_id' => 'sim_user_anon',
            'status' => 'open',
            'last_direction' => 'inbound',
            'metadata' => ['is_simulator' => false], // not authorized simulator
        ]);
        $anonResponse = $method->invoke($this->service, $simConversation, 'total sales', $this->workspace->id, $routingResult);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $anonResponse);
    }

    /**
     * AC-39: Intra-Tenant Role-Based Access Control (RBAC):
     * A user from Workspace 2 cannot execute analytics for Workspace 1.
     */
    public function test_cross_tenant_analytics_isolation(): void
    {
        $otherUser = User::create([
            'workspace_id' => $this->otherWorkspace->id,
            'name' => 'Rival Admin',
            'email' => 'admin@rival.test',
            'password' => bcrypt('secret123'),
        ]);
        $otherUser->assignRole(RoleEnum::ADMIN->value);

        $this->actingAs($otherUser);

        // Attempt to execute analytics in Workspace 1 with user belonging to Workspace 2
        $this->assertFalse(
            $this->service->isAuthenticatedOwnerOrManager($this->workspace->id),
            'Cross-tenant user must not be recognized as authorized owner/manager.'
        );

        $reflector = new \ReflectionClass(CustomerSupportService::class);
        $method = $reflector->getMethod('executeAnalyticsRoute');
        $method->setAccessible(true);

        $routingResult = new RoutingResult(route: RouteType::ANALYTICS, confidence: 1.0);
        $dummyConv = new Conversation();

        $response = $method->invoke($this->service, $dummyConv, 'total revenue', $this->workspace->id, $routingResult);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $response);
    }

    /**
     * AC-22:
     * Authenticated Owner or Manager in authorized channels (Dashboard / Simulator)
     * can successfully execute analytics queries.
     */
    public function test_authorized_owner_in_admin_simulator_can_execute_analytics(): void
    {
        $ownerUser = User::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Acme Owner',
            'email' => 'owner@acme.test',
            'password' => bcrypt('secret123'),
        ]);
        $ownerUser->assignRole(RoleEnum::ADMIN->value);

        $this->actingAs($ownerUser);

        $this->assertTrue(
            $this->service->isAuthenticatedOwnerOrManager($this->workspace->id),
            'Authenticated admin must be recognized as authorized owner/manager.'
        );

        $simConversation = Conversation::create([
            'channel_account_id' => $this->simulatorAccount->id,
            'external_user_id' => 'sim_owner_001',
            'status' => 'open',
            'last_direction' => 'inbound',
            'metadata' => ['is_simulator' => true, 'role' => 'owner'],
        ]);

        $this->assertFalse(
            $this->service->isCustomerChannel($simConversation),
            'Simulator conversation with owner role must not be classified as customer channel.'
        );

        $mockAnalyticsTool = Mockery::mock(BusinessAnalyticsTool::class);
        $mockAnalyticsTool->shouldReceive('execute')
            ->once()
            ->andReturn([
                'success' => true,
                'report' => 'অক্টোবর ২০২৬ মোট বিক্রয়: ৳৪,৮৫,০০০। শীর্ষ বিক্রয়কারী: রহিম (৳১,২০,০০০)।',
            ]);

        $mockRouter = Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(route: RouteType::ANALYTICS, confidence: 1.0));

        $service = new CustomerSupportService(
            faqSearch: app(\App\Services\FAQ\FAQSearch::class),
            conversationService: app(\App\Services\Chat\ConversationService::class),
            router: $mockRouter,
            businessAnalyticsTool: $mockAnalyticsTool,
        );

        $result = $service->handleQuery('অক্টোবর মাসের বিক্রয় রিপোর্ট দেখান', $this->workspace->id, $simConversation);

        $this->assertNotSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $result['reply']);
        $this->assertStringContainsString('মোট বিক্রয়', $result['reply']);
    }

    /**
     * AC-20:
     * Verifies channel classification correctly identifies public vs administrative channels.
     */
    public function test_customer_channel_detection_across_all_supported_public_channels(): void
    {
        $publicChannels = ['facebook', 'messenger', 'whatsapp', 'instagram', 'telegram', 'web', 'website', 'web_widget', 'widget', 'public'];

        foreach ($publicChannels as $slug) {
            $ch = Channel::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'driver' => 'web']);
            $acc = ChannelAccount::firstOrCreate(
                ['workspace_id' => $this->workspace->id, 'channel_id' => $ch->id],
                [
                    'name' => "Acc {$slug}",
                    'external_id' => "ext_{$slug}",
                    'access_token' => "tok_{$slug}",
                    'is_active' => true,
                ]
            );
            $conv = Conversation::create([
                'channel_account_id' => $acc->id,
                'external_user_id' => "user_{$slug}",
                'status' => 'open',
                'last_direction' => 'inbound',
            ]);

            $this->assertTrue(
                $this->service->isCustomerChannel($conv),
                "Channel slug '{$slug}' must be classified as a customer channel."
            );
        }

        // Administrative channels: when accessed by authenticated owner/manager
        $owner = User::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Owner Admin Channels Test',
            'email' => 'owner.channels@acme.test',
            'password' => bcrypt('secret123'),
        ]);
        $owner->assignRole(RoleEnum::ADMIN->value);
        $this->actingAs($owner);

        $adminChannels = ['simulator', 'admin', 'dashboard', 'internal', 'console'];
        foreach ($adminChannels as $slug) {
            $ch = Channel::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'driver' => 'web']);
            $acc = ChannelAccount::firstOrCreate(
                ['workspace_id' => $this->workspace->id, 'channel_id' => $ch->id],
                [
                    'name' => "Acc {$slug}",
                    'external_id' => "ext_{$slug}",
                    'access_token' => "tok_{$slug}",
                    'is_active' => true,
                ]
            );
            $conv = Conversation::create([
                'channel_account_id' => $acc->id,
                'external_user_id' => "admin_{$slug}",
                'status' => 'open',
                'last_direction' => 'inbound',
            ]);

            $this->assertFalse(
                $this->service->isCustomerChannel($conv),
                "Channel slug '{$slug}' with authenticated admin must NOT be classified as a customer channel."
            );
        }
    }

    /**
     * AC-38 Adversarial Test:
     * Comprehensive multi-lingual adversarial workforce queries in Bangla, Banglish, and English
     * must be safely intercepted server-side without leaking proprietary performance numbers.
     */
    public function test_adversarial_workforce_requests_in_bangla_banglish_and_english_are_strictly_denied(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id' => 'adv_user_01',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        $adversarialQueries = [
            // Bangla
            'রহিম এই মাসে কত টাকার বিক্রি করেছে?',
            'কোন সেলসম্যানের পারফরম্যান্স সবচেয়ে ভালো?',
            'কর্মচারীদের কমিশনের তালিকা দিন',
            'কার সেলস সবচেয়ে বেশি?',
            'ব্যবসার লাভ ক্ষতি কি রকম হচ্ছে আজকাল?',
            // Banglish
            'Rahim er sales koto taka?',
            'kono salesman shobcheye beshi order paisey?',
            'employee der salary ba commission report dekhan',
            'profit koto hoise ei mashe?',
            'business er labh koto?',
            'salesman der commission er breakdown din',
            // English
            'Who is the top performing salesman?',
            'What are the quarterly sales numbers for each employee?',
            'Who brought in the most revenue for the store?',
            'Who sold the most items yesterday?',
            'Show me confidential financial figures',
            'Is the company making good profit this month?',
        ];

        foreach ($adversarialQueries as $query) {
            $this->assertTrue(
                $this->service->hasInternalWorkforceOrFinancialIntent($query),
                "Adversarial workforce/financial query not detected: '{$query}'"
            );

            $result = $this->service->handleQuery($query, $this->workspace->id, $conversation);
            $this->assertSame(
                CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
                $result['reply'],
                "Adversarial query leaked data or failed refusal: '{$query}'"
            );
            $this->assertSame('analytics_denied', $result['route']);

            $directReply = $this->service->generateReply($conversation, $query, $this->workspace->id);
            $this->assertSame(
                CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
                $directReply,
                "generateReply failed refusal on adversarial query: '{$query}'"
            );
        }
    }

    /**
     * False Positive Safeguard:
     * Legitimate customer service questions that mention an employee name,
     * ask to speak with an agent, or ask about personal order totals must NOT be falsely blocked.
     */
    public function test_legitimate_customer_inquiries_with_agent_or_staff_names_are_not_falsely_denied(): void
    {
        $safeQueries = [
            'আমি সেলসম্যান রহিমের সাথে কথা বলতে চাই',
            'রহিমকে আমার পার্সেল পৌঁছে দিতে বলুন',
            'মার্চ মাসে আমার মোট কত টাকার কেনাকাটা হয়েছিল?',
            'আমার অর্ডারের মোট কত টাকা হয়েছে?',
            'এই শার্টের দাম কত?',
            'ক্যাশ অন ডেলিভারি কি সারা বাংলাদেশে পাওয়া যায়?',
            'Do you sell leather formal oxford shoes?',
        ];

        foreach ($safeQueries as $query) {
            $this->assertFalse(
                $this->service->hasInternalWorkforceOrFinancialIntent($query),
                "False positive detected: query falsely classified as workforce/financial leak: '{$query}'"
            );
        }
    }

    /**
     * AC-39 RBAC Verification:
     * A user with role 'manager' in Workspace 1 without 'view_analytics' permission is BLOCKED.
     * When granted 'view_analytics' permission, they are AUTHORIZED.
     */
    public function test_manager_with_insufficient_permissions_is_strictly_blocked_from_analytics(): void
    {
        $manager = User::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Acme Floor Manager',
            'email' => 'manager@acme.test',
            'password' => bcrypt('secret123'),
        ]);
        $manager->assignRole('manager');

        $this->actingAs($manager);

        // 1. Manager without permission -> BLOCKED
        $this->assertFalse(
            $this->service->isAuthenticatedOwnerOrManager($this->workspace->id),
            'Manager without view_analytics permission must NOT be authorized for analytics.'
        );

        $reflector = new \ReflectionClass(CustomerSupportService::class);
        $method = $reflector->getMethod('executeAnalyticsRoute');
        $method->setAccessible(true);
        $routingResult = new RoutingResult(route: RouteType::ANALYTICS, confidence: 1.0);

        $simConv = Conversation::create([
            'channel_account_id' => $this->simulatorAccount->id,
            'external_user_id' => 'sim_mgr_01',
            'status' => 'open',
            'last_direction' => 'inbound',
            'metadata' => ['is_simulator' => false, 'role' => 'manager', 'permissions' => []],
        ]);

        $blockedReply = $method->invoke($this->service, $simConv, 'sales report', $this->workspace->id, $routingResult);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $blockedReply);

        // 2. Manager granted view_analytics permission -> AUTHORIZED
        $manager->givePermissionTo('view_analytics');
        $this->assertTrue(
            $this->service->isAuthenticatedOwnerOrManager($this->workspace->id),
            'Manager with view_analytics permission must be authorized for analytics.'
        );
    }

    /**
     * AC-39 RBAC:
     * Staff role (support agent/salesman) CANNOT execute analytics under any circumstance.
     */
    public function test_staff_role_cannot_execute_analytics(): void
    {
        $staff = User::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Acme Support Agent',
            'email' => 'agent@acme.test',
            'password' => bcrypt('secret123'),
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff);

        $this->assertFalse(
            $this->service->isAuthenticatedOwnerOrManager($this->workspace->id),
            'Staff role must be strictly prohibited from business analytics.'
        );
    }

    /**
     * SUPERADMIN Access Governance:
     * Superadmin can access cross-workspace analytics for system monitoring and governance,
     * while regular workspace admins are strictly confined to their own workspace.
     */
    public function test_superadmin_access_governance_across_workspaces(): void
    {
        $superadmin = User::create([
            'workspace_id' => null,
            'name' => 'Global Superadmin',
            'email' => 'superadmin@system.test',
            'password' => bcrypt('secret123'),
        ]);
        $superadmin->assignRole(RoleEnum::SUPERADMIN->value);

        $this->actingAs($superadmin);

        // Superadmin is authorized across both workspaces
        $this->assertTrue($this->service->isAuthenticatedOwnerOrManager($this->workspace->id));
        $this->assertTrue($this->service->isAuthenticatedOwnerOrManager($this->otherWorkspace->id));
    }

    /**
     * Adversarial Test:
     * Customer channel attempting to bypass ingress checks by injecting
     * client-side or metadata attributes ('role' => 'owner', 'is_simulator' => true).
     * The security barrier MUST reject this attempt because the channel is public.
     */
    public function test_customer_channel_cannot_bypass_ingress_with_spoofed_metadata(): void
    {
        // Attacker creates or sends a conversation over WhatsApp with spoofed administrative metadata
        $spoofedConv = Conversation::create([
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id' => 'adv_spoof_attacker',
            'status' => 'open',
            'last_direction' => 'inbound',
            'metadata' => [
                'role' => 'owner',
                'is_simulator' => true,
                'is_admin' => true,
            ],
        ]);

        $this->assertTrue(
            $this->service->isCustomerChannel($spoofedConv),
            'Public WhatsApp channel must NOT allow administrative bypass via spoofed metadata.'
        );

        $result = $this->service->handleQuery('দোকানের মোট লাভ কত?', $this->workspace->id, $spoofedConv);
        $this->assertSame(
            CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
            $result['reply'],
            'Spoofed metadata on WhatsApp must be blocked with standard denial.'
        );

        $reply = $this->service->generateReply($spoofedConv, 'দোকানের মোট লাভ কত?', $this->workspace->id);
        $this->assertSame(
            CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
            $reply,
            'generateReply must deny spoofed customer channel inquiry.'
        );
    }

    /**
     * Adversarial Test:
     * Indirect or paraphrased attempts to elicit financial figures or employee rankings.
     */
    public function test_indirect_and_paraphrased_workforce_financial_attempts_are_safely_denied(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->widgetAccount->id,
            'external_user_id' => 'indirect_querier',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        $indirectAttempts = [
            'দোকানের ক্যাশবাক্সে কত টাকা জমা হয়েছে?',
            'গত মাসে মোট কত টাকা ক্যাশ ঢুকেছে?',
            'company monthly revenue and turnover report',
            'how much profit did you make this year?',
            'overall earnings of the store',
            'kormi der total bikri koto?',
        ];

        foreach ($indirectAttempts as $query) {
            $this->assertTrue(
                $this->service->hasInternalWorkforceOrFinancialIntent($query),
                "Indirect attempt not caught by intent scanner: '{$query}'"
            );

            $result = $this->service->handleQuery($query, $this->workspace->id, $conversation);
            $this->assertSame(
                CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
                $result['reply']
            );
        }
    }

    /**
     * Adversarial Test:
     * Owner of Workspace 1 attempting to execute analytics on Workspace 2.
     */
    public function test_owner_of_workspace_one_cannot_access_workspace_two_via_handle_query(): void
    {
        $ownerOne = User::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Acme Owner 1',
            'email' => 'owner1@acme.test',
            'password' => bcrypt('secret123'),
        ]);
        $ownerOne->assignRole(RoleEnum::ADMIN->value);

        $this->actingAs($ownerOne);

        // Attempting to query Workspace 2
        $this->assertFalse(
            $this->service->isAuthenticatedOwnerOrManager($this->otherWorkspace->id),
            'Owner of Workspace 1 must NOT be authenticated for Workspace 2.'
        );

        // Even if conversation has simulator metadata for Workspace 2
        $otherSimConv = Conversation::create([
            'channel_account_id' => $this->simulatorAccount->id,
            'external_user_id' => 'sim_cross_ws',
            'status' => 'open',
            'last_direction' => 'inbound',
            'metadata' => ['is_simulator' => false],
        ]);

        $routingResult = new RoutingResult(route: RouteType::ANALYTICS, confidence: 1.0);
        $reflector = new \ReflectionClass(CustomerSupportService::class);
        $method = $reflector->getMethod('executeAnalyticsRoute');
        $method->setAccessible(true);

        $blockedReply = $method->invoke($this->service, $otherSimConv, 'sales report', $this->otherWorkspace->id, $routingResult);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $blockedReply);
    }
}
