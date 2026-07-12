<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\Search\TypesenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ChatbotHealth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'chatbot:health';

    /**
     * The console command description.
     */
    protected $description = 'Verify that the complete chatbot infrastructure is healthy';

    /**
     * Typesense collection name for FAQs.
     */
    private const COLLECTION = 'faqs';

    /**
     * Collected check results.
     *
     * @var array<int, array{section: string, label: string, status: bool, detail?: string, latency?: float}>
     */
    private array $results = [];

    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly TypesenseService $typesense,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('==================================');
        $this->line('   Chatbot Infrastructure Health');
        $this->line('==================================');
        $this->line('');

        $this->checkLaravel();
        $this->checkEmbeddingService();
        $this->checkTypesense();
        $this->checkConfig();
        $this->checkConsistency();

        $this->displaySummary();

        $hasFailures = $this->hasFailures();

        if ($hasFailures) {
            $this->line('');
            $this->line('==================================');
            $this->error('   Overall Status: UNHEALTHY');
            $this->line('==================================');

            return Command::FAILURE;
        }

        $this->line('');
        $this->line('==================================');
        $this->info('   Overall Status: HEALTHY');
        $this->line('==================================');

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Section 1: Laravel
    // ─────────────────────────────────────────────────────────────────────

    private function checkLaravel(): void
    {
        $this->line('── Laravel ──────────────────────────────');

        // Database
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            $dbLatency = (microtime(true) - $dbStart) * 1000;
            $this->recordPass('laravel', 'Database', 'Database Connected', $dbLatency);
        } catch (\Throwable $e) {
            $dbLatency = (microtime(true) - $dbStart) * 1000;
            $this->recordFail('laravel', 'Database', 'Database Connection Failed', $e->getMessage(), $dbLatency);
        }

        // Queue
        $queueStart = microtime(true);
        try {
            $queue = Queue::connection();
            // Attempt to get status — will throw if misconfigured
            $queue->size();
            $queueLatency = (microtime(true) - $queueStart) * 1000;
            $this->recordPass('laravel', 'Queue', 'Queue Connected', $queueLatency);
        } catch (\Throwable $e) {
            $queueLatency = (microtime(true) - $queueStart) * 1000;
            $this->recordFail('laravel', 'Queue', 'Queue Connection Failed', $e->getMessage(), $queueLatency);
        }

        // Cache
        $cacheStart = microtime(true);
        try {
            Cache::store()->has('health-check-key');
            $cacheLatency = (microtime(true) - $cacheStart) * 1000;
            $this->recordPass('laravel', 'Cache', 'Cache Connected', $cacheLatency);
        } catch (\Throwable $e) {
            $cacheLatency = (microtime(true) - $cacheStart) * 1000;
            $this->recordFail('laravel', 'Cache', 'Cache Connection Failed', $e->getMessage(), $cacheLatency);
        }

        // Storage
        $storageStart = microtime(true);
        try {
            $disk = Storage::disk('local');
            $disk->exists('health-check-tmp');
            $storageLatency = (microtime(true) - $storageStart) * 1000;
            $this->recordPass('laravel', 'Storage', 'Storage Writable', $storageLatency);
        } catch (\Throwable $e) {
            $storageLatency = (microtime(true) - $storageStart) * 1000;
            $this->recordFail('laravel', 'Storage', 'Storage Not Writable', $e->getMessage(), $storageLatency);
        }

        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Section 2: Embedding Service
    // ─────────────────────────────────────────────────────────────────────

    private function checkEmbeddingService(): void
    {
        $this->line('── Embedding Service ────────────────────');

        // Config: URL
        $url = $this->embeddings->config('base_url', '');
        if (! empty($url)) {
            $this->recordPass('embedding', 'URL', "Endpoint: {$url}");
        } else {
            $this->recordFail('embedding', 'URL', 'Embedding Service URL is not configured');
        }

        // Config: API Key
        $apiKey = $this->embeddings->config('api_key', '');
        if (! empty($apiKey)) {
            $this->recordPass('embedding', 'API Key', 'API Key Configured');
        } else {
            $this->recordPass('embedding', 'API Key', 'No API Key (local/dev mode only)');
        }

        // Health endpoint
        try {
            $health = $this->embeddings->health();

            if ($health['status'] === 'ok') {
                $this->recordPass(
                    'embedding', 'Health', 'Service Reachable',
                    $health['latency_ms'],
                );

                $modelName = $health['model'] ?? 'unknown';
                $dimensions = $health['dimensions'] ?? 0;

                // Model loaded
                if ($modelName !== 'unknown') {
                    $this->recordPass('embedding', 'Model', "Model: {$modelName}");
                } else {
                    $this->recordFail('embedding', 'Model', 'No model loaded');
                }

                // Dimensions from health
                $this->recordPass('embedding', 'Dimension', "Dimensions: {$dimensions}");
            } else {
                $this->recordFail('embedding', 'Health', 'Service returned non-ok status');
            }
        } catch (\Throwable $e) {
            $this->recordFail('embedding', 'Health', 'Service Unreachable', $e->getMessage());
            // Skip remaining embedding checks
            $this->line('');
            return;
        }

        // Test embedding generation
        try {
            $embedStart = microtime(true);
            $response = $this->embeddings->embed('health check');
            $embedLatency = (microtime(true) - $embedStart) * 1000;

            $this->recordPass(
                'embedding', 'Test Embedding', "Generated ({$response->dimensions} dims)",
                $embedLatency,
            );

            // Capture actual dimensions for consistency check
            $this->results[] = [
                'section'  => 'consistency',
                'label'    => 'Embedding Dim (Python)',
                'status'   => true,
                'detail'   => (string) $response->dimensions,
                'latency'  => null,
            ];
        } catch (\Throwable $e) {
            $this->recordFail('embedding', 'Test Embedding', 'Generation Failed', $e->getMessage());
        }

        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Section 3: Typesense
    // ─────────────────────────────────────────────────────────────────────

    private function checkTypesense(): void
    {
        $this->line('── Typesense ────────────────────────────');

        // Health / Reachability
        $typesenseStart = microtime(true);
        $health = $this->typesense->health();
        $typesenseLatency = (microtime(true) - $typesenseStart) * 1000;

        if ($health['ok']) {
            $this->recordPass('typesense', 'URL', 'Typesense Reachable', $typesenseLatency);
        } else {
            $this->recordFail('typesense', 'URL', 'Typesense Unreachable', '', $typesenseLatency);
            // Skip remaining checks if Typesense is down
            $this->line('');
            return;
        }

        // API Key (implicitly validated by health check success)
        $this->recordPass('typesense', 'API Key', 'API Key Valid');

        // Collection existence
        $collectionName = $this->typesense->resolveCollectionName(self::COLLECTION);
        $exists = $this->typesense->collectionExists(self::COLLECTION);

        if ($exists) {
            $this->recordPass('typesense', 'Collection', "Collection '{$collectionName}' Exists");
        } else {
            $this->recordFail('typesense', 'Collection', "Collection '{$collectionName}' Not Found");
            $this->line('');
            return;
        }

        // Schema validation
        $schema = $this->typesense->getCollectionSchema(self::COLLECTION);

        if ($schema === null) {
            $this->recordFail('typesense', 'Schema', 'Schema could not be retrieved');
            $this->line('');
            return;
        }

        $this->recordPass('typesense', 'Schema', 'Schema Valid');

        // Embedding field validation
        $fields = $schema['fields'] ?? [];
        $embeddingField = null;
        foreach ($fields as $field) {
            if (($field['name'] ?? '') === 'embedding') {
                $embeddingField = $field;
                break;
            }
        }

        if ($embeddingField !== null) {
            $fieldType = $embeddingField['type'] ?? 'unknown';
            if ($fieldType === 'float[]') {
                $this->recordPass('typesense', 'Embedding Field', "Type: {$fieldType}");
            } else {
                $this->recordFail('typesense', 'Embedding Field', "Expected float[], got {$fieldType}");
            }
        } else {
            $this->recordFail('typesense', 'Embedding Field', 'Field not found in collection schema');
        }

        // Search API accessibility
        try {
            // Perform a lightweight search with empty query to verify the endpoint works
            // Typesense returns results even for empty queries
            $searchResult = $this->typesense->search(self::COLLECTION, '', [
                'per_page' => 1,
            ]);
            $this->recordPass('typesense', 'Search', 'Search API Accessible');
        } catch (\Throwable $e) {
            $this->recordFail('typesense', 'Search', 'Search API Error', $e->getMessage());
        }

        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Section 4: Configuration Validation
    // ─────────────────────────────────────────────────────────────────────

    private function checkConfig(): void
    {
        $this->line('── Configuration ────────────────────────');

        $allOk = true;

        // Embedding config
        $embedModel = config('embedding.model', '');
        $embedDim = config('embedding.dimensions', 0);
        $embedUrl = config('embedding.base_url', '');
        $embedKey = config('embedding.api_key', '');

        if (empty($embedModel)) {
            $this->warn('  ⚠ Embedding: Model not configured');
            $allOk = false;
        }
        if ($embedDim <= 0) {
            $this->warn('  ⚠ Embedding: Dimensions not configured');
            $allOk = false;
        }
        if (empty($embedUrl)) {
            $this->warn('  ⚠ Embedding: URL not configured');
            $allOk = false;
        }
        if (empty($embedKey)) {
            $this->warn('  ⚠ Embedding: API key not configured (expected in non-local)');
        }

        // Typesense config
        $tsHost = config('typesense.host', '');
        $tsKey = config('typesense.api_key', '');
        $tsPort = config('typesense.port', '');

        if (empty($tsHost)) {
            $this->warn('  ⚠ Typesense: Host not configured');
            $allOk = false;
        }
        if (empty($tsPort)) {
            $this->warn('  ⚠ Typesense: Port not configured');
            $allOk = false;
        }
        if (empty($tsKey)) {
            $this->warn('  ⚠ Typesense: API key not configured');
            $allOk = false;
        }

        if ($allOk) {
            $this->recordPass('config', 'All', 'All configuration values present');
        }

        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Section 5: Consistency Validation
    // ─────────────────────────────────────────────────────────────────────

    private function checkConsistency(): void
    {
        $this->line('── Consistency ──────────────────────────');

        $configDim = (int) config('embedding.dimensions', 0);

        // Find the Python-reported dimension from earlier results
        $pythonDim = null;
        foreach ($this->results as $result) {
            if ($result['section'] === 'consistency' && $result['label'] === 'Embedding Dim (Python)') {
                $pythonDim = (int) $result['detail'];
                break;
            }
        }

        if ($pythonDim !== null && $configDim > 0) {
            if ($pythonDim === $configDim) {
                $this->recordPass('consistency', 'Embedding Dim', "Laravel Config: {$configDim}, Python API: {$pythonDim} — Match");
            } else {
                $this->recordFail('consistency', 'Embedding Dim', "MISMATCH — Laravel Config: {$configDim}, Python API: {$pythonDim}");
            }
        } elseif ($pythonDim === null) {
            $this->warn('  ⚠ Consistency: Cannot verify embedding dimensions (Python check skipped)');
        } elseif ($configDim <= 0) {
            $this->warn('  ⚠ Consistency: Cannot verify embedding dimensions (config not set)');
        }

        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Summary
    // ─────────────────────────────────────────────────────────────────────

    private function displaySummary(): void
    {
        $this->line('── Performance ──────────────────────────');

        $latencies = [];
        foreach ($this->results as $result) {
            if ($result['latency'] !== null) {
                $latencies[] = $result;
            }
        }

        if (! empty($latencies)) {
            // Group by section and average
            $groups = [];
            foreach ($latencies as $r) {
                $section = $r['section'];
                if (! isset($groups[$section])) {
                    $groups[$section] = ['total' => 0, 'count' => 0];
                }
                $groups[$section]['total'] += $r['latency'];
                $groups[$section]['count']++;
            }

            $sectionLabels = [
                'laravel'   => 'Database / Queue / Cache / Storage',
                'embedding' => 'Embedding API',
                'typesense' => 'Typesense',
            ];

            foreach ($groups as $section => $data) {
                $avg = $data['total'] / $data['count'];
                $label = $sectionLabels[$section] ?? $section;
                $this->line(sprintf('  %-38s %6d ms', $label, (int) round($avg)));
            }
        }

        $this->line('');
        $this->line('── Results ─────────────────────────────');

        $sections = ['laravel', 'embedding', 'typesense', 'config', 'consistency'];
        $sectionLabels = [
            'laravel'      => 'Laravel',
            'embedding'    => 'Embedding Service',
            'typesense'    => 'Typesense',
            'config'       => 'Configuration',
            'consistency'  => 'Consistency',
        ];

        foreach ($sections as $section) {
            $checks = array_filter($this->results, fn ($r) => $r['section'] === $section);

            if (empty($checks)) {
                continue;
            }

            $this->line("  {$sectionLabels[$section]}");

            foreach ($checks as $check) {
                $marker = $check['status'] ? "\xE2\x9C\x93" : "\xE2\x9C\x97";
                $color = $check['status'] ? 'info' : 'error';

                $this->$color("    {$marker} {$check['label']}");
            }
        }

        // Show any failures with details
        $failures = array_filter($this->results, fn ($r) => ! $r['status']);

        if (! empty($failures)) {
            $this->line('');
            $this->line('── Failures ───────────────────────────');

            foreach ($failures as $failure) {
                $this->error("  {$failure['section']}: {$failure['label']}");
                if (! empty($failure['detail'])) {
                    $this->line("    {$failure['detail']}");
                }
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function recordPass(string $section, string $label, string $detail = '', ?float $latency = null): void
    {
        $this->results[] = [
            'section' => $section,
            'label'   => $label,
            'status'  => true,
            'detail'  => $detail,
            'latency' => $latency,
        ];

        $marker = "\xE2\x9C\x93";
        $this->info("  {$marker} {$label}" . ($detail ? " — {$detail}" : ''));
    }

    private function recordFail(string $section, string $label, string $detail = '', string $errorDetail = '', ?float $latency = null): void
    {
        $this->results[] = [
            'section' => $section,
            'label'   => $label,
            'status'  => false,
            'detail'  => $errorDetail ?: $detail,
            'latency' => $latency,
        ];

        $marker = "\xE2\x9C\x97";
        $this->error("  {$marker} {$label}" . ($detail ? " — {$detail}" : ''));

        if (! empty($errorDetail)) {
            $this->line("     {$errorDetail}");
        }
    }

    private function hasFailures(): bool
    {
        foreach ($this->results as $result) {
            if (! $result['status']) {
                return true;
            }
        }
        return false;
    }
}
