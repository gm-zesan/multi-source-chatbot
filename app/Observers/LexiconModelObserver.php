<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SyncLexiconToEmbeddingServiceJob;
use Illuminate\Database\Eloquent\Model;

class LexiconModelObserver
{
    /**
     * Handle the Model "saving" event.
     * Auto-increments version on modifications.
     */
    public function saving(Model $model): void
    {
        if ($model->exists && $model->isDirty()) {
            $model->version = ((int) ($model->version ?? 1)) + 1;
        }
    }

    /**
     * Handle the Model "saved" event.
     */
    public function saved(Model $model): void
    {
        $this->dispatchSyncJob($model);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->dispatchSyncJob($model);
    }

    /**
     * Handle the Model "restored" event.
     */
    public function restored(Model $model): void
    {
        $this->dispatchSyncJob($model);
    }

    /**
     * Dispatch the background sync job for the affected workspace.
     */
    private function dispatchSyncJob(Model $model): void
    {
        $workspaceId = (int) ($model->workspace_id ?? 0);
        SyncLexiconToEmbeddingServiceJob::dispatch($workspaceId);
    }
}
