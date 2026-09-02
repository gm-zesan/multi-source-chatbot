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

                $status = $faq->lifecycle_status ?? ($faq->is_active ? \App\Enums\FaqLifecycleStatus::ACTIVE : \App\Enums\FaqLifecycleStatus::DRAFT);

                return match ($status) {
                    \App\Enums\FaqLifecycleStatus::ACTIVE => '<span class="badge bg-success" style="font-size: 11px;"><i class="ri-checkbox-circle-line me-1"></i>Active</span>',
                    \App\Enums\FaqLifecycleStatus::VALIDATING => '<span class="badge bg-info text-white" style="font-size: 11px;"><i class="ri-loader-4-line ri-spin me-1"></i>Validating</span>',
                    \App\Enums\FaqLifecycleStatus::SYNCING => '<span class="badge" style="background-color: #8b5cf6; color: white; font-size: 11px;"><i class="ri-refresh-line ri-spin me-1"></i>Syncing</span>',
                    \App\Enums\FaqLifecycleStatus::VALIDATION_FAILED => '<span class="badge bg-danger" style="font-size: 11px;" title="' . e($faq->sync_error ?? 'Validation failed') . '"><i class="ri-error-warning-line me-1"></i>Validation Failed</span>',
                    \App\Enums\FaqLifecycleStatus::SYNC_FAILED => '<span class="badge bg-danger" style="font-size: 11px;" title="' . e($faq->sync_error ?? 'Sync failed') . '"><i class="ri-close-circle-line me-1"></i>Sync Failed</span>',
                    default => '<span class="badge bg-secondary" style="font-size: 11px;">Draft</span>',
                };
            })
            ->addColumn('is_active', function (FAQ $faq) {
                return ['id' => $faq->id, 'is_active' => $faq->is_active];
            })
            ->addColumn('action-btn', function (FAQ $faq) {
                return [
                    'id'         => $faq->id,
                    'trashed'    => $faq->trashed(),
                    'has_failed' => $faq->hasFailed(),
                    'error'      => $faq->sync_error,
                ];
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

            $wantsActive = (bool) ($data['is_active'] ?? true);
            if ($wantsActive) {
                // Invariant: starts as VALIDATING and is unsearchable until validation & Typesense sync succeed
                $data['lifecycle_status'] = \App\Enums\FaqLifecycleStatus::VALIDATING->value;
                $data['is_active'] = false;
            } else {
                $data['lifecycle_status'] = \App\Enums\FaqLifecycleStatus::DRAFT->value;
                $data['is_active'] = false;
            }

            return FAQ::create($data);
        });

        if ($faq->lifecycle_status === \App\Enums\FaqLifecycleStatus::VALIDATING) {
            $this->indexer->dispatchIndex($faq, 'index');
        }

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

            $wantsActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $faq->is_active;
            if ($wantsActive) {
                $data['lifecycle_status'] = \App\Enums\FaqLifecycleStatus::VALIDATING->value;
                $data['is_active'] = false;
                $data['sync_error'] = null;
            } else {
                $data['lifecycle_status'] = \App\Enums\FaqLifecycleStatus::DRAFT->value;
                $data['is_active'] = false;
            }

            $faq->update($data);

            return $faq->fresh();
        });

        if ($faq->lifecycle_status === \App\Enums\FaqLifecycleStatus::VALIDATING) {
            $this->indexer->dispatchIndex($faq, 'update');
        } else {
            $this->indexer->dispatchIndex($faq, 'delete');
        }

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
        $newActive = ! $faq->is_active;

        if ($newActive) {
            $faq->update([
                'is_active'        => false,
                'lifecycle_status' => \App\Enums\FaqLifecycleStatus::VALIDATING,
                'sync_error'       => null,
                'updated_by'       => Auth::id(),
            ]);
            $this->indexer->dispatchIndex($faq, 'index');
            return true;
        }

        $faq->update([
            'is_active'        => false,
            'lifecycle_status' => \App\Enums\FaqLifecycleStatus::DRAFT,
            'updated_by'       => Auth::id(),
        ]);
        $this->indexer->dispatchIndex($faq, 'delete');
        return false;
    }

    /**
     * Manually trigger immediate re-sync to Typesense and regenerate AI lexicon.
     */
    public function resync(FAQ $faq): void
    {
        $faq->update([
            'lifecycle_status' => \App\Enums\FaqLifecycleStatus::VALIDATING,
            'is_active'        => false,
            'sync_error'       => null,
        ]);

        $this->indexer->dispatchIndex($faq, 'index');
    }
}
