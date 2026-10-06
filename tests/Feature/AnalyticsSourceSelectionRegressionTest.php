<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\AI\Tools\BusinessAnalyticsTool;
use App\AI\Tools\ExcelAnalyticsTool;
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

class AnalyticsSourceSelectionRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private User $user;
    private CustomerSupportService $service;
    private $businessToolMock;
    private $excelToolMock;
    private $routerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Test Workspace', 'slug' => 'test-ws']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Web',
            'external_id'  => 'acc_storefront_test',
            'access_token' => 'tok_storefront_test',
            'is_active'    => true,
        ]);

        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'workspace_id' => $this->workspace->id,
        ]);

        $this->businessToolMock = Mockery::mock(BusinessAnalyticsTool::class);
        $this->excelToolMock = Mockery::mock(ExcelAnalyticsTool::class);
        $this->routerMock = Mockery::mock(HybridRouter::class);

        // Always route as ANALYTICS
        $this->routerMock->shouldReceive('route')
            ->andReturn(new RoutingResult(
                RouteType::ANALYTICS,
                0.95,
                'sales_query',
            ));

        $this->app->instance(BusinessAnalyticsTool::class, $this->businessToolMock);
        $this->app->instance(ExcelAnalyticsTool::class, $this->excelToolMock);
        $this->app->instance(HybridRouter::class, $this->routerMock);

        $this->service = $this->app->make(CustomerSupportService::class);
    }

    private function createConversation(array $metadata = []): Conversation
    {
        return Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'usr_' . uniqid(),
            'external_thread_id' => 'th_' . uniqid(),
            'last_direction'     => 'inbound',
            'status'             => 'open',
            'metadata'           => $metadata,
        ]);
    }

    /**
     * Case 1: Fresh conversation without active file -> MySQL wins (৳8,000)
     */
    public function test_01_fresh_query_defaults_to_mysql_hasan(): void
    {
        $conversation = $this->createConversation();

        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('Hasan কত sales করেছে?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Hasan total sales ৳8,000',
                'source_type' => 'production_database',
            ]);

        $this->excelToolMock->shouldNotReceive('execute');

        $reply = $this->service->generateReply($conversation, 'Hasan কত sales করেছে?');
        $this->assertStringContainsString('৳8,000', $reply);
        $this->assertStringContainsString('Production DB', $reply);
    }

    /**
     * Case 2: Fresh query for Karim -> MySQL wins (৳9,000)
     */
    public function test_02_fresh_query_defaults_to_mysql_karim(): void
    {
        $conversation = $this->createConversation();

        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('Karim কত sales করেছে?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Karim total sales ৳9,000',
                'source_type' => 'production_database',
            ]);

        $this->excelToolMock->shouldNotReceive('execute');

        $reply = $this->service->generateReply($conversation, 'Karim কত sales করেছে?');
        $this->assertStringContainsString('৳9,000', $reply);
    }

    /**
     * Case 3: Explicit filename mention -> Uploaded File wins (৳1,600) and sets active context
     */
    public function test_03_explicit_filename_routes_to_file_and_sets_active_context(): void
    {
        $conversation = $this->createConversation();

        $this->excelToolMock->shouldReceive('execute')
            ->once()
            ->with('sales.xlsx-এ Hasan কত sales করেছে?', $this->workspace->id, null, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'File sales.xlsx: Hasan sales ৳1,600',
                'source_id' => 'src_sales_xlsx',
                'filename' => 'sales.xlsx',
            ]);

        $this->businessToolMock->shouldNotReceive('execute');

        $reply = $this->service->generateReply($conversation, 'sales.xlsx-এ Hasan কত sales করেছে?');
        $this->assertStringContainsString('৳1,600', $reply);

        $conversation->refresh();
        $this->assertEquals('src_sales_xlsx', $conversation->metadata['active_file_id']);
        $this->assertEquals('sales.xlsx', $conversation->metadata['active_filename']);
    }

    /**
     * Case 4: Explicit filename for Karim -> Uploaded File wins (৳1,800)
     */
    public function test_04_explicit_filename_karim_routes_to_file(): void
    {
        $conversation = $this->createConversation();

        $this->excelToolMock->shouldReceive('execute')
            ->once()
            ->with('sales.xlsx-এর Karim কত sales করেছে?', $this->workspace->id, null, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'File sales.xlsx: Karim sales ৳1,800',
                'source_id' => 'src_sales_xlsx',
                'filename' => 'sales.xlsx',
            ]);

        $reply = $this->service->generateReply($conversation, 'sales.xlsx-এর Karim কত sales করেছে?');
        $this->assertStringContainsString('৳1,800', $reply);
    }

    /**
     * Case 5: Explicit Database mention -> Production MySQL wins (৳8,000)
     */
    public function test_05_explicit_database_mention_routes_to_mysql(): void
    {
        $conversation = $this->createConversation();

        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('Database-এ Hasan কত sales করেছে?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Hasan total sales ৳8,000',
            ]);

        $this->excelToolMock->shouldNotReceive('execute');

        $reply = $this->service->generateReply($conversation, 'Database-এ Hasan কত sales করেছে?');
        $this->assertStringContainsString('৳8,000', $reply);
    }

    /**
     * Case 6: Explicit Database mention for Karim -> Production MySQL wins (৳9,000)
     */
    public function test_06_explicit_database_karim_routes_to_mysql(): void
    {
        $conversation = $this->createConversation();

        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('Database-এর Karim কত sales করেছে?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Karim total sales ৳9,000',
            ]);

        $reply = $this->service->generateReply($conversation, 'Database-এর Karim কত sales করেছে?');
        $this->assertStringContainsString('৳9,000', $reply);
    }

    /**
     * Case 7: Multi-turn File Context: 'sales.xlsx-এ Hasan...' then 'আর Karim-এরটা?'
     * Follow-up stays on file context (৳1,600 -> ৳1,800)
     */
    public function test_07_file_context_preserved_in_followup(): void
    {
        $conversation = $this->createConversation([
            'active_file_id' => 'src_sales_xlsx',
            'active_filename' => 'sales.xlsx',
        ]);

        $this->excelToolMock->shouldReceive('execute')
            ->once()
            ->with('আর Karim-এরটা?', $this->workspace->id, 'src_sales_xlsx', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'File sales.xlsx: Karim sales ৳1,800',
                'source_id' => 'src_sales_xlsx',
            ]);

        $this->businessToolMock->shouldNotReceive('execute');

        $reply = $this->service->generateReply($conversation, 'আর Karim-এরটা?');
        $this->assertStringContainsString('৳1,800', $reply);
    }

    /**
     * Case 8 & 9: Switch from File to DB:
     * Turn 1: file is active.
     * Turn 2: 'Database-এ Hasan কত sales করেছে?' -> Clears active_file_id and returns DB ৳8,000.
     * Turn 3: 'আর Karim-এরটা?' -> Stays on DB (৳9,000) because active_file_id was cleared!
     */
    public function test_08_and_09_explicit_database_switch_clears_file_context_and_preserves_db(): void
    {
        $conversation = $this->createConversation([
            'active_file_id' => 'src_sales_xlsx',
            'active_filename' => 'sales.xlsx',
        ]);

        // Turn 2: Explicit Database query
        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('Database-এ Hasan কত sales করেছে?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Hasan total sales ৳8,000',
            ]);

        $replyDb = $this->service->generateReply($conversation, 'Database-এ Hasan কত sales করেছে?');
        $this->assertStringContainsString('৳8,000', $replyDb);

        // Verify active_file_id is cleared
        $conversation->refresh();
        $this->assertArrayNotHasKey('active_file_id', $conversation->metadata ?? []);

        // Turn 3: Follow-up 'আর Karim-এরটা?' must stay on DB because active_file_id is null!
        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('আর Karim-এরটা?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Karim total sales ৳9,000',
            ]);

        $replyFollowup = $this->service->generateReply($conversation, 'আর Karim-এরটা?');
        $this->assertStringContainsString('৳9,000', $replyFollowup);
    }

    /**
     * Case 10 & 11: Switch from DB to File:
     * Turn 1: on DB.
     * Turn 2: 'sales.xlsx-এ Hasan কত sales করেছে?' -> Routes to file (৳1,600) and sets active_file_id.
     * Turn 3: 'আর Karim-এরটা?' -> Stays on file (৳1,800).
     */
    public function test_10_and_11_explicit_file_switch_sets_context_and_followup_preserves_file(): void
    {
        $conversation = $this->createConversation();

        // Turn 2: Explicit File mention
        $this->excelToolMock->shouldReceive('execute')
            ->once()
            ->with('sales.xlsx-এ Hasan কত sales করেছে?', $this->workspace->id, null, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'File sales.xlsx: Hasan sales ৳1,600',
                'source_id' => 'src_sales_xlsx',
                'filename' => 'sales.xlsx',
            ]);

        $replyFile = $this->service->generateReply($conversation, 'sales.xlsx-এ Hasan কত sales করেছে?');
        $this->assertStringContainsString('৳1,600', $replyFile);

        $conversation->refresh();
        $this->assertEquals('src_sales_xlsx', $conversation->metadata['active_file_id']);

        // Turn 3: Followup
        $this->excelToolMock->shouldReceive('execute')
            ->once()
            ->with('আর Karim-এরটা?', $this->workspace->id, 'src_sales_xlsx', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'File sales.xlsx: Karim sales ৳1,800',
                'source_id' => 'src_sales_xlsx',
            ]);

        $replyFollowup = $this->service->generateReply($conversation, 'আর Karim-এরটা?');
        $this->assertStringContainsString('৳1,800', $replyFollowup);
    }

    /**
     * Case 12: Bangla Database keyword override: 'সিস্টেমে Hasan-এর সেলস কত?' overrides active file.
     */
    public function test_12_bangla_system_keyword_overrides_active_file(): void
    {
        $conversation = $this->createConversation([
            'active_file_id' => 'src_sales_xlsx',
        ]);

        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('সিস্টেমে Hasan-এর সেলস কত?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Hasan total sales ৳8,000',
            ]);

        $reply = $this->service->generateReply($conversation, 'সিস্টেমে Hasan-এর সেলস কত?');
        $this->assertStringContainsString('৳8,000', $reply);

        $conversation->refresh();
        $this->assertArrayNotHasKey('active_file_id', $conversation->metadata ?? []);
    }

    /**
     * Case 13: Bangla File indicator: 'এই file-এ Hasan কত sales করেছে?' overrides DB default.
     */
    public function test_13_bangla_file_indicator_routes_to_file(): void
    {
        $conversation = $this->createConversation();

        $this->excelToolMock->shouldReceive('execute')
            ->once()
            ->with('এই file-এ Hasan কত sales করেছে?', $this->workspace->id, null, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'File sales.xlsx: Hasan sales ৳1,600',
                'source_id' => 'src_sales_xlsx',
            ]);

        $reply = $this->service->generateReply($conversation, 'এই file-এ Hasan কত sales করেছে?');
        $this->assertStringContainsString('৳1,600', $reply);
    }

    /**
     * Case 14: One uploaded file existing in the workspace does NOT intercept an otherwise ambiguous fresh query.
     */
    public function test_14_single_uploaded_file_does_not_intercept_ambiguous_fresh_query(): void
    {
        // Conversation without active_file_id
        $conversation = $this->createConversation();

        // Must hit business tool (MySQL), NOT excel tool
        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('Hasan কত sales করেছে?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Hasan total sales ৳8,000',
                'source_type' => 'production_database',
            ]);

        $this->excelToolMock->shouldNotReceive('execute');

        $reply = $this->service->generateReply($conversation, 'Hasan কত sales করেছে?');
        $this->assertStringContainsString('৳8,000', $reply);
    }

    /**
     * Case 15: Multiple uploaded files do NOT silently select latest file when ambiguous.
     */
    public function test_15_multiple_uploaded_files_do_not_silently_select_latest_file(): void
    {
        $conversation = $this->createConversation();

        $this->excelToolMock->shouldReceive('execute')
            ->once()
            ->with('এই file-এর Hasan কত sales করেছে?', $this->workspace->id, null, Mockery::type('array'))
            ->andReturn([
                'success' => false,
                'is_ambiguous' => true,
                'report' => '🤔 একাধিক ফাইলে আপনার প্রশ্নের তথ্য রয়েছে। অনুগ্রহ করে sales.xlsx অথবা sales_october.csv উল্লেখ করুন।',
            ]);

        $reply = $this->service->generateReply($conversation, 'এই file-এর Hasan কত sales করেছে?');
        $this->assertStringContainsString('একাধিক ফাইলে', $reply);
        $this->assertStringContainsString('sales.xlsx', $reply);
    }

    /**
     * Case 16: Zero data mixing: Result is strictly from one resolved engine, never aggregated (৳8,000 OR ৳1,600, never ৳9,600).
     */
    public function test_16_zero_data_mixing_isolation(): void
    {
        $conversation = $this->createConversation();

        $this->businessToolMock->shouldReceive('execute')
            ->once()
            ->with('Hasan কত sales করেছে?', $this->workspace->id, Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'report' => 'Production DB: Hasan total sales ৳8,000',
                'source_type' => 'production_database',
            ]);

        $reply = $this->service->generateReply($conversation, 'Hasan কত sales করেছে?');
        $this->assertStringContainsString('৳8,000', $reply);
        $this->assertStringNotContainsString('৳9,600', $reply);
        $this->assertStringNotContainsString('৳1,600', $reply);
    }
}

