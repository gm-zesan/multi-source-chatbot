<?php

namespace App\Services\FAQ;

use App\Enums\RoleEnum;
use App\Models\FAQCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class FAQCategoryService
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
            return FAQCategory::query();
        }

        // Regular users scope to their workspace
        return FAQCategory::where('workspace_id', $this->getWorkspaceId());
    }

    /**
     * Get all FAQ categories for DataTables (server-side).
     *
     * Follows the same pattern as UserController and ContactFormController.
     */
    public function getDataTables(): JsonResponse
    {
        $categories = $this->workspaceQuery()->withCount('faqs')->withTrashed()->get();

        return DataTables::of($categories)
            ->addIndexColumn()
            ->addColumn('name', function (FAQCategory $category) {
                return $category->name;
            })
            ->addColumn('slug', function (FAQCategory $category) {
                return $category->slug;
            })
            ->addColumn('faqs_count', function (FAQCategory $category) {
                return '<span class="badge bg-primary">' . $category->faqs_count . '</span>';
            })
            ->addColumn('order_column', function (FAQCategory $category) {
                return $category->order_column;
            })
            ->addColumn('status_badge', function (FAQCategory $category) {
                if ($category->trashed()) {
                    return '<span class="badge bg-secondary">Deleted</span>';
                }
                return $category->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-warning text-dark">Inactive</span>';
            })
            ->addColumn('is_active', function (FAQCategory $category) {
                return ['id' => $category->id, 'is_active' => $category->is_active];
            })
            ->addColumn('action-btn', function (FAQCategory $category) {
                return ['id' => $category->id, 'trashed' => $category->trashed()];
            })
            ->rawColumns(['faqs_count', 'status_badge'])
            ->make(true);
    }

    /**
     * Get all active categories (for dropdowns).
     */
    public function getActive(): Collection
    {
        return $this->workspaceQuery()->where('is_active', true)
            ->orderBy('order_column')
            ->get();
    }

    /**
     * Create a new FAQ category.
     */
    public function create(array $data): FAQCategory
    {
        return DB::transaction(function () use ($data) {
            $data['workspace_id'] = $this->getWorkspaceId();
            $data['slug'] = Str::slug($data['name']);
            $data['created_by'] = Auth::id();

            return FAQCategory::create($data);
        });
    }

    /**
     * Update an existing FAQ category.
     */
    public function update(FAQCategory $category, array $data): FAQCategory
    {
        return DB::transaction(function () use ($category, $data) {
            if (isset($data['name']) && $data['name'] !== $category->name) {
                $data['slug'] = Str::slug($data['name']);
            }
            $data['updated_by'] = Auth::id();

            $category->update($data);

            return $category->fresh();
        });
    }

    /**
     * Soft delete a category.
     */
    public function delete(FAQCategory $category): void
    {
        $category->delete();
    }

    /**
     * Restore a soft-deleted category.
     */
    public function restore(string $id): void
    {
        $category = $this->workspaceQuery()->withTrashed()->findOrFail($id);
        $category->restore();
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(FAQCategory $category): bool
    {
        $category->update([
            'is_active' => !$category->is_active,
            'updated_by' => Auth::id(),
        ]);

        return $category->fresh()->is_active;
    }
}
