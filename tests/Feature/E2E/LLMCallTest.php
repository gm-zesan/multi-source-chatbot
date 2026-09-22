<?php

namespace Tests\Feature\E2E;

use App\Models\Conversation;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Services\AI\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LLMCallTest extends TestCase
{
    use RefreshDatabase;

    public function test_llm_api_calls()
    {
        Http::fake([
            '*' => Http::response('{"route":"chat","confidence":0.99,"security_status":"allowed","content":"fake response"}', 200)
        ]);

        $workspace = Workspace::create(['name' => 'Test', 'slug' => 'test-ws']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test',
            'external_id'  => 'test',
            'access_token' => 'test',
            'is_active'    => true,
        ]);
        $conversation = Conversation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'workspace_id' => $workspace->id,
            'channel_account_id' => $account->id,
            'session_id' => 'test-session',
            'contact_id' => 'test-contact',
            'external_user_id' => 'test_user',
            'status' => 'active',
            'last_direction' => 'inbound'
        ]);

        $service = app(CustomerSupportService::class);

        // Turn 1
        $result = $service->handleQuery('Hello', $workspace->id, $conversation);
        
        echo "FINAL ROUTE: " . $result['route'] . "\n";
        echo "FINAL REPLY: " . $result['reply'] . "\n";
        
        $requests = Http::recorded();
        foreach ($requests as $request) {
            echo "URL Called: " . $request[0]->url() . "\n";
        }
        
        $openRouterCalls = collect($requests)->filter(function ($request) {
            return str_contains($request[0]->url(), 'openrouter') || str_contains($request[0]->url(), 'deepseek');
        })->count();

        echo "\nTOTAL LLM CALLS MADE FOR 'Hello': {$openRouterCalls}\n";
        
        $this->assertTrue(true);
    }
}
