<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Analytics\AnalyticsClient;
use App\Services\Memory\ConversationMemoryClient;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_is_accessible_without_authentication(): void
    {
        $mockRetrieval = Mockery::mock(RetrievalClient::class);
        $mockRetrieval->shouldReceive('health')->andReturn(['ok' => true, 'latency_ms' => 5.0, 'error' => null]);
        $this->app->instance(RetrievalClient::class, $mockRetrieval);

        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('health')->andReturn(['healthy' => true, 'latency_ms' => 6.0, 'error' => null]);
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $mockMemory = Mockery::mock(ConversationMemoryClient::class);
        $mockMemory->shouldReceive('healthCheck')->andReturn(true);
        $this->app->instance(ConversationMemoryClient::class, $mockMemory);

        $response = $this->get('/health');

        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'app' => ['env', 'debug', 'version'],
            'checks' => ['database', 'queue', 'retrieval', 'analytics', 'memory', 'cache'],
        ]);
    }

    public function test_health_check_returns_healthy_when_all_services_are_up(): void
    {
        $mockRetrieval = Mockery::mock(RetrievalClient::class);
        $mockRetrieval->shouldReceive('health')->once()->andReturn(['ok' => true, 'latency_ms' => 4.2, 'error' => null]);
        $this->app->instance(RetrievalClient::class, $mockRetrieval);

        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('health')->once()->andReturn(['healthy' => true, 'latency_ms' => 3.8, 'error' => null]);
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $mockMemory = Mockery::mock(ConversationMemoryClient::class);
        $mockMemory->shouldReceive('healthCheck')->once()->andReturn(true);
        $this->app->instance(ConversationMemoryClient::class, $mockMemory);

        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
            'checks' => [
                'database'  => ['healthy' => true],
                'queue'     => ['healthy' => true],
                'retrieval' => ['healthy' => true],
                'analytics' => ['healthy' => true],
                'memory'    => ['healthy' => true],
                'cache'     => ['healthy' => true],
            ],
        ]);
    }

    public function test_health_check_returns_degraded_when_analytics_service_is_down(): void
    {
        $mockRetrieval = Mockery::mock(RetrievalClient::class);
        $mockRetrieval->shouldReceive('health')->once()->andReturn(['ok' => true, 'latency_ms' => 5.0, 'error' => null]);
        $this->app->instance(RetrievalClient::class, $mockRetrieval);

        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('health')->once()->andReturn(['healthy' => false, 'latency_ms' => 12.0, 'error' => 'Connection refused']);
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $mockMemory = Mockery::mock(ConversationMemoryClient::class);
        $mockMemory->shouldReceive('healthCheck')->once()->andReturn(true);
        $this->app->instance(ConversationMemoryClient::class, $mockMemory);

        $response = $this->get('/health');

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'degraded',
            'checks' => [
                'analytics' => [
                    'healthy' => false,
                    'error'   => 'Connection refused',
                ],
            ],
        ]);
    }

    public function test_health_check_returns_degraded_when_memory_service_is_down(): void
    {
        $mockRetrieval = Mockery::mock(RetrievalClient::class);
        $mockRetrieval->shouldReceive('health')->once()->andReturn(['ok' => true, 'latency_ms' => 5.0, 'error' => null]);
        $this->app->instance(RetrievalClient::class, $mockRetrieval);

        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('health')->once()->andReturn(['healthy' => true, 'latency_ms' => 4.0, 'error' => null]);
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $mockMemory = Mockery::mock(ConversationMemoryClient::class);
        $mockMemory->shouldReceive('healthCheck')->once()->andReturn(false);
        $this->app->instance(ConversationMemoryClient::class, $mockMemory);

        $response = $this->get('/health');

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'degraded',
            'checks' => [
                'memory' => [
                    'healthy' => false,
                ],
            ],
        ]);
    }
}
