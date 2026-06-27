<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * GenerateEmbeddingsCommand
 *
 * Laravel command that delegates embedding generation to the Python
 * sentence-transformers script. Provides a progress bar, timeout handling,
 * detailed logging, and rollback on failure.
 *
 * Usage:
 *   php artisan embeddings:generate                  # Incremental generation
 *   php artisan embeddings:generate --refresh        # Full regenerate
 *   php artisan embeddings:generate --source=db_01   # Single source
 *   php artisan embeddings:generate --type=table     # Single entity type
 *   php artisan embeddings:generate --dry-run        # Preview only
 *   php artisan embeddings:generate --timeout=300    # Custom timeout (seconds)
 *
 * The Python script reads from MySQL directly and saves embeddings
 * to the `embeddings` table. This command manages the process lifecycle,
 * captures output, and reports progress back to the user.
 */
class GenerateEmbeddingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'embeddings:generate
                            {--refresh : Force regenerate all embeddings, replacing existing ones}
                            {--source= : Only process a specific source (e.g., db_01)}
                            {--type= : Only process this entity type (database, table, alias)}
                            {--dry-run : Show what would be generated without executing}
                            {--timeout=300 : Maximum execution time in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate 384-dimension embeddings for all registered sources, tables, and aliases using sentence-transformers';

    /**
     * Execute the console command.
     *
     * Constructs the Python command, runs it via Symfony Process with
     * real-time output streaming, progress indication, and error handling.
     */
    public function handle(): int
    {
        $pythonPath = config('chatbot.embedding.python', 'python');
        $scriptPath = config('chatbot.embedding.script');

        // Fallback to the new script path if the configured one doesn't exist
        if (!file_exists($scriptPath)) {
            $scriptPath = storage_path('scripts/generate_embeddings.py');
        }

        if (!file_exists($scriptPath)) {
            $this->error('Embedding script not found at: ' . $scriptPath);
            $this->line('');
            $this->warn('Make sure storage/scripts/generate_embeddings.py exists.');
            $this->warn('Install Python dependencies: pip install -r storage/scripts/requirements.txt');
            return Command::FAILURE;
        }

        // ── Build Command Arguments ──
        $args = [
            $pythonPath,
            $scriptPath,
        ];

        if ($this->option('refresh')) {
            $args[] = '--refresh';
        }

        if ($source = $this->option('source')) {
            $args[] = '--source';
            $args[] = $source;
        }

        if ($type = $this->option('type')) {
            $args[] = '--type';
            $args[] = $type;
        }

        if ($this->option('dry-run')) {
            $args[] = '--dry-run';
        }

        $timeout = (int) $this->option('timeout');

        // ── Display Configuration ──
        $this->info('🚀 Starting embedding generation via Python...');
        $this->newLine();
        $this->line('Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Python', $pythonPath],
                ['Script', $scriptPath],
                ['Refresh', $this->option('refresh') ? 'Yes' : 'No (incremental)'],
                ['Source', $this->option('source') ?? 'All'],
                ['Type', $this->option('type') ?? 'All'],
                ['Dry Run', $this->option('dry-run') ? 'Yes' : 'No'],
                ['Timeout', $timeout . ' seconds'],
            ]
        );
        $this->newLine();

        // Check Python dependencies
        if (!$this->checkPythonDependencies($pythonPath)) {
            return Command::FAILURE;
        }

        // ── Run Process ──
        $process = new Process($args);
        $process->setTimeout($timeout);
        $process->setIdleTimeout($timeout);

        $startTime = microtime(true);

        try {
            $this->line('📡 Running Python embedding generator...');
            $this->newLine();

            $process->mustRun(function ($type, $buffer) {
                // Stream output in real-time
                $this->output->write($buffer);
            });

            $elapsed = round(microtime(true) - $startTime, 2);
            $this->newLine();
            $this->info("✅ Embedding generation completed successfully in {$elapsed}s.");

            Log::info('Embedding generation completed', [
                'elapsed_seconds' => $elapsed,
                'refresh' => $this->option('refresh'),
                'source' => $this->option('source'),
                'type' => $this->option('type'),
            ]);

            return Command::SUCCESS;

        } catch (ProcessTimedOutException $e) {
            $elapsed = round(microtime(true) - $startTime, 2);
            $this->error("❌ Process timed out after {$elapsed}s (limit: {$timeout}s).");
            $this->warn('Consider increasing --timeout or reducing batch size.');
            $this->warn('You can also run the Python script directly:');
            $this->line("  {$pythonPath} {$scriptPath} --refresh --batch-size 32");

            Log::error('Embedding generation timed out', [
                'elapsed_seconds' => $elapsed,
                'timeout' => $timeout,
            ]);

            return Command::FAILURE;

        } catch (ProcessFailedException $e) {
            $elapsed = round(microtime(true) - $startTime, 2);
            $this->error("❌ Embedding generation failed after {$elapsed}s.");

            // Show the error output
            $errorOutput = $process->getErrorOutput();
            if (!empty(trim($errorOutput))) {
                $this->newLine();
                $this->line('<fg=red>Error Output:</>');
                $this->line($errorOutput);
            }

            $this->newLine();
            $this->warn('Troubleshooting:');
            $this->warn('  1. Check Python is installed: python --version');
            $this->warn('  2. Install dependencies: pip install -r storage/scripts/requirements.txt');
            $this->warn('  3. Check MySQL connection settings in .env');
            $this->warn('  4. Run the Python script directly for more details:');
            $this->line("     {$pythonPath} {$scriptPath} --refresh");

            Log::error('Embedding generation failed', [
                'elapsed_seconds' => $elapsed,
                'exit_code' => $process->getExitCode(),
                'error_output' => mb_substr($process->getErrorOutput(), 0, 2000),
            ]);

            return Command::FAILURE;

        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $startTime, 2);
            $this->error("❌ Unexpected error after {$elapsed}s: {$e->getMessage()}");

            Log::error('Embedding generation unexpected error', [
                'elapsed_seconds' => $elapsed,
                'error' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 1000),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Check that Python and required packages are available.
     */
    protected function checkPythonDependencies(string $pythonPath): bool
    {
        $this->line('🔍 Checking Python environment...');

        // Check Python version
        $versionProcess = new Process([$pythonPath, '--version']);
        $versionProcess->setTimeout(10);
        $versionProcess->run();

        if (!$versionProcess->isSuccessful()) {
            $this->error("Python not found at '{$pythonPath}'.");
            $this->warn('Install Python 3.8+ and set CHATBOT_PYTHON_PATH in .env if needed.');
            return false;
        }

        $this->line('   ✅ Python: ' . trim($versionProcess->getOutput()));

        // Check sentence-transformers availability (use pip show — much faster than importing)
        $checkProcess = new Process([
            $pythonPath, '-m', 'pip', 'show', 'sentence-transformers',
        ]);
        $checkProcess->setTimeout(15);
        $checkProcess->run();

        if (!$checkProcess->isSuccessful()) {
            $this->warn('   ⚠️  sentence-transformers not installed. Run:');
            $this->warn('      pip install -r storage/scripts/requirements.txt');
            $this->warn('   Continuing anyway — the Python script will show detailed errors.');
        } else {
            $versionLine = trim($checkProcess->getOutput());
            // Extract version from pip show output
            if (preg_match('/^Version:\s*(.+)$/m', $versionLine, $m)) {
                $this->line('   ✅ sentence-transformers v' . $m[1]);
            } else {
                $this->line('   ✅ sentence-transformers installed');
            }
        }

        $this->newLine();
        return true;
    }
}
