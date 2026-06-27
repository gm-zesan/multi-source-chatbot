<?php

namespace Tests\Unit;

use App\Services\VectorEmbeddingService;
use Tests\TestCase;

class VectorEmbeddingServiceTest extends TestCase
{
    protected VectorEmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VectorEmbeddingService();
    }

    /** @test */
    public function it_computes_cosine_similarity_of_identical_vectors(): void
    {
        $vec = [1.0, 0.0, 0.0];
        $similarity = $this->service->cosineSimilarity($vec, $vec);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    /** @test */
    public function it_computes_cosine_similarity_of_orthogonal_vectors(): void
    {
        $vec1 = [1.0, 0.0, 0.0];
        $vec2 = [0.0, 1.0, 0.0];
        $similarity = $this->service->cosineSimilarity($vec1, $vec2);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    /** @test */
    public function it_computes_cosine_similarity_of_opposite_vectors(): void
    {
        $vec1 = [1.0, 0.0, 0.0];
        $vec2 = [-1.0, 0.0, 0.0];
        $similarity = $this->service->cosineSimilarity($vec1, $vec2);

        $this->assertEqualsWithDelta(-1.0, $similarity, 0.0001);
    }

    /** @test */
    public function it_returns_zero_for_empty_vectors(): void
    {
        $this->assertEquals(0.0, $this->service->cosineSimilarity([], []));
        $this->assertEquals(0.0, $this->service->cosineSimilarity([1.0], []));
        $this->assertEquals(0.0, $this->service->cosineSimilarity([], [1.0]));
    }

    /** @test */
    public function it_returns_zero_for_mismatched_vector_lengths(): void
    {
        $this->assertEquals(0.0, $this->service->cosineSimilarity([1.0, 0.0], [1.0]));
    }

    /** @test */
    public function it_computes_cosine_similarity_partial_match(): void
    {
        $vec1 = [1.0, 2.0, 3.0];
        $vec2 = [1.0, 2.0, 0.0];

        // dot = 1+4+0 = 5
        // |vec1| = sqrt(1+4+9) = sqrt(14) ≈ 3.7417
        // |vec2| = sqrt(1+4+0) = sqrt(5) ≈ 2.2361
        // sim = 5 / (3.7417 * 2.2361) = 5 / 8.366 ≈ 0.5976
        $similarity = $this->service->cosineSimilarity($vec1, $vec2);

        $this->assertEqualsWithDelta(0.5976, $similarity, 0.01);
    }

    /** @test */
    public function it_finds_best_match_with_threshold(): void
    {
        $input = 'show me all customers';
        $candidates = [
            ['key' => 'customers', 'text' => 'customers table', 'vector' => [1.0, 0.0, 0.0]],
            ['key' => 'products',  'text' => 'products table',  'vector' => [0.0, 1.0, 0.0]],
        ];

        // Mock generateEmbedding to return a vector close to "customers"
        $service = $this->getMockBuilder(VectorEmbeddingService::class)
            ->onlyMethods(['generateEmbedding'])
            ->getMock();

        $service->expects($this->once())
            ->method('generateEmbedding')
            ->with($input)
            ->willReturn([0.9, 0.1, 0.0]);

        $result = $service->findBestMatch($input, $candidates, 0.5);

        $this->assertNotNull($result);
        $this->assertEquals('customers', $result['key']);
        $this->assertGreaterThan(0.5, $result['similarity']);
    }

    /** @test */
    public function it_returns_null_when_no_match_meets_threshold(): void
    {
        $input = 'something completely unrelated';
        $candidates = [
            ['key' => 'customers', 'text' => 'customers', 'vector' => [1.0, 0.0]],
            ['key' => 'products',  'text' => 'products',  'vector' => [0.0, 1.0]],
        ];

        $service = $this->getMockBuilder(VectorEmbeddingService::class)
            ->onlyMethods(['generateEmbedding'])
            ->getMock();

        $service->expects($this->once())
            ->method('generateEmbedding')
            ->willReturn([-1.0, -1.0]);

        $result = $service->findBestMatch($input, $candidates, 0.9);

        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_top_n_matches_in_order(): void
    {
        $input = 'find products';
        $candidates = [
            ['key' => 'sales',     'text' => 'sales',     'vector' => [0.1, 0.9]],
            ['key' => 'customers', 'text' => 'customers', 'vector' => [0.2, 0.8]],
            ['key' => 'products',  'text' => 'products',  'vector' => [0.9, 0.1]],
        ];

        $service = $this->getMockBuilder(VectorEmbeddingService::class)
            ->onlyMethods(['generateEmbedding'])
            ->getMock();

        $service->expects($this->once())
            ->method('generateEmbedding')
            ->willReturn([0.85, 0.15]);

        $results = $service->findTopN($input, $candidates, 2);

        $this->assertCount(2, $results);
        $this->assertEquals('products', $results[0]['key']);
        $this->assertGreaterThanOrEqual($results[1]['similarity'], $results[0]['similarity']);
    }
}
