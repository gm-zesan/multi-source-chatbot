<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permissions\FAQPermission;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\FAQ\FAQService;
use App\Services\Knowledge\KnowledgeDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly FAQService $faqService,
        private readonly KnowledgeDocumentService $documentService,
    ) {}

    /**
     * Display the unified Knowledge Base hub.
     */
    public function index(Request $request): View|JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $isSuperAdmin = $user->hasRole(RoleEnum::SUPERADMIN->value);
        $canViewFaqs = $isSuperAdmin || $user->can(FAQPermission::VIEW->value);
        $canViewDocs = $isSuperAdmin || $user->can(KnowledgeDocumentPermission::VIEW->value);

        if (! $canViewFaqs && ! $canViewDocs) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax() || $request->wantsJson() || $request->has('draw')) {
            $source = $request->input('source');
            if ($source === 'documents' || $request->has('purpose')) {
                if (! $canViewDocs) {
                    abort(403, 'Unauthorized to access knowledge documents.');
                }
                return $this->documentService->getDataTables($request);
            }

            if (! $canViewFaqs) {
                abort(403, 'Unauthorized to access FAQs and policies.');
            }
            return $this->faqService->getDataTables($request);
        }

        $requestedTab = $request->input('tab');
        if ($requestedTab === 'documents') {
            $requestedTab = 'data_sources';
        }

        $activeTab = $requestedTab;

        if ($canViewFaqs && $canViewDocs) {
            $validTabs = ['faqs', 'policies', 'business_info', 'data_sources'];
            if (! in_array($activeTab, $validTabs, true)) {
                $activeTab = 'faqs';
            }
        } elseif ($canViewFaqs) {
            $validTabs = ['faqs', 'policies', 'business_info'];
            if (! in_array($activeTab, $validTabs, true)) {
                $activeTab = 'faqs';
            }
        } else {
            // Document-only permission
            $activeTab = 'data_sources';
        }

        return view('admin.knowledge-base.index', [
            'activeTab'    => $activeTab,
            'canViewFaqs'  => $canViewFaqs,
            'canViewDocs'  => $canViewDocs,
            'isSuperAdmin' => $isSuperAdmin,
            'workspaces'   => $isSuperAdmin ? \App\Models\Workspace::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /**
     * Trigger a complete re-index of active FAQs / Knowledge Base to Typesense.
     */
    public function reindex(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $isSuperAdmin = $user->hasRole(RoleEnum::SUPERADMIN->value);
        $canUpdate = $isSuperAdmin || $user->can(FAQPermission::UPDATE->value) || $user->can(FAQPermission::CREATE->value);

        if (! $canUpdate) {
            abort(403, 'Unauthorized to trigger re-index.');
        }

        $explicitWsId = null;
        if ($isSuperAdmin && $request->filled('workspace_id')) {
            $explicitWsId = (int) $request->input('workspace_id');
        }

        $result = $this->faqService->reindexAll($explicitWsId);

        return response()->json([
            'success' => true,
            'message' => "Successfully re-indexed {$result['synced']} FAQ(s) into vector search engine." . ($result['failed'] > 0 ? " ({$result['failed']} failed)" : ''),
            'total'   => $result['total'],
            'synced'  => $result['synced'],
            'failed'  => $result['failed'],
        ]);
    }
}
