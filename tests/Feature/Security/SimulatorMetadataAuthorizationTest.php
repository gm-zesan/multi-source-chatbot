<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\AI\Orchestration\CustomerSupportService;
use App\AI\Decision\UnifiedOrchestrator;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Spreadsheet\Security\Exceptions\TenantScopeViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Simulator Metadata Authorization Boundary Verification Suite
 *
 * Verifies whether `is_simulator: true` metadata in conversation state allows
 * an unauthorized STAFF user to bypass Layer 2 barriers and execute analytics.
 */
class SimulatorMetadataAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $ownerA;
    private User $managerA;
    private User $staffA;
    private User $ownerB;
    private Channel $simulatorChannel;
    private ChannelAccount $simulatorAccountA;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'view_analytics', 'guard_name' => 'web'],
            ['display_name' => 'View Analytics', 'module' => 'analytics']
        );

        // Workspaces
        $this->workspaceA = Workspace::create(['name' => 'Workspace Alpha', 'slug' => 'ws-alpha', 'is_active' => true]);
        $this->workspaceB = Workspace::create(['name' => 'Workspace Beta', 'slug' => 'ws-beta', 'is_active' => true]);

        // Users
        $this->ownerA = User::factory()->create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.test',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->ownerA->assignRole('owner');

        $this->managerA = User::factory()->create([
            'name' => 'Manager Alpha',
            'email' => 'manager@alpha.test',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->managerA->assignRole('manager');

        $this->staffA = User::factory()->create([
            'name' => 'Staff Alpha',
            'email' => 'staff@alpha.test',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->staffA->assignRole('staff');

        $this->ownerB = User::factory()->create([
            'name' => 'Owner Beta',
            'email' => 'owner@beta.test',
            'workspace_id' => $this->workspaceB->id,
        ]);
        $this->ownerB->assignRole('owner');

        // Channel and ChannelAccount
        $this->simulatorChannel = Channel::create(['name' => 'Simulator', 'slug' => 'simulator', 'driver' => 'web']);

        $this->simulatorAccountA = ChannelAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'channel_id' => $this->simulatorChannel->id,
            'name' => 'Simulator Account Alpha',
            'external_id' => 'sim_acc_alpha',
            'access_token' => 'sim_token_alpha',
            'is_active' => true,
        ]);

        // Seed spreadsheet and structured records for Workspace A
        $docA = KnowledgeDocument::create([
            'workspace_id' => $this->workspaceA->id,
            'title' => 'Alpha Financials 2026',
            'original_filename' => 'alpha_financials_2026.xlsx',
            'file_path' => 'documents/alpha_financials_2026.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content_hash' => hash('sha256', 'alpha_fin'),
            'source_id' => 'src_alpha_fin',
            'file_size' => 2048,
            'lifecycle_status' => \App\Enums\DocumentLifecycleStatus::ACTIVE,
        ]);

        $jobA = SpreadsheetImportJob::create([
            'workspace_id' => $this->workspaceA->id,
            'document_id' => $docA->id,
            'generation_id' => 'gen_alpha_fin',
            'status' => 'completed',
        ]);

        StructuredRecord::create([
            'workspace_id' => $this->workspaceA->id,
            'document_id' => (string) $docA->id,
            'import_job_id' => $jobA->id,
            'sheet_name' => 'Financials',
            'row_index' => 1,
            'payload' => ['TotalRevenue' => 5000000, 'NetProfit' => 1200000],
            'record_hash' => hash('sha256', 'row_1_fin'),
            'generation_id' => (string) $jobA->generation_id,
        ]);
    }

    /**
     * Scenario 1: Authorized OWNER in Simulator
     *
     * Route: POST /dashboard/simulator/send
     * Expected: HTTP 200, enters UnifiedOrchestrator, executes Contract 3.0 virtual execution, returns owner report.
     */
    public function test_scenario_1_authorized_owner_executes_owner_business_assistant(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->postJson('/dashboard/simulator/send', [
                'message' => 'আজকে মোট বিক্রি কত হয়েছে?',
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertSame('analytics', $json['route']);
        $this->assertStringContainsString('ব্যবসায়িক অ্যানালিটিক্স ইনসাইট', $json['reply']);
        $this->assertStringContainsString('alpha_financials_2026.xlsx', $json['sources'][0] ?? '');
    }

    /**
     * Scenario 2: Authorized MANAGER in Simulator
     *
     * Route: POST /dashboard/simulator/send
     * Expected: HTTP 200, enters UnifiedOrchestrator, authorized as manager, returns owner report.
     */
    public function test_scenario_2_authorized_manager_executes_owner_business_assistant(): void
    {
        $response = $this->actingAs($this->managerA)
            ->postJson('/dashboard/simulator/send', [
                'message' => 'Total sale koto?',
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertSame('analytics', $json['route']);
        $this->assertStringContainsString('ব্যবসায়িক অ্যানালিটিক্স ইনসাইট', $json['reply']);
    }

    /**
     * Scenario 3: STAFF without analytics permission in Simulator
     *
     * Route: POST /dashboard/simulator/send
     * Question: Does staff get blocked, OR does simulator metadata allow execution of BusinessAnalyticsTool?
     *
     * When query is routed to ANALYTICS, we observe what actually executes.
     */
    public function test_scenario_3_staff_user_simulator_metadata_execution_behavior(): void
    {
        // Mock external Python analytics endpoint
        Http::fake([
            '*/analytics/query' => Http::response([
                'success' => true,
                'engine' => 'semantic',
                'intent' => 'business_analytics',
                'report' => 'CONFIDENTIAL FINANCIAL REPORT: Total revenue is 5,000,000 BDT.',
            ], 200),
        ]);

        $response = $this->actingAs($this->staffA)
            ->postJson('/dashboard/simulator/send', [
                'message' => 'আজকে মোট বিক্রি কত হয়েছে?',
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        // ── SECURITY VERIFICATION ──
        // Verify that the staff user does NOT receive confidential data
        // and is strictly blocked by the customer analytics security barrier.
        $receivedConfidentialData = str_contains($json['reply'] ?? '', 'CONFIDENTIAL FINANCIAL REPORT');
        $receivedDenialMessage = ($json['reply'] ?? '') === CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE;

        $this->assertFalse(
            $receivedConfidentialData,
            'SECURITY FIX VERIFICATION: Staff user in simulator must NEVER receive confidential analytics data!'
        );
        $this->assertTrue(
            $receivedDenialMessage,
            'SECURITY FIX VERIFICATION: CustomerSupportService must block staff with CUSTOMER_ANALYTICS_DENIAL_MESSAGE.'
        );
        $this->assertSame('analytics_denied', $json['route']);
    }

    /**
     * Scenario 4: STAFF calling UnifiedOrchestrator DIRECTLY
     *
     * In contrast to the simulator endpoint, calling UnifiedOrchestrator directly
     * checks actor role strictly and safely denies customer analytics.
     */
    public function test_scenario_4_staff_calling_owner_assistant_directly_is_strictly_blocked(): void
    {
        $orchestrator = app(UnifiedOrchestrator::class);

        $conv = Conversation::create([
            'channel_account_id' => $this->simulatorAccountA->id,
            'external_user_id' => 'direct_test_staff',
            'last_direction' => 'inbound',
            'metadata' => ['is_simulator' => true, 'is_customer' => false, 'role' => 'staff'],
        ]);

        $result = $orchestrator->process(
            query: 'আজকে মোট বিক্রি কত হয়েছে?',
            workspaceId: $this->workspaceA->id,
            user: $this->staffA,
            conversation: $conv,
            channelSlug: 'simulator',
        );

        $this->assertSame('analytics_denied', $result['route']);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $result['reply']);
    }

    /**
     * Scenario 5: Cross-Workspace Access via Simulator
     *
     * Owner of Workspace B attempting to switch to Workspace A via simulator endpoint.
     */
    public function test_scenario_5a_cross_workspace_switch_denied_for_non_superadmin(): void
    {
        $response = $this->actingAs($this->ownerB)
            ->postJson('/dashboard/simulator/switch-workspace', [
                'workspace_id' => $this->workspaceA->id,
            ]);

        // HTTP 403 Forbidden: Only superadmins can switch workspace
        $response->assertStatus(403);
    }

    /**
     * Scenario 5b: Cross-Workspace User sending query with explicit workspace_id parameter
     *
     * Owner of Workspace B calls simulator/send with workspace_id = Workspace A.
     * resolveWorkspaceId ignores the parameter for non-superadmins and forces Workspace B.
     */
    public function test_scenario_5b_cross_workspace_parameter_ignored_for_non_superadmin(): void
    {
        $response = $this->actingAs($this->ownerB)
            ->postJson('/dashboard/simulator/send', [
                'message' => 'আজকে মোট বিক্রি কত হয়েছে?',
                'workspace_id' => $this->workspaceA->id, // Attempting to target Workspace A
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        // Workspace B has NO spreadsheet jobs or structured records.
        // It must NOT access Workspace A's alpha_financials_2026.xlsx.
        $this->assertStringNotContainsString('alpha_financials_2026.xlsx', $json['reply'] ?? '');
    }
}

