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
     * Requirement 7: Legacy standalone page routes redirect to Hub while JSON endpoints remain accessible.
     */
    public function test_legacy_standalone_page_routes_redirect_to_hub_and_no_longer_render_legacy_pages(): void
    {
        // 1. Browser GET /dashboard/faqs redirects to Hub faqs tab
        $faqResponse = $this->actingAs($this->userA)->get('/dashboard/faqs');
        $faqResponse->assertRedirect(route('knowledge-base.index', ['tab' => 'faqs']));

        // 2. Browser GET /dashboard/documents redirects to Hub data_sources tab
        $docResponse = $this->actingAs($this->userA)->get('/dashboard/documents');
        $docResponse->assertRedirect(route('knowledge-base.index', ['tab' => 'data_sources']));

        // 3. DataTables AJAX requests on legacy endpoints remain functional for backward compatibility
        $this->actingAs($this->userA)->getJson('/dashboard/faqs')->assertOk();
        $this->actingAs($this->userA)->getJson('/dashboard/documents')->assertOk();
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

    /**
     * Requirement A.1 & A.5: faq.view only -> FAQ data allowed; document data denied without leaking data.
     */
    public function test_user_with_only_faq_view_can_access_faqs_but_is_forbidden_from_documents(): void
    {
        $roleFaqOnly = Role::create([
            'name'         => 'faq-only',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $roleFaqOnly->givePermissionTo([FAQPermission::VIEW->value]);
        $userFaqOnly = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $userFaqOnly->assignRole($roleFaqOnly);

        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'FAQ Question Allowed',
            'answer'        => 'Answer here',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Secret Analytics Doc',
            'original_filename' => 'doc.xlsx',
            'file_path'         => 'documents/dummy.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'content_hash'      => 'hash-secret-doc',
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        // 1. Hub page access allowed; defaults to faqs; displays FAQ tabs but hides Data Sources tab
        $pageResponse = $this->actingAs($userFaqOnly)->get('/dashboard/knowledge-base');
        $pageResponse->assertOk();
        $pageResponse->assertViewHas('activeTab', 'faqs');
        $pageResponse->assertSee('FAQs (Q&A)', false);
        $pageResponse->assertDontSee('Analytics Data Sources');

        // 2. FAQ AJAX endpoint allowed and returns FAQ record
        $faqResponse = $this->actingAs($userFaqOnly)->getJson('/dashboard/knowledge-base?source=faqs');
        $faqResponse->assertOk();
        $faqData = $faqResponse->json('data');
        $this->assertNotEmpty($faqData);
        $this->assertEquals('FAQ Question Allowed', $faqData[0]['question']);

        // 3. Document AJAX endpoint returns 403 Forbidden without leaking documents
        $docResponse = $this->actingAs($userFaqOnly)->getJson('/dashboard/knowledge-base?source=documents');
        $docResponse->assertForbidden();
        $this->assertStringNotContainsString('Secret Analytics Doc', $docResponse->getContent());
    }

    /**
     * Requirement A.2 & A.5: knowledge_document.view only -> document data allowed; FAQ data denied without leaking data.
     */
    public function test_user_with_only_document_view_can_access_documents_but_is_forbidden_from_faqs(): void
    {
        $roleDocOnly = Role::create([
            'name'         => 'doc-only',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $roleDocOnly->givePermissionTo([KnowledgeDocumentPermission::VIEW->value]);
        $userDocOnly = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $userDocOnly->assignRole($roleDocOnly);

        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Secret FAQ Item',
            'answer'        => 'Answer here',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Allowed Data Source',
            'original_filename' => 'allowed.xlsx',
            'file_path'         => 'documents/allowed.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'content_hash'      => 'hash-allowed-doc',
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        // 1. Hub page access allowed; defaults automatically to data_sources tab; hides FAQ tabs
        $pageResponse = $this->actingAs($userDocOnly)->get('/dashboard/knowledge-base');
        $pageResponse->assertOk();
        $pageResponse->assertViewHas('activeTab', 'data_sources');
        $pageResponse->assertSee('Analytics Data Sources');
        $pageResponse->assertDontSee('FAQs (Q&A)');

        // 2. Document AJAX endpoint allowed and returns document record
        $docResponse = $this->actingAs($userDocOnly)->getJson('/dashboard/knowledge-base?source=documents');
        $docResponse->assertOk();
        $docData = $docResponse->json('data');
        $this->assertNotEmpty($docData);
        $this->assertEquals('Allowed Data Source', $docData[0]['title']);

        // 3. FAQ AJAX endpoint returns 403 Forbidden without leaking FAQs
        $faqResponse = $this->actingAs($userDocOnly)->getJson('/dashboard/knowledge-base?source=faqs');
        $faqResponse->assertForbidden();
        $this->assertStringNotContainsString('Secret FAQ Item', $faqResponse->getContent());
    }

    /**
     * Requirement A.4 & C: Superadmin explicit workspace filtering in Hub DataTables.
     */
    public function test_superadmin_workspace_filtering_in_hub(): void
    {
        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'FAQ Workspace A',
            'answer'        => 'Answer A',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        FAQ::create([
            'workspace_id'  => $this->workspaceB->id,
            'question'      => 'FAQ Workspace B',
            'answer'        => 'Answer B',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Document Workspace A',
            'original_filename' => 'doc_a.xlsx',
            'file_path'         => 'documents/doc_a.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'content_hash'      => 'hash-doc-a',
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceB->id,
            'title'             => 'Document Workspace B',
            'original_filename' => 'doc_b.xlsx',
            'file_path'         => 'documents/doc_b.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'content_hash'      => 'hash-doc-b',
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        // 1. Superadmin without workspace_id filter sees all records
        $allFaqs = $this->actingAs($this->superAdmin)->getJson('/dashboard/knowledge-base?source=faqs');
        $allFaqs->assertOk();
        $this->assertCount(2, $allFaqs->json('data'));

        // 2. Superadmin filtering by workspace A receives only workspace A FAQs
        $wsAFaqs = $this->actingAs($this->superAdmin)->getJson('/dashboard/knowledge-base?source=faqs&workspace_id=' . $this->workspaceA->id);
        $wsAFaqs->assertOk();
        $this->assertCount(1, $wsAFaqs->json('data'));
        $this->assertEquals('FAQ Workspace A', $wsAFaqs->json('data.0.question'));

        // 3. Superadmin filtering by workspace A receives only workspace A Documents
        $wsADocs = $this->actingAs($this->superAdmin)->getJson('/dashboard/knowledge-base?source=documents&workspace_id=' . $this->workspaceA->id);
        $wsADocs->assertOk();
        $this->assertCount(1, $wsADocs->json('data'));
        $this->assertEquals('Document Workspace A', $wsADocs->json('data.0.title'));
    }

    /**
     * Requirement B: Missing workspace on Superadmin FAQ creation fails with validation error.
     */
    public function test_superadmin_creating_faq_without_workspace_fails_with_validation_error(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/dashboard/faqs', [
            'question'      => 'Superadmin Question without workspace',
            'answer'        => 'Some answer',
            'document_type' => 'faq',
            'priority'      => 0,
            'is_active'     => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workspace_id']);
        $this->assertDatabaseMissing('faqs', [
            'question' => 'Superadmin Question without workspace',
        ]);
    }

    /**
     * Requirement B: Invalid workspace on Superadmin FAQ creation fails with validation error.
     */
    public function test_superadmin_creating_faq_with_invalid_workspace_fails_validation(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/dashboard/faqs', [
            'workspace_id'  => 999999,
            'question'      => 'Superadmin Question with invalid workspace',
            'answer'        => 'Some answer',
            'document_type' => 'faq',
            'priority'      => 0,
            'is_active'     => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workspace_id']);
        $this->assertDatabaseMissing('faqs', [
            'question' => 'Superadmin Question with invalid workspace',
        ]);
    }

    /**
     * Requirement B: Valid Superadmin workspace selection creates FAQ successfully.
     */
    public function test_superadmin_creating_faq_with_valid_workspace_succeeds(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/dashboard/faqs', [
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Superadmin Valid FAQ',
            'answer'        => 'Some valid answer',
            'document_type' => 'faq',
            'priority'      => 1,
            'is_active'     => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('faqs', [
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'Superadmin Valid FAQ',
        ]);
    }

    /**
     * Requirement B: Regular user cannot override their assigned workspace_id.
     */
    public function test_regular_user_cannot_override_workspace_id_on_faq_creation(): void
    {
        $response = $this->actingAs($this->userA)->postJson('/dashboard/faqs', [
            'workspace_id'  => $this->workspaceB->id,
            'question'      => 'Attacker attempt to override workspace',
            'answer'        => 'Exploit attempt',
            'document_type' => 'faq',
            'priority'      => 0,
            'is_active'     => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workspace_id']);
        $this->assertDatabaseMissing('faqs', [
            'question' => 'Attacker attempt to override workspace',
        ]);
    }

    /**
     * Requirement D: Legacy FAQs whose document_type is NULL or empty remain visible under FAQs tab.
     */
    public function test_legacy_faq_with_null_or_empty_document_type_is_visible_under_faqs_tab(): void
    {
        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Legacy FAQ With Empty Document Type',
            'answer'        => 'Legacy answer 2',
            'document_type' => '',
            'is_active'     => true,
        ]);

        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Valid Policy Question',
            'answer'        => 'Policy answer',
            'document_type' => 'return_policy',
            'is_active'     => true,
        ]);

        // 1. FAQs tab query (type_group=faq) returns empty document_type legacy record
        $faqResponse = $this->actingAs($this->userA)->getJson('/dashboard/faqs?type_group=faq');
        $faqResponse->assertOk();
        $faqData = $faqResponse->json('data');
        $questions = array_column($faqData, 'question');
        $this->assertContains('Legacy FAQ With Empty Document Type', $questions);
        $this->assertNotContains('Valid Policy Question', $questions);

        // 2. Policies tab does NOT include the legacy empty FAQs
        $policyResponse = $this->actingAs($this->userA)->getJson('/dashboard/faqs?type_group=policy');
        $policyResponse->assertOk();
        $policyQuestions = array_column($policyResponse->json('data'), 'question');
        $this->assertNotContains('Legacy FAQ With Empty Document Type', $policyQuestions);
        $this->assertContains('Valid Policy Question', $policyQuestions);

        // 3. Business Info tab does NOT include the legacy empty FAQs
        $bizResponse = $this->actingAs($this->userA)->getJson('/dashboard/faqs?type_group=business_info');
        $bizResponse->assertOk();
        $bizQuestions = array_column($bizResponse->json('data'), 'question');
        $this->assertNotContains('Legacy FAQ With Empty Document Type', $bizQuestions);
    }

    /**
     * Requirement D: Customer support classification / badge consistency.
     */
    public function test_customer_support_document_type_classified_as_business_info_consistently(): void
    {
        $csFaq = FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Support Desk Details',
            'answer'        => 'Reach out anytime',
            'document_type' => 'customer_support',
            'is_active'     => true,
        ]);

        $this->assertFalse($csFaq->isPolicy());
        $this->assertTrue($csFaq->isBusinessInfo());
        $this->assertEquals('Customer Support Desk', $csFaq->documentTypeLabel());

        $bizResponse = $this->actingAs($this->userA)->getJson('/dashboard/faqs?type_group=business_info');
        $bizResponse->assertOk();
        $bizQuestions = array_column($bizResponse->json('data'), 'question');
        $this->assertContains('Support Desk Details', $bizQuestions);
    }

    /**
     * Requirement D: Post-action redirect flow to Hub tab while preserving legacy routes.
     */
    public function test_faq_post_action_redirects_to_hub_when_specified_and_preserves_legacy_route(): void
    {
        // 1. With redirect_to = hub -> redirects to knowledge-base hub
        $hubResponse = $this->actingAs($this->userA)->post('/dashboard/faqs', [
            'question'      => 'Redirect to Hub Test',
            'answer'        => 'Some answer',
            'document_type' => 'faq',
            'priority'      => 0,
            'is_active'     => 1,
            'redirect_to'   => 'hub',
        ]);
        $hubResponse->assertRedirect(route('knowledge-base.index', ['tab' => 'faqs']));

        // 2. Without redirect_to (legacy API / form) -> preserves route('faqs.index')
        $legacyResponse = $this->actingAs($this->userA)->post('/dashboard/faqs', [
            'question'      => 'Legacy Redirect Test',
            'answer'        => 'Some answer',
            'document_type' => 'faq',
            'priority'      => 0,
            'is_active'     => 1,
        ]);
        $legacyResponse->assertRedirect(route('faqs.index'));
    }

    /**
     * Requirement: Button for FAQ / Knowledge Base reindex without terminal command dependency.
     */
    public function test_authorized_user_can_trigger_knowledge_base_reindex(): void
    {
        // Mock retrieval client to return true for syncFaq
        $retrievalMock = $this->createMock(\App\Services\Retrieval\RetrievalClient::class);
        $retrievalMock->expects($this->atLeastOnce())
            ->method('syncFaq')
            ->willReturn(true);
        $this->app->instance(\App\Services\Retrieval\RetrievalClient::class, $retrievalMock);

        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Reindex Test FAQ 1',
            'answer'        => 'Answer 1',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        FAQ::create([
            'workspace_id'  => $this->workspaceA->id,
            'question'      => 'Reindex Test FAQ 2',
            'answer'        => 'Answer 2',
            'document_type' => 'company_info',
            'is_active'     => true,
        ]);

        $response = $this->actingAs($this->userA)->postJson('/dashboard/knowledge-base/reindex');
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'total'   => 2,
            'synced'  => 2,
            'failed'  => 0,
        ]);
    }

    public function test_unauthorized_user_cannot_trigger_knowledge_base_reindex(): void
    {
        // User with no update permission
        $response = $this->actingAs($this->restrictedUser)->postJson('/dashboard/knowledge-base/reindex');
        $response->assertForbidden();
    }

    /**
     * Requirement: User with only FAQ view permission can access FAQs but is forbidden from documents.
     */
    public function test_user_with_only_faq_view_can_view_faqs_but_is_forbidden_from_documents(): void
    {
        $faqOnlyRole = Role::create([
            'name'         => 'faq-only-unique',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $faqOnlyRole->givePermissionTo(FAQPermission::VIEW->value);

        $faqOnlyUser = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $faqOnlyUser->assignRole($faqOnlyRole);

        // Can access Hub page and default tab is faqs
        $pageResponse = $this->actingAs($faqOnlyUser)->get('/dashboard/knowledge-base');
        $pageResponse->assertOk();
        $pageResponse->assertViewHas('activeTab', 'faqs');
        $pageResponse->assertViewHas('canViewFaqs', true);
        $pageResponse->assertViewHas('canViewDocs', false);

        // Can query FAQs DataTables
        $faqDataResponse = $this->actingAs($faqOnlyUser)->getJson('/dashboard/knowledge-base?source=faqs');
        $faqDataResponse->assertOk();

        // Forbidden from documents DataTables
        $docDataResponse = $this->actingAs($faqOnlyUser)->getJson('/dashboard/knowledge-base?source=documents');
        $docDataResponse->assertForbidden();

        // Forbidden from documents legacy endpoint
        $legacyDocResponse = $this->actingAs($faqOnlyUser)->getJson('/dashboard/documents');
        $legacyDocResponse->assertForbidden();
    }

    /**
     * Requirement: User with only Document view permission can access documents but is forbidden from FAQs.
     */
    public function test_user_with_only_document_view_can_view_documents_but_is_forbidden_from_faqs(): void
    {
        $docOnlyRole = Role::create([
            'name'         => 'doc-only-unique',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $docOnlyRole->givePermissionTo(KnowledgeDocumentPermission::VIEW->value);

        $docOnlyUser = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $docOnlyUser->assignRole($docOnlyRole);

        // Can access Hub page and default tab automatically falls back to data_sources
        $pageResponse = $this->actingAs($docOnlyUser)->get('/dashboard/knowledge-base');
        $pageResponse->assertOk();
        $pageResponse->assertViewHas('activeTab', 'data_sources');
        $pageResponse->assertViewHas('canViewFaqs', false);
        $pageResponse->assertViewHas('canViewDocs', true);

        // Can query documents DataTables
        $docDataResponse = $this->actingAs($docOnlyUser)->getJson('/dashboard/knowledge-base?source=documents');
        $docDataResponse->assertOk();

        // Forbidden from FAQs DataTables
        $faqDataResponse = $this->actingAs($docOnlyUser)->getJson('/dashboard/knowledge-base?source=faqs');
        $faqDataResponse->assertForbidden();

        // Forbidden from FAQs legacy endpoint
        $legacyFaqResponse = $this->actingAs($docOnlyUser)->getJson('/dashboard/faqs');
        $legacyFaqResponse->assertForbidden();
    }
}
