<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\CustomerSupportAgent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatSimulatorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Main Workspace', 'slug' => 'main']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->admin->assignRole('superadmin');
    }

    public function test_simulator_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard/simulator');

        $response->assertOk()
            ->assertViewIs('admin.simulator')
            ->assertSee('Chat Simulator', false);
    }

    public function test_simulator_send_processes_message_with_ai_agent_and_crm_extraction(): void
    {
        CustomerSupportAgent::fake([
            'Our standard delivery time is 3-5 business days.',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/dashboard/simulator/send', [
            'message' => 'Hello my email is john@example.com and phone is +1-555-123-4567. How long is delivery?',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'query'   => 'Hello my email is john@example.com and phone is +1-555-123-4567. How long is delivery?',
                'reply'   => 'Our standard delivery time is 3-5 business days.',
            ])
            ->assertJsonStructure([
                'success',
                'query',
                'reply',
                'answered',
                'confidence',
                'match_type',
                'pipeline_diagnostics' => [
                    'total_time_ms',
                    'crm_extracted' => [
                        'has_data',
                        'db_saved',
                        'emails',
                        'phones',
                    ],
                    'python_service',
                    'typesense',
                    'scores',
                ],
            ]);

        // Verify CRM contact was created from entity extraction
        $this->assertDatabaseHas('crm_contacts', [
            'workspace_id' => $this->workspace->id,
        ]);
        $this->assertDatabaseHas('crm_contact_emails', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_simulator_send_validates_required_message(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/dashboard/simulator/send', [
            'message' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_simulator_returns_complete_turn_decision_trace_without_secrets(): void
    {
        CustomerSupportAgent::fake([
            'রিটার্ন পলিসি অনুযায়ী ৭ দিনের মধ্যে পণ্য ফেরত দেওয়া যাবে।',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/dashboard/simulator/send', [
            'message' => 'রিটার্ন কত দিনে করতে হবে?',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'query',
                'reply',
                'decision_trace' => [
                    'query',
                    'route',
                    'route_confidence',
                    'memory_decision',
                    'contextual_signal',
                    'retrieval_summary' => [
                        'hits_count',
                        'top_score',
                        'top_doc_type',
                        'top_question',
                    ],
                    'answerability_status',
                    'answerability_score',
                    'grounded_hit_count',
                    'llm_generation' => [
                        'provider',
                        'model',
                        'status',
                    ],
                    'latency_breakdown' => [
                        'router_ms',
                        'retrieval_ms',
                        'llm_ms',
                        'total_ms',
                    ],
                ],
            ]);

        // Security check: Verify no raw system prompt or API keys leaked
        $json = $response->json();
        $rawJson = json_encode($json);
        $this->assertStringNotContainsString('api_key', $rawJson);
        $this->assertStringNotContainsString('sk-', $rawJson);
        $this->assertStringNotContainsString('You are an expert E-Commerce', $rawJson);
    }
}
