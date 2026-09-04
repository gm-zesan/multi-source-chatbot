<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Lexicon\LexiconSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalLexiconController extends Controller
{
    public function __construct(
        private readonly LexiconSnapshotService $snapshotService
    ) {}

    /**
     * Internal endpoint for Python LexiconRepository to fetch frozen snapshot.
     * GET /api/v1/internal/lexicon/snapshot?workspace_id={id}
     */
    public function getSnapshot(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->query('workspace_id', 0);
        
        $snapshot = $this->snapshotService->buildSnapshot($workspaceId);
        
        return response()->json($snapshot);
    }
}
