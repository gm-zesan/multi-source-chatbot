<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFAQRequest;
use App\Http\Requests\UpdateFAQRequest;
use App\Models\FAQ;
use App\Services\FAQ\FAQService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FAQController extends Controller
{
    public function __construct(
        private readonly FAQService $faqService
    ) {}

    /**
     * Display a listing of FAQs.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->faqService->getDataTables($request);
        }

        return view('admin.faqs.index', [
            'categories' => $this->faqService->getCategories(),
        ]);
    }

    /**
     * Show the form for creating a new FAQ.
     */
    public function create(): View
    {
        return view('admin.faqs.create', [
            'categories' => $this->faqService->getCategories(),
        ]);
    }

    /**
     * Store a newly created FAQ.
     */
    public function store(StoreFAQRequest $request): RedirectResponse
    {
        $this->faqService->create($request->validated());

        return redirect()->route('faqs.index')
            ->with('success', 'FAQ created successfully.');
    }

    /**
     * Show the form for editing the specified FAQ.
     */
    public function edit(FAQ $faq): View
    {
        $this->authorizeWorkspace($faq);

        return view('admin.faqs.edit', [
            'faq'        => $faq,
            'categories' => $this->faqService->getCategories(),
        ]);
    }

    /**
     * Update the specified FAQ.
     */
    public function update(UpdateFAQRequest $request, FAQ $faq): RedirectResponse
    {
        $this->authorizeWorkspace($faq);

        $this->faqService->update($faq, $request->validated());

        return redirect()->route('faqs.index')
            ->with('success', 'FAQ updated successfully.');
    }

    /**
     * Soft delete the specified FAQ.
     */
    public function destroy(FAQ $faq): RedirectResponse
    {
        $this->authorizeWorkspace($faq);

        $this->faqService->delete($faq);

        return redirect()->route('faqs.index')
            ->with('success', 'FAQ moved to trash.');
    }

    /**
     * Restore a soft-deleted FAQ.
     */
    public function restore(string $id): RedirectResponse
    {
        $this->faqService->restore($id);

        return redirect()->route('faqs.index')
            ->with('success', 'FAQ restored successfully.');
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(FAQ $faq): JsonResponse
    {
        $this->authorizeWorkspace($faq);

        $active = $this->faqService->toggleActive($faq);

        return response()->json([
            'success' => true,
            'is_active' => $active,
            'message' => $active ? 'FAQ activated.' : 'FAQ deactivated.',
        ]);
    }

    /**
     * Bulk soft delete FAQs.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'string|exists:faqs,id',
        ]);

        $count = $this->faqService->bulkDelete($request->input('ids'));

        return redirect()->route('faqs.index')
            ->with('success', "$count FAQ(s) moved to trash.");
    }

    /**
     * Ensure the FAQ belongs to the user's workspace.
     */
    private function authorizeWorkspace(FAQ $faq): void
    {
        if ($faq->workspace_id !== Auth::user()->workspace_id) {
            abort(403);
        }
    }
}
