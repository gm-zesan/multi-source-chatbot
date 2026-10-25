<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IngestionPurpose;
use App\Enums\Permissions\FAQPermission;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseHubTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $userA;
    protected User $userB;
    protected User $superAdmin;
    protected User $restrictedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->workspaceA = Workspace::create(['name' => 'Workspace A', 'slug' => 'workspace-a']);
        $this->workspaceB = Workspace::create(['name' => 'Workspace B', 'slug' => 'workspace-b']);

        // Role with both FAQ and Document view permissions
        $roleA = Role::create([
            'name'         => 'manager-a',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $roleA->givePermissionTo([
            FAQPermission::VIEW->value,
            FAQPermission::CREATE->value,
            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->userA->assignRole($roleA);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);

        // Superadmin
        $superRole = Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $this->superAdmin = User::factory()->create([
            'workspace_id' => null,
        ]);
        $this->superAdmin->assignRole($superRole);

        // Restricted user with no KB permissions
        $restrictedRole = Role::create([
            'name'         => 'restricted-a',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->restrictedUser = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->restrictedUser->assignRole($restrictedRole);
    }

    /**
     * Requirement 10: Hub authorization - unauthenticated user is redirected to login.
     */
    public function test_unauthenticated_user_cannot_access_knowledge_base_hub(): void
    {
        $response = $this->get('/dashboard/knowledge-base');
        $response->assertRedirect('/login');
    }

    /**
     * Requirement 10: Authorized tenant user can access the Knowledge Base hub.
     */
    public function test_authorized_tenant_user_can_access_knowledge_base_hub(): void
    {
        $response = $this->actingAs($this->userA)->get('/dashboard/knowledge-base');
        $response->assertOk();
        $response->assertViewIs('admin.knowledge-base.index');
        $response->assertSee('Knowledge Base');
        $response->assertSee('FAQs (Q&A)', false);
        $response->assertSee('Policies');
        $response->assertSee('Business Information');
        $response->assertSee('Analytics Data Sources');
    }

    /**
     * Requirement 10: User lacking both FAQ and Document view permissions is forbidden.
     */
    public function test_user_without_kb_permissions_is_forbidden_from_hub(): void
    {
        $response = $this->actingAs($this->restrictedUser)->get('/dashboard/knowledge-base');
        $response->assertForbidden();
    }

    /**
     * Requirement 10: Superadmin can access the Knowledge Base hub.
     */
    public function test_superadmin_can_access_knowledge_base_hub(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/dashboard/knowledge-base');
        $response->assertOk();
    }

    /**
     * Requirement 5: Canonical redirect /knowledge-base -> /dashboard/knowledge-base.
     */
    public function test_canonical_knowledge_base_redirect_works(): void
    {
        $response = $this->actingAs($this->userA)->get('/knowledge-base');
        $response->assertRedirect(route('knowledge-base.index'));
    }

    public function test_legacy_documents_tab_maps_to_data_sources(): void
    {
        $response = $this->actingAs($this->userA)->get('/dashboard/knowledge-base?tab=documents');
        $response->assertOk();
        $response->assertViewHas('activeTab', 'data_sources');
    }

    /**
     * Requirement 7: Backward compatibility - existing routes remain accessible.
     */
    public function test_backward_compatibility_existing_routes_remain_accessible(): void
    {
        $this->actingAs($this->userA)->get('/dashboard/faqs')->assertOk();
        $this->actingAs($this->userA)->get('/dashboard/documents')->assertOk();
    }

    /**
     * Requirement 6: Tab/filter navigation - FAQ service filters by type_group.
     */
    public function test_knowledge_base_filters_faqs_by_type_group(): void
    {
        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'How do I track my order?',
            'answer'        => 'Use the tracking portal.',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Return Policy (30 Days)',
            'answer'        => 'Returns accepted within 30 days.',
            'document_type' => 'return_policy',
            'is_active'     => true,
        ]);

        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Customer Care Hotline',
            'answer'        => 'Call +880 1700-000000.',
            'document_type' => 'contact',
            'is_active'     => true,
        ]);

        // 1. Query policies tab
        $policyResponse = $this->actingAs($this->userA)
            ->getJson('/dashboard/faqs?type_group=policy');
        $policyResponse->assertOk();
        $policyData = $policyResponse->json('data');
        $this->assertCount(1, $policyData);
        $this->assertEquals('Return Policy (30 Days)', $policyData[0]['question']);

        // 2. Query business_info tab
        $bizResponse = $this->actingAs($this->userA)
            ->getJson('/dashboard/faqs?type_group=business_info');
        $bizResponse->assertOk();
        $bizData = $bizResponse->json('data');
        $this->assertCount(1, $bizData);
        $this->assertEquals('Customer Care Hotline', $bizData[0]['question']);

        // 3. Query faqs tab
        $faqResponse = $this->actingAs($this->userA)
            ->getJson('/dashboard/faqs?type_group=faq');
        $faqResponse->assertOk();
        $faqData = $faqResponse->json('data');
        $this->assertCount(1, $faqData);
        $this->assertEquals('How do I track my order?', $faqData[0]['question']);
    }

    /**
     * Requirement 6: Tab/filter navigation - Document service filters by purpose.
     */
    public function test_knowledge_base_filters_documents_by_purpose(): void
    {
        KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Q3 Sales Report',
            'original_filename' => 'q3_sales.xlsx',
            'file_path'         => 'documents/dummy.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'content_hash'      => 'hash-1',
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Company Guidelines',
            'original_filename' => 'guidelines.txt',
            'file_path'         => 'documents/guidelines.txt',
            'mime_type'         => 'text/plain',
            'file_size'         => 512,
            'content_hash'      => 'hash-2',
            'purpose'           => IngestionPurpose::KNOWLEDGE_BASE,
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        // 1. Filter data_analytics
        $analyticsResponse = $this->actingAs($this->userA)
            ->getJson('/dashboard/documents?purpose=data_analytics');
        $analyticsResponse->assertOk();
        $analyticsData = $analyticsResponse->json('data');
        $this->assertCount(1, $analyticsData);
        $this->assertEquals('Q3 Sales Report', $analyticsData[0]['title']);

        // 2. Filter knowledge_base text documents
        $textDocResponse = $this->actingAs($this->userA)
            ->getJson('/dashboard/documents?purpose=knowledge_base');
        $textDocResponse->assertOk();
        $textDocData = $textDocResponse->json('data');
        $this->assertCount(1, $textDocData);
        $this->assertEquals('Company Guidelines', $textDocData[0]['title']);

        // 3. Hub AJAX endpoint serves documents tab cleanly
        $hubDocResponse = $this->actingAs($this->userA)
            ->getJson('/dashboard/knowledge-base?source=documents&purpose=knowledge_base');
        $hubDocResponse->assertOk();
        $hubDocData = $hubDocResponse->json('data');
        $this->assertCount(1, $hubDocData);
        $this->assertEquals('Company Guidelines', $hubDocData[0]['title']);

        // 4. Hub AJAX endpoint serves data_sources tab cleanly
        $hubAnalyticsResponse = $this->actingAs($this->userA)
            ->getJson('/dashboard/knowledge-base?source=documents&purpose=data_analytics');
        $hubAnalyticsResponse->assertOk();
        $hubAnalyticsData = $hubAnalyticsResponse->json('data');
        $this->assertCount(1, $hubAnalyticsData);
        $this->assertEquals('Q3 Sales Report', $hubAnalyticsData[0]['title']);
    }

    /**
     * Requirement 10: Multi-tenant workspace isolation is strictly preserved in the hub.
     */
    public function test_knowledge_base_hub_preserves_tenant_isolation(): void
    {
        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Tenant A Secret FAQ',
            'answer'        => 'Visible to Tenant A only.',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        FAQ::create([
            'workspace_id'  => $this->workspaceB->id,
            'question'      => 'Tenant B Secret FAQ',
            'answer'        => 'Visible to Tenant B only.',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        $responseA = $this->actingAs($this->userA)->getJson('/dashboard/faqs');
        $responseA->assertOk();
        $dataA = $responseA->json('data');
        $this->assertCount(1, $dataA);
        $this->assertEquals('Tenant A Secret FAQ', $dataA[0]['question']);

        // User B cannot see Tenant A's FAQ
        $roleB = Role::create([
            'name'         => 'manager-b',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceB->id,
        ]);
        $roleB->givePermissionTo([FAQPermission::VIEW->value]);
        $this->userB->assignRole($roleB);

        $responseB = $this->actingAs($this->userB)->getJson('/dashboard/faqs');
        $responseB->assertOk();
        $dataB = $responseB->json('data');
        $this->assertCount(1, $dataB);
        $this->assertEquals('Tenant B Secret FAQ', $dataB[0]['question']);
    }
}
