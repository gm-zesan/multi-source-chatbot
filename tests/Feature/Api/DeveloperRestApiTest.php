<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\RoleEnum;
use App\Models\ApiKey;
use App\Models\FAQ;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspace\WorkspaceProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeveloperRestApiTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $provisioning = app(WorkspaceProvisioningService::class);
        $this->user = $provisioning->provisionNewTenant('Test Shop', [
            'name'     => 'Merchant Admin',
            'email'    => 'merchant@testshop.com',
            'password' => bcrypt('password123'),
        ]);

        $this->workspace = $this->user->workspace;
    }

    public function test_api_key_generation_and_storage(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('api-keys.store'), [
            'name' => 'Mobile App Key',
        ]);

        $response->assertSessionHas('new_plain_token');
        $response->assertSessionHas('success');

        $apiKey = ApiKey::where('workspace_id', $this->workspace->id)->first();
        $this->assertNotNull($apiKey);
        $this->assertSame('Mobile App Key', $apiKey->name);
        $this->assertTrue($apiKey->isValid());
    }

    public function test_unauthenticated_api_request_fails_with_401(): void
    {
        $response = $this->postJson('/api/v1/chat', [
            'message' => 'Hello AI',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error'   => 'unauthorized',
        ]);
    }

    public function test_invalid_api_key_fails_with_401(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ea_live_invalid_token_12345678901234567890',
        ])->postJson('/api/v1/chat', [
            'message' => 'Hello AI',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error'   => 'unauthorized',
        ]);
    }

    public function test_valid_api_key_can_send_chat_and_receive_response(): void
    {
        $result = ApiKey::generate($this->workspace, $this->user, 'Backend Server');
        $plainToken = $result['plain_token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->postJson('/api/v1/chat', [
            'message'       => 'What are your operating hours?',
            'customer_name' => 'John Customer',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'conversation_id',
                'reply',
                'channel',
                'workspace' => ['id', 'name'],
                'timestamp',
            ],
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.reply'));

        // Verify API key usage was tracked
        $key = $result['model']->fresh();
        $this->assertNotNull($key->last_used_at);
    }

    public function test_workspace_and_knowledge_endpoints(): void
    {
        $result = ApiKey::generate($this->workspace, $this->user, 'Sync Client');
        $plainToken = $result['plain_token'];

        // 1. Workspace Info
        $wsResponse = $this->withHeaders([
            'X-API-Key' => $plainToken,
        ])->getJson('/api/v1/workspace');

        $wsResponse->assertStatus(200);
        $wsResponse->assertJsonPath('data.workspace.name', 'Test Shop');
        $wsResponse->assertJsonPath('data.subscription.status', 'active');

        // 2. Knowledge FAQs
        $faqResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->getJson('/api/v1/knowledge/faqs');

        $faqResponse->assertStatus(200);
        $this->assertTrue($faqResponse->json('success'));
        $this->assertGreaterThanOrEqual(1, count($faqResponse->json('data')));
    }

    public function test_revoked_api_key_is_rejected(): void
    {
        $result = ApiKey::generate($this->workspace, $this->user, 'Temporary Key');
        $plainToken = $result['plain_token'];

        // Revoke the key
        $result['model']->update(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->postJson('/api/v1/chat', [
            'message' => 'Test message',
        ]);

        $response->assertStatus(401);
    }
}
