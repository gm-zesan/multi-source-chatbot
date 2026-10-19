<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('documents:process-pending', function (\App\Services\Knowledge\KnowledgeDocumentService $service) {
    $pendingDocs = \App\Models\KnowledgeDocument::where('lifecycle_status', \App\Enums\DocumentLifecycleStatus::PENDING_INGESTION)->get();
    $this->info("Found {$pendingDocs->count()} pending document(s) to process.");

    foreach ($pendingDocs as $doc) {
        $this->line("Processing [{$doc->id}] {$doc->title} ({$doc->original_filename})...");
        try {
            $service->retry((string) $doc->id);
            $doc->refresh();
            $this->info(" -> Status: {$doc->lifecycle_status->value}");
        } catch (\Throwable $e) {
            $this->error(" -> Error: {$e->getMessage()}");
        }
    }

    $this->info("Done processing pending documents.");
})->purpose('Process and activate all documents stuck in pending_ingestion status');

