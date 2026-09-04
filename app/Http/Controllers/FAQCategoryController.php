<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFAQCategoryRequest;
use App\Http\Requests\UpdateFAQCategoryRequest;
use App\Models\FAQCategory;
use App\Services\FAQ\FAQCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FAQCategoryController extends Controller
{
    public function __construct(
        private readonly FAQCategoryService $categoryService
    ) {}

    /**
     * Display a listing of FAQ categories.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->categoryService->getDataTables();
        }

        return view('admin.faq-categories.index');
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        return view('admin.faq-categories.create');
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreFAQCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()->route('faq-categories.index')
            ->with('success', 'FAQ category created successfully.');
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(FAQCategory $faqCategory): View
    {
        $this->authorizeWorkspace($faqCategory);

        return view('admin.faq-categories.edit', [
            'category' => $faqCategory,
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateFAQCategoryRequest $request, FAQCategory $faqCategory): RedirectResponse
    {
        $this->authorizeWorkspace($faqCategory);

        $this->categoryService->update($faqCategory, $request->validated());

        return redirect()->route('faq-categories.index')
            ->with('success', 'FAQ category updated successfully.');
    }

    /**
     * Soft delete the specified category.
     */
    public function destroy(FAQCategory $faqCategory): RedirectResponse
    {
        $this->authorizeWorkspace($faqCategory);

        $this->categoryService->delete($faqCategory);

        return redirect()->route('faq-categories.index')
            ->with('success', 'FAQ category moved to trash.');
    }

    /**
     * Restore a soft-deleted category.
     */
    public function restore(string $id): RedirectResponse
    {
        $this->categoryService->restore($id);

        return redirect()->route('faq-categories.index')
            ->with('success', 'FAQ category restored successfully.');
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(FAQCategory $faqCategory): JsonResponse
    {
        $this->authorizeWorkspace($faqCategory);

        $active = $this->categoryService->toggleActive($faqCategory);

        return response()->json([
            'success'   => true,
            'is_active' => $active,
            'message'   => $active ? 'Category activated.' : 'Category deactivated.',
        ]);
    }

    /**
     * Ensure the category belongs to the user's workspace.
     */
    private function authorizeWorkspace(FAQCategory $category): void
    {
        if ($category->workspace_id !== Auth::user()->workspace_id) {
            abort(403);
        }
    }
}
