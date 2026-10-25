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
        $this->authorizeHubAccess();

        if ($request->ajax() || $request->wantsJson() || $request->has('draw')) {
            $source = $request->input('source');
            if ($source === 'documents' || $request->has('purpose')) {
                return $this->documentService->getDataTables($request);
            }

            return $this->faqService->getDataTables($request);
        }

        $activeTab = $request->input('tab', 'faqs');
        if ($activeTab === 'documents') {
            $activeTab = 'data_sources';
        }

        $validTabs = ['faqs', 'policies', 'business_info', 'data_sources'];
        if (! in_array($activeTab, $validTabs, true)) {
            $activeTab = 'faqs';
        }

        return view('admin.knowledge-base.index', [
            'activeTab' => $activeTab,
        ]);
    }

    /**
     * Authorize user access to the Knowledge Base hub.
     */
    private function authorizeHubAccess(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        if ($user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return;
        }

        $canViewFaqs = $user->can(FAQPermission::VIEW->value);
        $canViewDocs = $user->can(KnowledgeDocumentPermission::VIEW->value);

        if (! $canViewFaqs && ! $canViewDocs) {
            abort(403, 'Unauthorized action.');
        }
    }
}
