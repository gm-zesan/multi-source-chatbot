<?php

declare(strict_types=1);

namespace App\Services\FAQ;

use App\Enums\RoleEnum;
use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class FAQService
{
    public function __construct(
        private readonly FAQIndexer $indexer,
    ) {}
    /**
     * Get the authenticated user's workspace ID.
     */
    private function getWorkspaceId(): ?int
    {
        return Auth::user()?->workspace_id;
    }

    /**
     * Return a query scoped to the current workspace.
     *
     * Superadmin sees all records across all workspaces.
     * Regular users are scoped to their own workspace.
     */
    private function workspaceQuery()
    {
        /** @var User|null $user */
        $user = Auth::user();

        // Superadmin sees everything
        if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return FAQ::query();
        }

        // Regular users scope to their workspace
        return FAQ::where('workspace_id', $this->getWorkspaceId());
    }

    /**
     * Get all FAQs for DataTables (server-side).
     *
     * Follows the same pattern as UserController and ContactFormController.
     */
    public function getDataTables(?Request $request = null): JsonResponse
    {
        $query = $this->workspaceQuery()->with(['category', 'lexicon']);

        $status = $request?->input('status');
        if ($status === 'trashed') {
            $query->onlyTrashed();
        } elseif ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } else {
            $query->withTrashed();
        }

        $categoryId = $request?->input('category_id');
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $faqs = $query->get();

        return DataTables::of($faqs)
            ->addIndexColumn()
            ->addColumn('question', function (FAQ $faq) {
                return $faq->question;
            })
            ->addColumn('category', function (FAQ $faq) {
                return $faq->category?->name ?? '<span class="text-muted">Uncategorized</span>';
            })
            ->addColumn('document_type', function (FAQ $faq) {
                $label = $faq->documentTypeLabel();
                $isPolicy = $faq->isPolicy();
                $badgeStyle = $isPolicy
                    ? 'background-color: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe;'
                    : 'background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;';
                $icon = $isPolicy ? 'ri-shield-check-line' : 'ri-question-line';
                return '<span class="badge" style="' . $badgeStyle . ' font-weight: 500; font-size: 11px;">'
                    . '<i class="' . $icon . ' me-1"></i>' . e($label) . '</span>';
            })
            ->addColumn('commerce_domain', function (FAQ $faq) {
                $domain = $faq->lexicon?->domain ?? 'General Support';
                return '<span class="badge" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 500; font-size: 11px;">'
                    . '<i class="ri-store-2-line me-1 text-primary"></i>' . e($domain) . '</span>';
            })
            ->addColumn('lexicon_badge', function (FAQ $faq) {
                if (!$faq->lexicon) {
                    return '<span class="badge bg-secondary-subtle text-secondary" style="font-size: 11px;">Pending Sync</span>';
                }
                $terms = $faq->lexicon->allTerms();
                $termCount = count($terms);
                $sample = e(implode(', ', array_slice($terms, 0, 5)));
                return '<span class="badge" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 500; font-size: 11px;" title="' . $sample . '">'
                    . '<i class="ri-sparkling-line me-1"></i>' . $termCount . ' Terms</span>';
            })
            ->addColumn('priority', function (FAQ $faq) {
                return $faq->priority;
            })
            ->addColumn('hit_count', function (FAQ $faq) {
                return '<span class="badge bg-info">' . number_format($faq->hit_count) . '</span>';
            })
            ->addColumn('status_badge', function (FAQ $faq) {
                if ($faq->trashed()) {
                    return '<span class="badge bg-secondary">Deleted</span>';
                }
                return $faq->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-warning text-dark">Inactive</span>';
            })
            ->addColumn('is_active', function (FAQ $faq) {
                return ['id' => $faq->id, 'is_active' => $faq->is_active];
            })
            ->addColumn('action-btn', function (FAQ $faq) {
                return ['id' => $faq->id, 'trashed' => $faq->trashed()];
            })
            ->rawColumns(['category', 'document_type', 'commerce_domain', 'lexicon_badge', 'hit_count', 'status_badge'])
            ->make(true);
    }

    /**
     * Get all active categories for the current workspace (for filters/dropdowns).
     */
    public function getCategories(): Collection
    {
        /** @var User|null $user */
        $user = Auth::user();

        $query = FAQCategory::where('is_active', true)->orderBy('order_column');

        // Superadmin sees all categories; regular users scope by workspace
        if (! $user || ! $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            $query->where('workspace_id', $this->getWorkspaceId());
        }

        return $query->get();
    }

    /**
     * Create a new FAQ.
     */
    public function create(array $data): FAQ
    {
        $faq = DB::transaction(function () use ($data) {
            $data['workspace_id'] = $this->getWorkspaceId();
            $data['searchable_text'] = strip_tags(($data['question'] ?? '') . ' ' . ($data['answer'] ?? ''));
            $data['created_by'] = Auth::id();

            return FAQ::create($data);
        });

        $this->indexer->dispatchIndex($faq, 'index');

        return $faq;
    }

    /**
     * Update an existing FAQ.
     */
    public function update(FAQ $faq, array $data): FAQ
    {
        $faq = DB::transaction(function () use ($faq, $data) {
            if (isset($data['question']) || isset($data['answer'])) {
                $data['searchable_text'] = strip_tags(
                    ($data['question'] ?? $faq->question) . ' ' . ($data['answer'] ?? $faq->answer)
                );
            }
            $data['updated_by'] = Auth::id();

            $faq->update($data);

            return $faq->fresh();
        });

        $this->indexer->dispatchIndex($faq, 'update');

        return $faq;
    }

    /**
     * Soft delete a FAQ.
     */
    public function delete(FAQ $faq): void
    {
        $faq->delete();
        $this->indexer->dispatchIndex($faq, 'delete');
    }

    /**
     * Restore a soft-deleted FAQ.
     */
    public function restore(string $id): void
    {
        $faq = $this->workspaceQuery()->withTrashed()->findOrFail($id);
        $faq->restore();
        $this->indexer->dispatchIndex($faq, 'index');
    }

    /**
     * Bulk soft delete FAQs.
     */
    public function bulkDelete(array $ids): int
    {
        $count = $this->workspaceQuery()->whereIn('id', $ids)->delete();

        // Dispatch delete jobs for each removed FAQ
        FAQ::onlyTrashed()->whereIn('id', $ids)->chunk(100, function ($faqs) {
            $this->indexer->dispatchBatch($faqs, 'delete');
        });

        return $count;
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(FAQ $faq): bool
    {
        $faq->update([
            'is_active' => !$faq->is_active,
            'updated_by' => Auth::id(),
        ]);

        $fresh = $faq->fresh();

        // Re-index or remove based on new active state
        if ($fresh->is_active) {
            $this->indexer->dispatchIndex($fresh, 'index');
        } else {
            $this->indexer->dispatchIndex($fresh, 'delete');
        }

        return $fresh->is_active;
    }

    /**
     * Manually trigger immediate re-sync to Typesense and regenerate AI lexicon.
     */
    public function resync(FAQ $faq): void
    {
        $this->indexer->dispatchIndex($faq, 'index');
    }
}
