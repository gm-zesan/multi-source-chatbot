<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionIntentMapping;
use App\Models\ConceptPhrasePattern;
use App\Models\LexiconDomainEntry;
use App\Models\PolicyIntentMapping;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LexiconManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Tech Corp', 'slug' => 'tech-corp']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name'         => 'Test Admin',
            'email'        => 'admin@example.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->admin->assignRole('superadmin');
    }

    public function test_superadmin_can_view_lexicon_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('lexicons.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.lexicons.index');
        $response->assertSee('Lexicon & Dynamic Vocabulary', false);
    }

    public function test_can_create_update_and_delete_domain_synonym_entry(): void
    {
        Queue::fake();

        // 1. Store
        $postData = [
            'workspace_id' => 0,
            'concept_key'  => 'TEST_CONCEPT',
            'pattern'      => 'test pattern',
            'expansion'    => 'test expansion keyword',
            'language'     => 'bn',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('lexicons.domain-entries.store'), $postData);

        $response->assertRedirect();
        $this->assertDatabaseHas('lexicon_domain_entries', [
            'concept_key' => 'TEST_CONCEPT',
            'pattern'     => 'test pattern',
            'workspace_id' => 0,
        ]);

        $entry = LexiconDomainEntry::where('concept_key', 'TEST_CONCEPT')->first();
        $this->assertNotNull($entry);

        // 2. Update
        $updateResponse = $this->actingAs($this->admin)
            ->put(route('lexicons.domain-entries.update', $entry->id), [
                'concept_key' => 'TEST_CONCEPT',
                'pattern'     => 'test pattern updated',
                'expansion'   => 'new expansion',
                'language'    => 'en',
                'status'      => 'ACTIVE',
            ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('lexicon_domain_entries', [
            'id'      => $entry->id,
            'pattern' => 'test pattern updated',
        ]);

        // 3. Delete
        $deleteResponse = $this->actingAs($this->admin)
            ->delete(route('lexicons.domain-entries.destroy', $entry->id));

        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('lexicon_domain_entries', ['id' => $entry->id]);
    }

    public function test_can_create_and_delete_concept_pattern(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('lexicons.concept-patterns.store'), [
                'workspace_id'    => 0,
                'concept_key'     => 'WARRANTY_CLAIM',
                'pattern_type'    => 'POSITIVE',
                'phrase'          => 'warranty claim kivabe korbo',
                'target_doc_type' => 'policy_warranty',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('concept_phrase_patterns', [
            'concept_key'  => 'WARRANTY_CLAIM',
            'pattern_type' => 'POSITIVE',
        ]);

        $pattern = ConceptPhrasePattern::where('concept_key', 'WARRANTY_CLAIM')->first();
        $this->actingAs($this->admin)
            ->delete(route('lexicons.concept-patterns.destroy', $pattern->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('concept_phrase_patterns', ['id' => $pattern->id]);
    }

    public function test_can_create_and_delete_action_intent_mapping(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('lexicons.action-mappings.store'), [
                'workspace_id'   => 0,
                'intent_name'    => 'order_track',
                'action_keyword' => 'kothay ache',
                'target_phrase'  => 'how do i track my order?',
                'penalty_phrase' => 'how do i cancel my order?',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('action_intent_mappings', [
            'intent_name'    => 'order_track',
            'action_keyword' => 'kothay ache',
        ]);

        $mapping = ActionIntentMapping::where('intent_name', 'order_track')->first();
        $this->actingAs($this->admin)
            ->delete(route('lexicons.action-mappings.destroy', $mapping->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('action_intent_mappings', ['id' => $mapping->id]);
    }

    public function test_can_create_and_delete_policy_intent_mapping(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('lexicons.policy-mappings.store'), [
                'workspace_id'    => 0,
                'policy_name'     => 'replacement_policy',
                'cue_phrase'      => 'replace kora jabe',
                'target_doc_type' => 'policy_replacement',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('policy_intent_mappings', [
            'policy_name' => 'replacement_policy',
            'cue_phrase'  => 'replace kora jabe',
        ]);

        $policy = PolicyIntentMapping::where('policy_name', 'replacement_policy')->first();
        $this->actingAs($this->admin)
            ->delete(route('lexicons.policy-mappings.destroy', $policy->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('policy_intent_mappings', ['id' => $policy->id]);
    }

    public function test_manual_sync_endpoint_calls_retrieval_client(): void
    {
        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('reloadLexicon')
            ->once()
            ->with(0)
            ->andReturn([
                'ok'                => true,
                'workspace_id'      => 0,
                'snapshot_version'  => 42,
                'global_version'    => 42,
                'workspace_version' => 0,
            ]);

        $this->app->instance(RetrievalClient::class, $mockClient);

        $response = $this->actingAs($this->admin)
            ->postJson(route('lexicons.sync'), ['workspace_id' => 0]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok'               => true,
            'snapshot_version' => 42,
        ]);
    }
}
