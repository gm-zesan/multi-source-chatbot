<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeApiController extends Controller
{
    /**
     * List active FAQs for the authenticated workspace.
     */
    public function faqs(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        if (! $workspace) {
            return new JsonResponse(['success' => false, 'error' => 'Workspace context not found'], 400);
        }

        $faqs = FAQ::where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->select(['id', 'question', 'answer', 'document_type', 'hit_count', 'created_at'])
            ->latest('id')
            ->paginate((int) $request->input('per_page', 20));

        return new JsonResponse([
            'success' => true,
            'data'    => $faqs->items(),
            'meta'    => [
                'current_page' => $faqs->currentPage(),
                'total'        => $faqs->total(),
                'per_page'     => $faqs->perPage(),
                'last_page'    => $faqs->lastPage(),
            ],
        ]);
    }

    /**
     * List active knowledge documents for the authenticated workspace.
     */
    public function documents(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        if (! $workspace) {
            return new JsonResponse(['success' => false, 'error' => 'Workspace context not found'], 400);
        }

        $documents = KnowledgeDocument::where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->select(['id', 'title', 'document_type', 'summary', 'status', 'created_at'])
            ->latest('id')
            ->paginate((int) $request->input('per_page', 20));

        return new JsonResponse([
            'success' => true,
            'data'    => $documents->items(),
            'meta'    => [
                'current_page' => $documents->currentPage(),
                'total'        => $documents->total(),
                'per_page'     => $documents->perPage(),
                'last_page'    => $documents->lastPage(),
            ],
        ]);
    }
}
