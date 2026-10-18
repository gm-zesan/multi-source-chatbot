<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Http\Requests\StoreKnowledgeDocumentRequest;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Knowledge\KnowledgeDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KnowledgeDocumentController extends Controller
{
    public function __construct(
        private readonly KnowledgeDocumentService $documentService,
    ) {}

    /**
     * Display a listing of knowledge documents.
     */
    public function index(Request $request): View|JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::VIEW->value);

        if ($request->ajax() || $request->wantsJson() || $request->has('draw')) {
            return $this->documentService->getDataTables($request);
        }

        return view('admin.documents.index');
    }

    /**
     * Show the form for uploading a new knowledge document.
     */
    public function create(): View
    {
        $this->authorizeAction(KnowledgeDocumentPermission::CREATE->value);

        return view('admin.documents.create');
    }

    /**
     * Store and persist an uploaded document.
     */
    public function store(StoreKnowledgeDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('document');
        $title = $request->input('title');

        $document = $this->documentService->store($file, $title);

        if ($document->hasFailed()) {
            return redirect()->route('documents.index')
                ->with('error', 'Document uploaded, but analytics registration failed: ' . ($document->error_message ?? 'Unknown error.'));
        }

        return redirect()->route('documents.index')
            ->with('success', 'Document uploaded and registered successfully.');
    }

    /**
     * Soft delete the specified document.
     */
    public function destroy(KnowledgeDocument $document): RedirectResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::DELETE->value);
        $this->authorizeWorkspace($document);

        $result = $this->documentService->delete($document);

        if (! empty($result['cleanup_warning'])) {
            return redirect()->route('documents.index')
                ->with('error', 'Document moved to trash, but ' . $result['cleanup_warning']);
        }

        return redirect()->route('documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    /**
     * Restore a soft-deleted document.
     */
    public function restore(string $id): RedirectResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);

        $this->documentService->restore($id);

        return redirect()->route('documents.index')
            ->with('success', 'Document restored successfully.');
    }

    /**
     * Retry registration for a failed document.
     */
    public function retry(string $id): RedirectResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);

        $document = $this->documentService->retry($id);

        if ($document->hasFailed()) {
            return redirect()->route('documents.index')
                ->with('error', 'Retry failed: ' . ($document->error_message ?? 'Unknown error.'));
        }

        return redirect()->route('documents.index')
            ->with('success', 'Document reprocessed and registered successfully.');
    }

    /**
     * Ensure the authenticated user has the required permission.
     * Superadmins bypass permission checks.
     */
    private function authorizeAction(string $permission): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return;
        }

        if (! $user || ! $user->can($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Ensure the document belongs to the authenticated user's workspace.
     * Superadmins are allowed across all workspaces.
     */
    private function authorizeWorkspace(KnowledgeDocument $document): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return;
        }

        if ($document->workspace_id !== $user?->workspace_id) {
            abort(403, 'Unauthorized access to document from another workspace.');
        }
    }
}
