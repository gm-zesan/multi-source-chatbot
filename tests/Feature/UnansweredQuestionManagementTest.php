<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permissions\FAQPermission;
use App\Enums\Permissions\UnansweredQuestionPermission;
use App\Enums\RoleEnum;
use App\Models\FAQ;
use App\Models\Role;
use App\Models\UnansweredQuestion;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UnansweredQuestionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $merchantAdminA;
    protected User $merchantAdminB;
    protected User $superAdmin;
    protected UnansweredQuestion $questionA;
    protected UnansweredQuestion $questionB;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(PermissionSeeder::class);

        $this->workspaceA = Workspace::create(['name' => 'Workspace A', 'slug' => 'workspace-a']);
        $this->workspaceB = Workspace::create(['name' => 'Workspace B', 'slug' => 'workspace-b']);

        // Roles for Workspace A
        $roleA = Role::create([
            'name'         => 'admin-a',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $roleA->givePermissionTo([
            UnansweredQuestionPermission::VIEW->value,
            UnansweredQuestionPermission::UPDATE->value,
            UnansweredQuestionPermission::DELETE->value,
            FAQPermission::CREATE->value,
            FAQPermission::VIEW->value,
            FAQPermission::UPDATE->value,
        ]);

        // Roles for Workspace B
        $roleB = Role::create([
            'name'         => 'admin-b',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceB->id,
        ]);
        $roleB->givePermissionTo([
            UnansweredQuestionPermission::VIEW->value,
            UnansweredQuestionPermission::UPDATE->value,
            UnansweredQuestionPermission::DELETE->value,
            FAQPermission::CREATE->value,
            FAQPermission::VIEW->value,
            FAQPermission::UPDATE->value,
        ]);

        // Superadmin Role
        $superAdminRole = Role::create([
            'name'       => RoleEnum::SUPERADMIN->value,
            'guard_name' => 'web',
        ]);
        $superAdminRole->givePermissionTo([
            UnansweredQuestionPermission::VIEW->value,
            UnansweredQuestionPermission::UPDATE->value,
            UnansweredQuestionPermission::DELETE->value,
            FAQPermission::CREATE->value,
            FAQPermission::VIEW->value,
            FAQPermission::UPDATE->value,
        ]);

        // Users
        $this->merchantAdminA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->merchantAdminA->assignRole($roleA);

        $this->merchantAdminB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);
        $this->merchantAdminB->assignRole($roleB);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($superAdminRole);

        // Seed Unanswered Questions
        $this->questionA = UnansweredQuestion::create([
            'workspace_id'        => $this->workspaceA->id,
            'original_question'   => 'What is the warranty policy for laptop?',
            'normalized_question' => 'what is the warranty policy for laptop',
            'occurrence_count'    => 5,
            'status'              => 'pending',
        ]);

        $this->questionB = UnansweredQuestion::create([
            'workspace_id'        => $this->workspaceB->id,
            'original_question'   => 'Do you ship to Chittagong?',
            'normalized_question' => 'do you ship to chittagong',
            'occurrence_count'    => 2,
            'status'              => 'pending',
        ]);
    }

    /**
     * Test merchant can list their workspace unanswered questions with strict isolation.
     */
    public function test_merchant_can_list_their_workspace_unanswered_questions_with_isolation(): void
    {
        $response = $this->actingAs($this->merchantAdminA)
            ->get(route('unanswered-questions.index'));

        $response->assertOk();
        $response->assertViewIs('admin.unanswered-questions.index');
        $response->assertViewHas('stats');
    }

    /**
     * Test DataTables endpoint enforces tenant workspace boundary.
     */
    public function test_datatables_endpoint_enforces_tenant_workspace_boundary(): void
    {
        $response = $this->actingAs($this->merchantAdminA)
            ->getJson(route('unanswered-questions.index', ['draw' => 1]));

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($this->questionA->original_question, $data[0]['original_question']);
    }

    /**
     * Test merchant can update status and notes of their unanswered question.
     */
    public function test_merchant_can_update_status_and_notes(): void
    {
        $response = $this->actingAs($this->merchantAdminA)
            ->putJson(route('unanswered-questions.update', $this->questionA), [
                'status' => 'ignored',
                'notes'  => 'Not relevant to current product line',
            ]);

        $response->assertOk();
        $this->questionA->refresh();
        $this->assertSame('ignored', $this->questionA->status);
        $this->assertSame('Not relevant to current product line', $this->questionA->notes);
    }

    /**
     * Test cross-tenant update is strictly blocked with 403.
     */
    public function test_cross_tenant_update_is_strictly_blocked(): void
    {
        $response = $this->actingAs($this->merchantAdminA)
            ->putJson(route('unanswered-questions.update', $this->questionB), [
                'status' => 'resolved',
            ]);

        $response->assertForbidden();
    }

    /**
     * Test merchant can delete their unanswered question.
     */
    public function test_merchant_can_delete_their_unanswered_question(): void
    {
        $response = $this->actingAs($this->merchantAdminA)
            ->deleteJson(route('unanswered-questions.destroy', $this->questionA));

        $response->assertOk();
        $this->assertDatabaseMissing('unanswered_questions', ['id' => $this->questionA->id]);
    }

    /**
     * Test cross-tenant delete is strictly blocked with 403.
     */
    public function test_cross_tenant_delete_is_strictly_blocked(): void
    {
        $response = $this->actingAs($this->merchantAdminA)
            ->deleteJson(route('unanswered-questions.destroy', $this->questionB));

        $response->assertForbidden();
        $this->assertDatabaseHas('unanswered_questions', ['id' => $this->questionB->id]);
    }

    /**
     * Test 1-click convert unanswered question to FAQ creates FAQ and marks question resolved.
     */
    public function test_1_click_convert_to_faq_creates_faq_and_marks_question_resolved(): void
    {
        $response = $this->actingAs($this->merchantAdminA)
            ->postJson(route('unanswered-questions.convert-to-faq', $this->questionA), [
                'question' => 'What is the official laptop warranty policy?',
                'answer'   => 'All laptops come with a 2-year official brand warranty with free replacement within 30 days.',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Verify FAQ was created in Database for Workspace A
        $faq = FAQ::where('workspace_id', $this->workspaceA->id)
            ->where('question', 'What is the official laptop warranty policy?')
            ->first();

        $this->assertNotNull($faq);
        $this->assertSame('All laptops come with a 2-year official brand warranty with free replacement within 30 days.', $faq->answer);

        // Verify UnansweredQuestion status is resolved
        $this->questionA->refresh();
        $this->assertSame('resolved', $this->questionA->status);
        $this->assertStringContainsString("Converted to FAQ #{$faq->id}", $this->questionA->notes);
    }

    /**
     * Test cross-tenant convert to FAQ is strictly blocked with 403.
     */
    public function test_cross_tenant_convert_to_faq_is_strictly_blocked(): void
    {
        $response = $this->actingAs($this->merchantAdminA)
            ->postJson(route('unanswered-questions.convert-to-faq', $this->questionB), [
                'answer' => 'Unauthorized attempt to answer Workspace B question.',
            ]);

        $response->assertForbidden();
    }
}
