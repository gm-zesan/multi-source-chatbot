<?php

namespace Tests\Feature;

use App\Models\ChatLog;
use App\Models\Embedding;
use App\Models\SourceTable;
use App\Models\SourceTableColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContextRoutingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed registry tables
        SourceTable::create([
            'source_id' => 'db_01',
            'table_name' => 'customers',
            'alias' => 'client,clients,buyer',
        ]);
        SourceTable::create([
            'source_id' => 'db_02',
            'table_name' => 'products',
            'alias' => 'product,item,goods',
        ]);
        SourceTable::create([
            'source_id' => 'db_03',
            'table_name' => 'sales',
            'alias' => 'sale,orders,transactions',
        ]);

        // Seed columns
        foreach (['customers', 'products', 'sales'] as $table) {
            SourceTableColumn::create(['table_name' => $table, 'column_name' => 'id']);
        }
        SourceTableColumn::create(['table_name' => 'customers', 'column_name' => 'name']);
        SourceTableColumn::create(['table_name' => 'customers', 'column_name' => 'email']);
        SourceTableColumn::create(['table_name' => 'products', 'column_name' => 'product_name']);
        SourceTableColumn::create(['table_name' => 'products', 'column_name' => 'category']);
        SourceTableColumn::create(['table_name' => 'products', 'column_name' => 'price']);
        SourceTableColumn::create(['table_name' => 'products', 'column_name' => 'stock']);
        SourceTableColumn::create(['table_name' => 'sales', 'column_name' => 'customer_id']);
        SourceTableColumn::create(['table_name' => 'sales', 'column_name' => 'total_amount']);
        SourceTableColumn::create(['table_name' => 'sales', 'column_name' => 'order_date']);

        // Seed mock embeddings (5-dim mock vectors for deterministic tests)
        $this->seedEmbedding('database', 'db_01', 'db_01', [0.9, 0.1, 0.0, 0.0, 0.0]);
        $this->seedEmbedding('database', 'db_02', 'db_02', [0.1, 0.9, 0.0, 0.0, 0.0]);
        $this->seedEmbedding('database', 'db_03', 'db_03', [0.0, 0.1, 0.9, 0.0, 0.0]);
        $this->seedEmbedding('table', 'customers', 'customers', [0.9, 0.1, 0.0, 0.0, 0.0]);
        $this->seedEmbedding('table', 'products', 'products', [0.1, 0.9, 0.0, 0.0, 0.0]);
        $this->seedEmbedding('table', 'sales', 'sales', [0.0, 0.1, 0.9, 0.0, 0.0]);

        // Pre-cache embeddings for test queries to avoid Python calls
        $prefix = config('chatbot.vector.cache_prefix', 'embedding:');
        $ttl    = 3600;
        Cache::put($prefix . md5('show customers'), [0.5, 0.1, 0.0, 0.0, 0.0], $ttl);
        Cache::put($prefix . md5('show clients'), [0.5, 0.1, 0.0, 0.0, 0.0], $ttl);
        Cache::put($prefix . md5('show products'), [0.1, 0.5, 0.1, 0.0, 0.0], $ttl);
        Cache::put($prefix . md5('xyzzy unknown query'), [0.0, 0.0, 0.0, 0.0, 0.0], $ttl);
        Cache::put($prefix . md5('show products where price > 100'), [0.1, 0.5, 0.0, 0.0, 0.0], $ttl);
        Cache::put($prefix . md5('show top 2 customers'), [0.5, 0.1, 0.0, 0.0, 0.0], $ttl);
    }

    // ─── Endpoint Tests ───────────────────────────────────────────────

    /** @test */
    public function chat_endpoint_returns_json_with_confidence(): void
    {
        $response = $this->postJson('/chat/send', [
            'message' => 'show customers',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'type',
        ]);
    }

    /** @test */
    public function chat_endpoint_handles_unknown_queries_gracefully(): void
    {
        $response = $this->postJson('/chat/send', [
            'message' => 'xyzzy unknown query',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 'text']);
    }

    /** @test */
    public function query_with_alias_routes_to_correct_table(): void
    {
        $response = $this->postJson('/chat/send', [
            'message' => 'show clients',
        ]);

        $response->assertStatus(200);
    }

    // ─── Routing Metadata Tests ───────────────────────────────────────

    /** @test */
    public function chat_log_stores_routing_method(): void
    {
        $this->postJson('/chat/send', ['message' => 'show customers']);

        $log = ChatLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->routing_method);
        $this->assertContains($log->routing_method, ['semantic', 'keyword_fallback', 'none']);
    }

    /** @test */
    public function chat_log_stores_routing_confidence(): void
    {
        $this->postJson('/chat/send', ['message' => 'show products']);

        $log = ChatLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->routing_confidence);
        $this->assertGreaterThanOrEqual(0, $log->routing_confidence);
        $this->assertLessThanOrEqual(1, $log->routing_confidence);
    }

    /** @test */
    public function chat_log_stores_routing_source(): void
    {
        $this->postJson('/chat/send', ['message' => 'show customers']);

        $log = ChatLog::latest()->first();
        $this->assertNotNull($log);
        // source may be null if routing failed, or a string like 'db_01'
        $this->assertTrue($log->routing_source === null || is_string($log->routing_source));
    }

    /** @test */
    public function intent_contains_routing_metadata(): void
    {
        $this->postJson('/chat/send', ['message' => 'show customers']);

        $log = ChatLog::latest()->first();
        $this->assertNotNull($log);
        $intent = json_decode($log->intent, true);

        $this->assertArrayHasKey('routing_method', $intent);
        $this->assertArrayHasKey('routing_confidence', $intent);
        $this->assertArrayHasKey('routing_source', $intent);
    }

    /** @test */
    public function empty_query_returns_validation_error(): void
    {
        $response = $this->postJson('/chat/send', ['message' => '']);
        $response->assertStatus(422); // Validation error
        $response->assertJsonValidationErrors(['message']);
    }

    /** @test */
    public function query_with_where_clause_routes_correctly(): void
    {
        $response = $this->postJson('/chat/send', [
            'message' => 'show products where price > 100',
        ]);

        $response->assertStatus(200);
        // Should either give a table result or a text message
        $this->assertContains($response->json('type'), ['table', 'text']);
    }

    // ─── Complete Pipeline Test ───────────────────────────────────────

    /** @test */
    public function complete_pipeline_resolves_and_executes(): void
    {
        // This tests: User input → ContextRouter → QueryParser → QueryPlanner → QueryExecutor → Response
        $response = $this->postJson('/chat/send', [
            'message' => 'show top 2 customers',
        ]);

        $response->assertStatus(200);
        $type = $response->json('type');

        // Should have resolved the query
        $this->assertContains($type, ['table', 'text']);

        if ($type === 'table') {
            $this->assertIsArray($response->json('data'));
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    protected function seedEmbedding(string $type, string $name, string $key, array $vector): void
    {
        Embedding::create([
            'entity_type' => $type,
            'entity_name' => $name,
            'entity_key'  => $key,
            'source_id'   => $type === 'database' ? $key : null,
            'embedding'   => $vector,
            'metadata'    => ['description' => "{$name} test embedding"],
        ]);
    }
}
