<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\Spreadsheet\Security\TypesensePublicProjectionSyncService;
use Illuminate\Console\Command;

class ReconcileTypesensePublicSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'typesense:reconcile-public-schema
                            {--workspace= : The workspace ID to reconcile}
                            {--document= : Optional document ID to reconcile}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect, sanitize, and purge any legacy internal/restricted fields from existing Typesense index collections';

    /**
     * Execute the console command.
     */
    public function handle(TypesensePublicProjectionSyncService $syncService): int
    {
        $workspaceId = $this->option('workspace');
        $documentId = $this->option('document') ? (int) $this->option('document') : null;

        $workspaces = $workspaceId
            ? Workspace::where('id', $workspaceId)->get()
            : Workspace::all();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found for reconciliation.');
            return self::SUCCESS;
        }

        $this->info("Starting Typesense public schema reconciliation across {$workspaces->count()} workspace(s)...");

        $totalScanned = 0;
        $totalPurged = 0;

        foreach ($workspaces as $ws) {
            $result = $syncService->reconcileAndPurgeLegacyFields((int) $ws->id, $documentId);
            $totalScanned += $result['scanned_records'];
            $totalPurged += $result['purged_restricted_records'];

            $this->line("Workspace #{$ws->id}: Scanned {$result['scanned_records']} records, Purged {$result['purged_restricted_records']} restricted records.");
            if (! empty($result['purged_fields'])) {
                $this->line("  Purged restricted fields: " . implode(', ', $result['purged_fields']));
            }
        }

        $this->info("Reconciliation complete! Total records scanned: {$totalScanned}, Total purged from public index: {$totalPurged}.");
        return self::SUCCESS;
    }
}

