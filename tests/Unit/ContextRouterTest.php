<?php

namespace Tests\Unit;

use App\DTO\RoutingResult;
use App\Models\Embedding;
use App\Models\SourceTable;
use App\Services\ContextRouter;
use App\Services\VectorEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextRouterTest extends TestCase
{
    use RefreshDatabase;

    protected ContextRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed registry data so keyword fallback works
        SourceTable::create([
            'source_id'  => 'db_01',
            'table_name' => 'customers',
            'alias'      => 'client,clients',
        ]);

        // Seed an embedding so getDatabaseCandidates() doesn't fail
        Embedding::create([
            'entity_type' => 'database',
            'entity_name' => 'db_01',
            'entity_key'  => 'db_01',
            'source_id'   => 'db_01',
            'embedding'   => [0.1, 0.2, 0.3],
            'metadata'    => ['description' => 'Customer database'],
        ]);

        Embedding::create([
            'entity_type' => 'table',
            'entity_name' => 'customers',
            'entity_key'  => 'customers',
            'source_id'   => 'db_01',
            'embedding'   => [0.4, 0.5, 0.6],
            'metadata'    => ['columns' => ['id', 'name', 'email']],
        ]);

        // Mock VectorEmbeddingService to avoid Python calls
        $mockEmbedding = $this->createMock(VectorEmbeddingService::class);
        $mockEmbedding->method('generateEmbedding')
            ->willReturn([0.1, 0.2, 0.3]);
        $mockEmbedding->method('findBestMatch')
            ->willReturn(null); // Force keyword fallback
        $mockEmbedding->method('cosineSimilarity')
            ->willReturn(0.5);

        $this->router = new ContextRouter($mockEmbedding);
    }

    /** @test */
    public function it_detects_query_type_from_keywords(): void
    {
        $this->assertEquals('count', $this->router->detectQueryType('count customers'));
        $this->assertEquals('count', $this->router->detectQueryType('how many products'));
        $this->assertEquals('aggregate', $this->router->detectQueryType('total sales'));
        $this->assertEquals('aggregate', $this->router->detectQueryType('average price'));
        $this->assertEquals('filtered_search', $this->router->detectQueryType('show where price > 100'));
        $this->assertEquals('select', $this->router->detectQueryType('show all customers'));
    }

    /** @test */
    public function it_provides_fallback_message_when_no_result(): void
    {
        $message = $this->router->getFallbackMessage();
        $this->assertEquals('No query has been processed yet.', $message);
    }

    /** @test */
    public function it_tracks_last_confidence(): void
    {
        $this->router->route('show customers');
        $this->assertIsFloat($this->router->getConfidence());
    }

    /** @test */
    public function it_includes_routing_path_in_metadata(): void
    {
        $result = $this->router->route('show customers');
        $this->assertArrayHasKey('routing_path', $result->metadata);
    }

    /** @test */
    public function it_returns_routing_result_with_matched_scores(): void
    {
        $result = $this->router->route('show customers');
        $this->assertIsFloat($result->matchedDbScore);
        $this->assertIsFloat($result->matchedTableScore);
    }
}
