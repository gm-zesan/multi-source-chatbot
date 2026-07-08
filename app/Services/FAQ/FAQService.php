<?php

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
        $query = $this->workspaceQuery()->with('category');

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
            ->rawColumns(['category', 'hit_count', 'status_badge'])
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
        return DB::transaction(function () use ($data) {
            $data['workspace_id'] = $this->getWorkspaceId();
            $data['searchable_text'] = strip_tags(($data['question'] ?? '') . ' ' . ($data['answer'] ?? ''));
            $data['created_by'] = Auth::id();

            return FAQ::create($data);
        });
    }

    /**
     * Update an existing FAQ.
     */
    public function update(FAQ $faq, array $data): FAQ
    {
        return DB::transaction(function () use ($faq, $data) {
            if (isset($data['question']) || isset($data['answer'])) {
                $data['searchable_text'] = strip_tags(
                    ($data['question'] ?? $faq->question) . ' ' . ($data['answer'] ?? $faq->answer)
                );
            }
            $data['updated_by'] = Auth::id();

            $faq->update($data);

            return $faq->fresh();
        });
    }

    /**
     * Soft delete a FAQ.
     */
    public function delete(FAQ $faq): void
    {
        $faq->delete();
    }

    /**
     * Restore a soft-deleted FAQ.
     */
    public function restore(string $id): void
    {
        $faq = $this->workspaceQuery()->withTrashed()->findOrFail($id);
        $faq->restore();
    }

    /**
     * Bulk soft delete FAQs.
     */
    public function bulkDelete(array $ids): int
    {
        return $this->workspaceQuery()->whereIn('id', $ids)->delete();
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

        return $faq->fresh()->is_active;
    }
}
